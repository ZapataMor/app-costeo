<?php

namespace App\Services\Costing;

use App\Enums\EstadoCirugia;
use App\Models\CostoCirugia;
use App\Support\Estadistica;
use Illuminate\Support\Collection;

/**
 * Detección de outliers en costos de cirugía, agrupados por procedimiento
 * principal. Combina dos criterios clásicos:
 *  - z-score: |z| > 3 (requiere desviación > 0)
 *  - Tukey/IQR: fuera de [Q1 − 1.5·IQR, Q3 + 1.5·IQR]
 */
class OutlierDetector
{
    public const UMBRAL_Z = 3.0;

    public const FACTOR_IQR = 1.5;

    /**
     * @return Collection<int, array<string, mixed>> un grupo por procedimiento
     */
    public function analizar(?int $procedimientoId = null): Collection
    {
        $filas = CostoCirugia::query()
            ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
            // Solo cirugías contabilizables (realizadas y terminadas)
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('cirugias.hora_fin')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')
                    ->where('cirugia_procedimiento.es_principal', true);
            })
            ->join(
                'procedimientos_quirurgicos',
                'procedimientos_quirurgicos.id',
                '=',
                'cirugia_procedimiento.procedimiento_quirurgico_id',
            )
            ->when(
                $procedimientoId !== null,
                fn ($query) => $query->where('procedimientos_quirurgicos.id', $procedimientoId),
            )
            ->select([
                'costos_cirugia.cirugia_id',
                'costos_cirugia.costo_total',
                'cirugias.fecha',
                'procedimientos_quirurgicos.id as procedimiento_id',
                'procedimientos_quirurgicos.codigo_cups',
                'procedimientos_quirurgicos.nombre as procedimiento_nombre',
            ])
            ->toBase()
            ->get();

        return $filas
            ->groupBy('procedimiento_id')
            ->map(fn (Collection $grupo) => $this->analizarGrupo($grupo))
            ->values();
    }

    /**
     * @param  Collection<int, \stdClass>  $grupo
     * @return array<string, mixed>
     */
    protected function analizarGrupo(Collection $grupo): array
    {
        $costos = $grupo->map(fn ($fila): float => (float) $fila->costo_total)->all();

        $media = Estadistica::media($costos);
        $desviacion = Estadistica::desviacionEstandar($costos);
        $q1 = Estadistica::percentil($costos, 0.25);
        $q3 = Estadistica::percentil($costos, 0.75);
        $iqr = $q3 - $q1;

        $limiteIqrInferior = $q1 - self::FACTOR_IQR * $iqr;
        $limiteIqrSuperior = $q3 + self::FACTOR_IQR * $iqr;

        $puntos = $grupo->map(function ($fila) use ($media, $desviacion, $limiteIqrInferior, $limiteIqrSuperior): array {
            $costo = (float) $fila->costo_total;
            $z = $desviacion > 0 ? ($costo - $media) / $desviacion : 0.0;

            $outlierZ = abs($z) > self::UMBRAL_Z;
            $outlierIqr = $costo < $limiteIqrInferior || $costo > $limiteIqrSuperior;

            return [
                'cirugia_id' => $fila->cirugia_id,
                'fecha' => $fila->fecha,
                'costo_total' => $costo,
                'z' => round($z, 3),
                'es_outlier' => $outlierZ || $outlierIqr,
                'criterios' => array_values(array_filter([
                    $outlierZ ? 'z' : null,
                    $outlierIqr ? 'iqr' : null,
                ])),
            ];
        })->values()->all();

        $primero = $grupo->first();

        return [
            'procedimiento' => [
                'id' => $primero->procedimiento_id,
                'codigo_cups' => $primero->codigo_cups,
                'nombre' => $primero->procedimiento_nombre,
            ],
            'n' => count($costos),
            'media' => round($media, 2),
            'desviacion' => round($desviacion, 2),
            'coeficiente_variacion' => $media > 0 ? round($desviacion / $media, 4) : null,
            'limites' => [
                'z_inferior' => round($media - self::UMBRAL_Z * $desviacion, 2),
                'z_superior' => round($media + self::UMBRAL_Z * $desviacion, 2),
                'iqr_inferior' => round($limiteIqrInferior, 2),
                'iqr_superior' => round($limiteIqrSuperior, 2),
            ],
            'total_outliers' => count(array_filter($puntos, fn (array $p): bool => $p['es_outlier'])),
            'puntos' => $puntos,
        ];
    }
}
