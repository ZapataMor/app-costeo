<?php

namespace App\Services\Indicators;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\SalaOperatoria;
use App\Support\Estadistica;
use App\Support\HospitalContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Indicadores de gestión del servicio de cirugía bajo el marco Donabedian:
 *  - Estructura: utilización de salas.
 *  - Proceso: costo promedio, variabilidad (CV), completitud de captura.
 *  - Resultado: margen costo vs. tarifa, glosas, recaudo.
 *
 * Todas las consultas quedan acotadas al hospital activo por el
 * HospitalScope global de los modelos.
 */
class KpiService
{
    /** Referencia tarifaria del modelo: SOAT − 25 %. */
    public const FACTOR_REFERENCIA_SOAT = 0.75;

    /**
     * Costo promedio por cirugía (global) y por procedimiento principal.
     *
     * @return array<string, mixed>
     */
    public function costos(): array
    {
        $costos = CostoCirugia::query()
            ->pluck('costo_total')
            ->map(fn ($v): float => (float) $v)
            ->all();

        $porProcedimiento = $this->costosPorProcedimiento()
            ->map(fn (array $grupo): array => [
                'procedimiento' => $grupo['procedimiento'],
                'n' => count($grupo['costos']),
                'costo_promedio' => round(Estadistica::media($grupo['costos']), 2),
                'costo_minimo' => $grupo['costos'] === [] ? null : round(min($grupo['costos']), 2),
                'costo_maximo' => $grupo['costos'] === [] ? null : round(max($grupo['costos']), 2),
            ])
            ->values()
            ->all();

        return [
            'global' => [
                'n_cirugias_costeadas' => count($costos),
                'costo_promedio' => round(Estadistica::media($costos), 2),
                'costo_minimo' => $costos !== [] ? round(min($costos), 2) : null,
                'costo_maximo' => $costos !== [] ? round(max($costos), 2) : null,
            ],
            'por_procedimiento' => $porProcedimiento,
        ];
    }

    /**
     * Coeficiente de variación de costos por procedimiento principal.
     *
     * @return array<string, mixed>
     */
    public function variabilidad(): array
    {
        $porProcedimiento = $this->costosPorProcedimiento()
            ->map(function (array $grupo): array {
                $cv = Estadistica::coeficienteVariacion($grupo['costos']);

                return [
                    'procedimiento' => $grupo['procedimiento'],
                    'n' => count($grupo['costos']),
                    'media' => round(Estadistica::media($grupo['costos']), 2),
                    'desviacion' => round(Estadistica::desviacionEstandar($grupo['costos']), 2),
                    'coeficiente_variacion' => $cv !== null ? round($cv, 4) : null,
                    'nivel_variabilidad' => $this->nivelVariabilidad($cv),
                ];
            })
            ->sortByDesc('coeficiente_variacion')
            ->values()
            ->all();

        return ['por_procedimiento' => $porProcedimiento];
    }

    /**
     * Margen por procedimiento: costo real vs. tarifa facturada y vs. la
     * referencia SOAT − 25 %.
     *
     * @return array<string, mixed>
     */
    public function margen(): array
    {
        $filas = $this->baseCostosPorProcedimiento()
            ->leftJoin('facturaciones', 'facturaciones.cirugia_id', '=', 'cirugias.id')
            ->groupBy(
                'procedimientos_quirurgicos.id',
                'procedimientos_quirurgicos.codigo_cups',
                'procedimientos_quirurgicos.nombre',
                'procedimientos_quirurgicos.tarifa_soat',
            )
            ->selectRaw(implode(', ', [
                'procedimientos_quirurgicos.id as procedimiento_id',
                'procedimientos_quirurgicos.codigo_cups',
                'procedimientos_quirurgicos.nombre as procedimiento_nombre',
                'procedimientos_quirurgicos.tarifa_soat',
                'count(*) as n',
                'avg(costos_cirugia.costo_total) as costo_promedio',
                'avg(facturaciones.valor_facturado) as facturado_promedio',
            ]))
            ->toBase()
            ->get();

        $porProcedimiento = $filas->map(function ($fila): array {
            $costo = round((float) $fila->costo_promedio, 2);
            $facturado = $fila->facturado_promedio !== null
                ? round((float) $fila->facturado_promedio, 2)
                : null;
            $tarifaSoat = $fila->tarifa_soat !== null ? (float) $fila->tarifa_soat : null;
            $referencia = $tarifaSoat !== null
                ? round($tarifaSoat * self::FACTOR_REFERENCIA_SOAT, 2)
                : null;

            return [
                'procedimiento' => [
                    'id' => $fila->procedimiento_id,
                    'codigo_cups' => $fila->codigo_cups,
                    'nombre' => $fila->procedimiento_nombre,
                ],
                'n' => (int) $fila->n,
                'costo_promedio' => $costo,
                'facturado_promedio' => $facturado,
                'tarifa_soat' => $tarifaSoat,
                'tarifa_referencia' => $referencia,
                'margen_vs_facturado' => $facturado !== null ? round($facturado - $costo, 2) : null,
                'margen_vs_facturado_pct' => $facturado !== null && $facturado > 0
                    ? round(($facturado - $costo) / $facturado, 4)
                    : null,
                'margen_vs_referencia' => $referencia !== null ? round($referencia - $costo, 2) : null,
                'margen_vs_referencia_pct' => $referencia !== null && $referencia > 0
                    ? round(($referencia - $costo) / $referencia, 4)
                    : null,
            ];
        })->values()->all();

        return [
            'factor_referencia_soat' => self::FACTOR_REFERENCIA_SOAT,
            'por_procedimiento' => $porProcedimiento,
        ];
    }

    /**
     * Utilización de salas (estructura): minutos operados ÷ minutos
     * disponibles en el mes. Si no se indica mes, usa el de la cirugía
     * realizada más reciente.
     *
     * @param  string|null  $mes  formato Y-m, p. ej. "2026-06"
     * @return array<string, mixed>
     */
    public function utilizacionSalas(?string $mes = null): array
    {
        // Con hospital activo, el denominador es su capacidad; en modo
        // consolidado (super_admin sin hospital) cada sala usa la capacidad
        // de SU hospital.
        $hospitalId = HospitalContext::id();

        $minutosPorHospital = Hospital::query()
            ->when($hospitalId !== null, fn ($query) => $query->whereKey($hospitalId))
            ->get()
            ->mapWithKeys(fn (Hospital $hospital): array => [
                $hospital->id => $hospital->minutosDisponiblesMes(),
            ]);

        if ($mes === null) {
            $ultimaFecha = Cirugia::where('estado', 'realizada')->max('fecha');
            $mes = $ultimaFecha !== null
                ? Carbon::parse($ultimaFecha)->format('Y-m')
                : now()->format('Y-m');
        }

        $inicio = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $cirugias = Cirugia::query()
            ->where('estado', 'realizada')
            ->whereNotNull('sala_operatoria_id')
            ->whereNotNull('hora_fin')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->get(['id', 'sala_operatoria_id', 'hora_inicio', 'hora_fin']);

        $minutosPorSala = $cirugias
            ->groupBy('sala_operatoria_id')
            ->map(fn ($grupo) => (int) $grupo->sum(fn (Cirugia $c): int => $c->duracionMinutos() ?? 0));

        $totalDisponibles = 0;

        $salas = SalaOperatoria::orderBy('nombre')->get()->map(function (SalaOperatoria $sala) use ($minutosPorSala, $minutosPorHospital, &$totalDisponibles): array {
            $usados = $minutosPorSala->get($sala->id, 0);
            $minutosDisponibles = (int) $minutosPorHospital->get($sala->hospital_id, 0);
            $totalDisponibles += $minutosDisponibles;

            return [
                'sala' => ['id' => $sala->id, 'nombre' => $sala->nombre],
                'minutos_usados' => $usados,
                'minutos_disponibles' => $minutosDisponibles,
                'utilizacion_pct' => $minutosDisponibles > 0
                    ? round($usados / $minutosDisponibles, 4)
                    : null,
            ];
        })->values()->all();

        $totalUsados = (int) $minutosPorSala->sum();

        return [
            'mes' => $mes,
            'global' => [
                'minutos_usados' => $totalUsados,
                'minutos_disponibles' => $totalDisponibles,
                'utilizacion_pct' => $totalDisponibles > 0
                    ? round($totalUsados / $totalDisponibles, 4)
                    : null,
            ],
            'por_sala' => $salas,
        ];
    }

    /**
     * Tasa de glosas y de recaudo sobre lo facturado (resultado financiero).
     *
     * @return array<string, mixed>
     */
    public function glosasRecaudo(): array
    {
        $totales = Facturacion::query()
            ->selectRaw('count(*) as n')
            ->selectRaw('coalesce(sum(valor_facturado), 0) as facturado')
            ->selectRaw('coalesce(sum(valor_glosado), 0) as glosado')
            ->selectRaw('coalesce(sum(valor_recaudado), 0) as recaudado')
            ->toBase()
            ->first();

        $facturado = (float) ($totales->facturado ?? 0);
        $glosado = (float) ($totales->glosado ?? 0);
        $recaudado = (float) ($totales->recaudado ?? 0);

        return [
            'n_facturas' => (int) ($totales->n ?? 0),
            'valor_facturado' => round($facturado, 2),
            'valor_glosado' => round($glosado, 2),
            'valor_recaudado' => round($recaudado, 2),
            'tasa_glosas' => $facturado > 0 ? round($glosado / $facturado, 4) : null,
            'tasa_recaudo' => $facturado > 0 ? round($recaudado / $facturado, 4) : null,
        ];
    }

    /**
     * Completitud de captura: % de cirugías realizadas con cada bloque de
     * información registrado (para el dashboard de supervisión).
     *
     * @return array<string, mixed>
     */
    public function completitud(): array
    {
        $base = fn () => Cirugia::query()->where('estado', 'realizada');

        $total = $base()->count();

        $chequeos = [
            'equipo_quirurgico' => $base()->whereHas('equipoQuirurgico')->count(),
            'consumo_insumos' => $base()->whereHas('consumos')->count(),
            'costo_calculado' => $base()->whereHas('costo')->count(),
            'resultado_clinico' => $base()->whereHas('resultadoClinico')->count(),
            'facturacion' => $base()->whereHas('facturacion')->count(),
        ];

        $completas = $base()
            ->whereHas('equipoQuirurgico')
            ->whereHas('consumos')
            ->whereHas('costo')
            ->whereHas('resultadoClinico')
            ->whereHas('facturacion')
            ->count();

        return [
            'total_cirugias_realizadas' => $total,
            'chequeos' => collect($chequeos)->map(fn (int $n, string $clave): array => [
                'registradas' => $n,
                'porcentaje' => $total > 0 ? round($n / $total, 4) : null,
            ])->all(),
            'completas' => $completas,
            'completitud_global' => $total > 0 ? round($completas / $total, 4) : null,
        ];
    }

    /**
     * Costo promedio por componente (RRHH, sala, equipos, insumos,
     * indirectos) por procedimiento, para el dashboard de composición.
     *
     * @return array<string, mixed>
     */
    public function costoPorComponente(): array
    {
        $filas = $this->baseCostosPorProcedimiento()
            ->groupBy(
                'procedimientos_quirurgicos.id',
                'procedimientos_quirurgicos.codigo_cups',
                'procedimientos_quirurgicos.nombre',
            )
            ->selectRaw(implode(', ', [
                'procedimientos_quirurgicos.id as procedimiento_id',
                'procedimientos_quirurgicos.codigo_cups',
                'procedimientos_quirurgicos.nombre as procedimiento_nombre',
                'count(*) as n',
                'avg(costos_cirugia.costo_recurso_humano) as recurso_humano',
                'avg(costos_cirugia.costo_sala) as sala',
                'avg(costos_cirugia.costo_equipos) as equipos',
                'avg(costos_cirugia.costo_insumos) as insumos',
                'avg(costos_cirugia.costo_indirecto) as indirectos',
                'avg(costos_cirugia.costo_total) as total',
            ]))
            ->toBase()
            ->get();

        return [
            'por_procedimiento' => $filas->map(fn ($fila): array => [
                'procedimiento' => [
                    'id' => $fila->procedimiento_id,
                    'codigo_cups' => $fila->codigo_cups,
                    'nombre' => $fila->procedimiento_nombre,
                ],
                'n' => (int) $fila->n,
                'recurso_humano' => round((float) $fila->recurso_humano, 2),
                'sala' => round((float) $fila->sala, 2),
                'equipos' => round((float) $fila->equipos, 2),
                'insumos' => round((float) $fila->insumos, 2),
                'indirectos' => round((float) $fila->indirectos, 2),
                'total' => round((float) $fila->total, 2),
            ])->values()->all(),
        ];
    }

    protected function nivelVariabilidad(?float $cv): ?string
    {
        if ($cv === null) {
            return null;
        }

        return match (true) {
            $cv > 0.30 => 'alta',
            $cv > 0.15 => 'media',
            default => 'baja',
        };
    }

    /**
     * Costos totales agrupados por procedimiento principal.
     *
     * @return Collection<int, array{procedimiento: array{id: mixed, codigo_cups: mixed, nombre: mixed}, costos: list<float>}>
     */
    protected function costosPorProcedimiento(): Collection
    {
        $filas = $this->baseCostosPorProcedimiento()
            ->select([
                'costos_cirugia.costo_total',
                'procedimientos_quirurgicos.id as procedimiento_id',
                'procedimientos_quirurgicos.codigo_cups',
                'procedimientos_quirurgicos.nombre as procedimiento_nombre',
            ])
            ->toBase()
            ->get();

        return $filas
            ->groupBy('procedimiento_id')
            ->map(fn ($grupo): array => [
                'procedimiento' => [
                    'id' => $grupo->first()->procedimiento_id,
                    'codigo_cups' => $grupo->first()->codigo_cups,
                    'nombre' => $grupo->first()->procedimiento_nombre,
                ],
                'costos' => array_values($grupo->map(fn ($f): float => (float) $f->costo_total)->all()),
            ])
            ->values();
    }

    /**
     * Join base: costo de cirugía → cirugía → procedimiento principal.
     * El HospitalScope de CostoCirugia acota todo al hospital activo.
     *
     * @return Builder<CostoCirugia>
     */
    protected function baseCostosPorProcedimiento(): Builder
    {
        return CostoCirugia::query()
            ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')
                    ->where('cirugia_procedimiento.es_principal', true);
            })
            ->join(
                'procedimientos_quirurgicos',
                'procedimientos_quirurgicos.id',
                '=',
                'cirugia_procedimiento.procedimiento_quirurgico_id',
            );
    }
}
