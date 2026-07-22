<?php

namespace App\Services\Costing;

use App\Enums\EstadoCirugia;
use App\Models\CostoCirugia;
use App\Support\Estadistica;
use App\Support\Periodo;
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

    /** Ventana temporal del análisis; vacía = toda la historia. */
    protected Periodo $periodo;

    public function __construct()
    {
        $this->periodo = new Periodo;
    }

    public function enPeriodo(Periodo $periodo): static
    {
        $this->periodo = $periodo;

        return $this;
    }

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
            ->when(
                $this->periodo->desde !== null,
                fn ($query) => $query->whereDate('cirugias.fecha', '>=', $this->periodo->desde->toDateString()),
            )
            ->when(
                $this->periodo->hasta !== null,
                fn ($query) => $query->whereDate('cirugias.fecha', '<=', $this->periodo->hasta->toDateString()),
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

        // Bigotes de Tukey: el valor real más extremo que sigue dentro de los
        // límites, no el límite en sí. Es lo que dibuja la caja.
        $dentro = array_values(array_filter(
            $costos,
            fn (float $c): bool => $c >= $limiteIqrInferior && $c <= $limiteIqrSuperior,
        ));

        // Un grupo sin filas no existe —se agrupa por procedimiento sobre las
        // que hay—, pero la caja se calcula sin suponerlo.
        $minimo = $costos === [] ? 0.0 : min($costos);
        $maximo = $costos === [] ? 0.0 : max($costos);
        $extremos = $dentro === [] ? $costos : $dentro;
        $bigoteInferior = $extremos === [] ? $minimo : min($extremos);
        $bigoteSuperior = $extremos === [] ? $maximo : max($extremos);

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
            'caja' => [
                'minimo' => round($minimo, 2),
                'bigote_inferior' => round($bigoteInferior, 2),
                'q1' => round($q1, 2),
                'mediana' => round(Estadistica::percentil($costos, 0.5), 2),
                'q3' => round($q3, 2),
                'bigote_superior' => round($bigoteSuperior, 2),
                'maximo' => round($maximo, 2),
            ],
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
