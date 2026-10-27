<?php

namespace App\Services\Indicators;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Enums\ClasificacionDesviacion;
use App\Enums\EstadoCirugia;
use App\Enums\OrigenComponente;
use App\Models\Cirugia;
use App\Models\CirugiaConceptoIndirecto;
use App\Models\ConceptoCostoIndirecto;
use App\Models\CostoCirugia;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\SalaOperatoria;
use App\Support\Estadistica;
use App\Support\HospitalContext;
use App\Support\Periodo;
use Carbon\CarbonInterface;
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

    /** Ventana temporal aplicada a todos los indicadores. */
    protected Periodo $periodo;

    public function __construct()
    {
        $this->periodo = new Periodo;
    }

    /**
     * Acota los indicadores a una ventana temporal. Sin llamarla, el
     * servicio calcula sobre toda la historia (comportamiento por defecto).
     */
    public function enPeriodo(Periodo $periodo): static
    {
        $this->periodo = $periodo;

        return $this;
    }

    public function periodo(): Periodo
    {
        return $this->periodo;
    }

    /**
     * Costo promedio por cirugía (global) y por procedimiento principal.
     *
     * @return array{global: array<string, int|float|null>, por_procedimiento: list<array<string, mixed>>}
     */
    public function costos(): array
    {
        $costos = $this->baseCostosContabilizables()
            ->pluck('costos_cirugia.costo_total')
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
        $porProcedimiento = array_values($porProcedimiento);

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
     * @return array{por_procedimiento: list<array<string, mixed>>}
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

        return ['por_procedimiento' => array_values($porProcedimiento)];
    }

    /**
     * Margen por procedimiento: costo real vs. tarifa facturada y vs. la
     * referencia SOAT − 25 %. Solo compara cirugías facturadas: mezclar
     * costeadas-sin-factura con facturadas compararía poblaciones distintas.
     *
     * @return array{factor_referencia_soat: float, por_procedimiento: list<array<string, mixed>>}
     */
    public function margen(): array
    {
        $filas = $this->baseCostosPorProcedimiento()
            ->join('facturaciones', 'facturaciones.cirugia_id', '=', 'cirugias.id')
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
            'por_procedimiento' => array_values($porProcedimiento),
        ];
    }

    /**
     * Resultado del periodo en pesos: lo facturado menos el costo real de
     * las mismas cirugías. `margen()` promedia por procedimiento; la portada
     * necesita el total, que es lo que se gana o se pierde en el mes.
     *
     * @return array{n: int, costo: float, facturado: float, margen: float, margen_pct: float|null}
     */
    public function resultado(): array
    {
        $fila = $this->baseCostosContabilizables()
            ->join('facturaciones', 'facturaciones.cirugia_id', '=', 'cirugias.id')
            ->selectRaw('count(*) as n')
            ->selectRaw('coalesce(sum(costos_cirugia.costo_total), 0) as costo')
            ->selectRaw('coalesce(sum(facturaciones.valor_facturado), 0) as facturado')
            ->toBase()
            ->first();

        $costo = (float) ($fila->costo ?? 0);
        $facturado = (float) ($fila->facturado ?? 0);

        return [
            'n' => (int) ($fila->n ?? 0),
            'costo' => round($costo, 2),
            'facturado' => round($facturado, 2),
            'margen' => round($facturado - $costo, 2),
            'margen_pct' => $facturado > 0 ? round(($facturado - $costo) / $facturado, 4) : null,
        ];
    }

    /**
     * Costo real frente al costo estándar (lo que debió costar según el
     * protocolo) de las cirugías del periodo que tienen estándar.
     *
     * La desviación es agregada —Σ real ÷ Σ estándar − 1—, no el promedio de
     * los porcentajes: una cirugía barata desviada un 80 % no debe pesar lo
     * mismo que una cara desviada un 80 %.
     *
     * @return array{n: int, n_costeadas: int, costo_real: float, costo_estandar: float, desviacion: float, desviacion_pct: float|null, por_clasificacion: array<string, int>}
     */
    public function desviacionEstandar(): array
    {
        $fila = $this->baseCostosContabilizables()
            ->whereNotNull('costos_cirugia.costo_estandar')
            ->selectRaw('count(*) as n')
            ->selectRaw('coalesce(sum(costos_cirugia.costo_total), 0) as costo_real')
            ->selectRaw('coalesce(sum(costos_cirugia.costo_estandar), 0) as costo_estandar')
            ->toBase()
            ->first();

        $conteos = $this->baseCostosContabilizables()
            ->whereNotNull('costos_cirugia.clasificacion_desviacion')
            ->groupBy('costos_cirugia.clasificacion_desviacion')
            ->selectRaw('costos_cirugia.clasificacion_desviacion as clasificacion, count(*) as n')
            ->toBase()
            ->pluck('n', 'clasificacion');

        $real = (float) ($fila->costo_real ?? 0);
        $estandar = (float) ($fila->costo_estandar ?? 0);

        return [
            'n' => (int) ($fila->n ?? 0),
            'n_costeadas' => $this->baseCostosContabilizables()->count(),
            'costo_real' => round($real, 2),
            'costo_estandar' => round($estandar, 2),
            'desviacion' => round($real - $estandar, 2),
            'desviacion_pct' => $estandar > 0 ? round(($real - $estandar) / $estandar, 4) : null,
            'por_clasificacion' => collect(ClasificacionDesviacion::values())
                ->mapWithKeys(fn (string $clave): array => [$clave => (int) ($conteos[$clave] ?? 0)])
                ->all(),
        ];
    }

    /**
     * Variación del costo **a igual mezcla de procedimientos** frente a otro
     * periodo: Σ n·promedio_actual ÷ Σ n·promedio_anterior − 1, con las
     * cantidades del periodo actual y solo los procedimientos presentes en
     * ambos.
     *
     * El costo promedio por cirugía sube si un mes se operan más
     * colecistectomías que cesáreas aunque ningún procedimiento haya
     * encarecido; este índice aísla el cambio de costo del cambio de mezcla.
     *
     * @return array{variacion: float|null, cirugias_comparadas: int, cirugias_total: int}
     */
    public function variacionAMezclaConstante(Periodo $anterior): array
    {
        $actual = $this->costosPorProcedimiento()->keyBy(fn (array $g): int => (int) $g['procedimiento']['id']);
        $previo = (clone $this)->enPeriodo($anterior)->costosPorProcedimiento()
            ->keyBy(fn (array $g): int => (int) $g['procedimiento']['id']);

        $ahora = 0.0;
        $antes = 0.0;
        $comparadas = 0;

        foreach ($actual as $id => $grupo) {
            $anteriorDelProcedimiento = $previo->get($id);

            if ($anteriorDelProcedimiento === null) {
                continue;
            }

            $n = count($grupo['costos']);
            $comparadas += $n;
            $ahora += $n * Estadistica::media($grupo['costos']);
            $antes += $n * Estadistica::media($anteriorDelProcedimiento['costos']);
        }

        return [
            'variacion' => $antes > 0 ? round($ahora / $antes - 1, 4) : null,
            'cirugias_comparadas' => $comparadas,
            'cirugias_total' => (int) $actual->sum(fn (array $g): int => count($g['costos'])),
        ];
    }

    /**
     * Desviación frente al estándar por procedimiento principal, con el mismo
     * criterio agregado de `desviacionEstandar()`.
     *
     * @return array<int, array{n: int, desviacion: float, desviacion_pct: float|null}>
     */
    public function desviacionPorProcedimiento(): array
    {
        return $this->baseCostosPorProcedimiento()
            ->whereNotNull('costos_cirugia.costo_estandar')
            ->groupBy('procedimientos_quirurgicos.id')
            ->selectRaw('procedimientos_quirurgicos.id as procedimiento_id')
            ->selectRaw('count(*) as n')
            ->selectRaw('sum(costos_cirugia.costo_total) as costo_real')
            ->selectRaw('sum(costos_cirugia.costo_estandar) as costo_estandar')
            ->toBase()
            ->get()
            ->mapWithKeys(function ($fila): array {
                $real = (float) $fila->costo_real;
                $estandar = (float) $fila->costo_estandar;

                return [(int) $fila->procedimiento_id => [
                    'n' => (int) $fila->n,
                    'desviacion' => round($real - $estandar, 2),
                    'desviacion_pct' => $estandar > 0 ? round(($real - $estandar) / $estandar, 4) : null,
                ]];
            })
            ->all();
    }

    /**
     * Capacidad no utilizada valorada en pesos (backlog #2): el indicador
     * gerencial propio del TDABC. Dos fuentes que se reportan por separado:
     *
     *  - **Salas**: minutos disponibles sin cirugía × costo/hora de la sala.
     *    Solo cuando el costo de sala es digitado; si sale de la bolsa de
     *    infraestructura, esa ociosidad ya está en la segunda fuente.
     *  - **Bolsas CIF**: lo que suman las bolsas mensuales (por minuto de
     *    quirófano o de personal) en la ventana, menos lo que absorbieron las
     *    cirugías. Las de porcentaje sobre el directo no tienen monto fijo y
     *    quedan fuera.
     *
     * @return array<string, mixed>
     */
    public function capacidadNoUtilizada(): array
    {
        $utilizacion = $this->utilizacionSalas();
        $inicio = Carbon::parse($utilizacion['ventana']['desde'])->startOfDay();
        $fin = Carbon::parse($utilizacion['ventana']['hasta'])->endOfDay();

        $salas = SalaOperatoria::query()->with('hospital')->get()->keyBy('id');

        $porSala = array_values(array_map(function (array $fila) use ($salas): array {
            /** @var SalaOperatoria|null $sala */
            $sala = $salas->get($fila['sala']['id']);
            $ociosos = max(0, $fila['minutos_disponibles'] - $fila['minutos_usados']);
            $derivada = $sala?->hospital?->origenDe(CategoriaCif::Infraestructura) === OrigenComponente::DerivadoDeCif;

            return [
                'sala' => $fila['sala'],
                'minutos_usados' => $fila['minutos_usados'],
                'minutos_disponibles' => $fila['minutos_disponibles'],
                'minutos_ociosos' => $ociosos,
                'utilizacion_pct' => $fila['utilizacion_pct'],
                'costo_derivado_de_cif' => $derivada,
                'valor_ocioso' => $sala === null || $derivada
                    ? null
                    : round($ociosos * (float) $sala->costo_hora / 60, 2),
            ];
        }, $utilizacion['por_sala']));

        $bolsas = $this->bolsasEnVentana($inicio, $fin);

        $absorbido = (float) $this->acotarAlPeriodo(
            CirugiaConceptoIndirecto::query()
                ->join('cirugias', 'cirugias.id', '=', 'cirugia_concepto_indirecto.cirugia_id')
                ->where('cirugias.estado', EstadoCirugia::Realizada->value)
                ->whereNotNull('cirugias.hora_fin')
                ->where('cirugia_concepto_indirecto.base_asignacion_registrada', '!=', BaseAsignacionCif::PorcentajeDirecto->value),
        )->sum('cirugia_concepto_indirecto.monto_asignado');

        $valorSalas = array_sum(array_map(fn (array $f): float => (float) ($f['valor_ocioso'] ?? 0), $porSala));
        $noAbsorbido = max(0.0, $bolsas - $absorbido);

        return [
            'ventana' => $utilizacion['ventana'],
            'salas' => [
                'minutos_ociosos' => (int) array_sum(array_column($porSala, 'minutos_ociosos')),
                'valor_ocioso' => round($valorSalas, 2),
                'por_sala' => $porSala,
            ],
            'bolsas' => [
                'monto' => round($bolsas, 2),
                'absorbido' => round($absorbido, 2),
                'no_absorbido' => round($noAbsorbido, 2),
                'absorcion_pct' => $bolsas > 0 ? round($absorbido / $bolsas, 4) : null,
            ],
            'total' => round($valorSalas + $noAbsorbido, 2),
        ];
    }

    /**
     * Monto de las bolsas CIF de monto mensual en una ventana: cada mes pesa
     * por los días de la ventana que caen en él, y cada bolsa solo cuenta en
     * los días en que estuvo vigente.
     */
    protected function bolsasEnVentana(CarbonInterface $inicio, CarbonInterface $fin): float
    {
        $conceptos = ConceptoCostoIndirecto::query()
            ->where('activo', true)
            ->whereNotNull('monto_mensual')
            ->where('base_asignacion', '!=', BaseAsignacionCif::PorcentajeDirecto->value)
            ->whereDate('vigente_desde', '<=', $fin->toDateString())
            ->where(fn ($q) => $q->whereNull('vigente_hasta')
                ->orWhereDate('vigente_hasta', '>=', $inicio->toDateString()))
            ->get();

        $total = 0.0;

        foreach ($conceptos as $concepto) {
            $desde = Carbon::parse($concepto->vigente_desde)->startOfDay()->max(Carbon::parse($inicio)->startOfDay());
            $hasta = $concepto->vigente_hasta !== null
                ? Carbon::parse($concepto->vigente_hasta)->startOfDay()->min(Carbon::parse($fin)->startOfDay())
                : Carbon::parse($fin)->startOfDay();

            $mes = $desde->copy()->startOfMonth();

            while ($mes->lessThanOrEqualTo($hasta)) {
                $tramoDesde = $desde->max($mes);
                $tramoHasta = $hasta->min($mes->copy()->endOfMonth()->startOfDay());
                $dias = (int) $tramoDesde->diffInDays($tramoHasta) + 1;

                if ($dias > 0) {
                    $total += (float) $concepto->monto_mensual * $dias / $mes->daysInMonth;
                }

                $mes = $mes->copy()->addMonthNoOverflow()->startOfMonth();
            }
        }

        return $total;
    }

    /**
     * Utilización de salas (estructura): minutos operados ÷ minutos
     * disponibles en la ventana analizada.
     *
     * La ventana es, en orden: el mes pedido explícitamente (API), el periodo
     * activo del servicio, o el mes de la cirugía realizada más reciente.
     * Antes solo existía la tercera opción, así que el panel mostraba un mes
     * suelto mientras el resto de la página decía «toda la historia» y el
     * número parecía roto.
     *
     * @param  string|null  $mes  formato Y-m, p. ej. "2026-06"
     * @return array<string, mixed>
     */
    public function utilizacionSalas(?string $mes = null): array
    {
        [$inicio, $fin, $etiqueta, $mesResuelto] = $this->ventanaUtilizacion($mes);

        // `diffInDays` devuelve float: del 22/04 00:00 al 21/07 23:59 da
        // 90,99…, y sin truncar la ventana se rotulaba «91,99999 días».
        $dias = (int) $inicio->diffInDays($fin) + 1;

        // Con hospital activo, el denominador es su capacidad; en modo
        // consolidado (super_admin sin hospital) cada sala usa la capacidad
        // de SU hospital. La ventana completa manda sobre el mes: comparar un
        // trimestre contra la capacidad de un mes triplicaba el error.
        $hospitalId = HospitalContext::id();

        $minutosPorHospital = Hospital::query()
            ->when($hospitalId !== null, fn ($query) => $query->whereKey($hospitalId))
            ->get()
            ->mapWithKeys(fn (Hospital $hospital): array => [
                $hospital->id => $hospital->minutosDisponiblesEntre($inicio, $fin),
            ]);

        $cirugias = Cirugia::query()
            ->where('estado', 'realizada')
            ->whereNotNull('sala_operatoria_id')
            ->whereNotNull('hora_fin')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->get(['id', 'sala_operatoria_id', 'hora_inicio', 'hora_fin']);

        $minutosPorSala = $cirugias
            ->groupBy('sala_operatoria_id')
            ->map(fn ($grupo) => (int) $grupo->sum(fn (Cirugia $c): int => $c->duracionMinutos() ?? 0));

        $cirugiasPorSala = $cirugias->groupBy('sala_operatoria_id')->map->count();

        $totalDisponibles = 0;

        $salas = SalaOperatoria::orderBy('nombre')->get()->map(function (SalaOperatoria $sala) use ($minutosPorSala, $cirugiasPorSala, $minutosPorHospital, &$totalDisponibles): array {
            $usados = $minutosPorSala->get($sala->id, 0);
            $minutosDisponibles = (int) $minutosPorHospital->get($sala->hospital_id, 0);
            $totalDisponibles += $minutosDisponibles;

            return [
                'sala' => ['id' => $sala->id, 'nombre' => $sala->nombre],
                'n_cirugias' => (int) $cirugiasPorSala->get($sala->id, 0),
                'minutos_usados' => $usados,
                'minutos_disponibles' => $minutosDisponibles,
                'utilizacion_pct' => $minutosDisponibles > 0
                    ? round($usados / $minutosDisponibles, 4)
                    : null,
            ];
        })->values()->all();

        $totalUsados = (int) $minutosPorSala->sum();

        return [
            'mes' => $mesResuelto,
            'ventana' => [
                'desde' => $inicio->toDateString(),
                'hasta' => $fin->toDateString(),
                'dias' => $dias,
                'etiqueta' => $etiqueta,
            ],
            'global' => [
                'n_cirugias' => $cirugias->count(),
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
     * Resuelve la ventana de la utilización de salas.
     *
     * Las fechas se tipan como `CarbonInterface`: el periodo trae instancias
     * mutables y `now()` inmutables, y aquí solo se leen.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: string, 3: string}
     */
    protected function ventanaUtilizacion(?string $mes): array
    {
        // Mes explícito: la API lo usa para pedir un mes concreto.
        if ($mes !== null) {
            $inicio = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
            $fin = $inicio->copy()->endOfMonth();

            return [$inicio, $fin, $inicio->translatedFormat('F Y'), $mes];
        }

        // Periodo activo del panel: manda sobre cualquier valor por defecto.
        if (! $this->periodo->vacio()) {
            $inicio = $this->periodo->desde ?? Carbon::parse(
                Cirugia::where('estado', 'realizada')->min('fecha') ?? now()
            )->startOfDay();
            $fin = $this->periodo->hasta ?? now()->endOfDay();

            return [
                $inicio->copy()->startOfDay(),
                $fin->copy()->endOfDay(),
                $this->periodo->etiqueta(),
                $inicio->format('Y-m'),
            ];
        }

        // Sin periodo: toda la historia registrada, para no contradecir al
        // resto del panel con un mes suelto.
        $primera = Cirugia::where('estado', 'realizada')->min('fecha');
        $ultima = Cirugia::where('estado', 'realizada')->max('fecha');

        if ($primera === null || $ultima === null) {
            $inicio = now()->startOfMonth();

            return [$inicio, now()->endOfMonth(), 'Toda la historia', $inicio->format('Y-m')];
        }

        $inicio = Carbon::parse($primera)->startOfDay();
        $fin = Carbon::parse($ultima)->endOfDay();

        return [$inicio, $fin, 'Toda la historia', $fin->format('Y-m')];
    }

    /**
     * Tasa de glosas y de recaudo sobre lo facturado (resultado financiero).
     *
     * @return array<string, mixed>
     */
    public function glosasRecaudo(): array
    {
        $totales = $this->acotarAlPeriodo(
            Facturacion::query()
                ->join('cirugias', 'cirugias.id', '=', 'facturaciones.cirugia_id'),
        )
            ->selectRaw('count(*) as n')
            ->selectRaw('coalesce(sum(facturaciones.valor_facturado), 0) as facturado')
            ->selectRaw('coalesce(sum(facturaciones.valor_glosado), 0) as glosado')
            ->selectRaw('coalesce(sum(facturaciones.valor_recaudado), 0) as recaudado')
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
        $base = fn () => $this->acotarAlPeriodo(
            Cirugia::query()->where('estado', 'realizada'),
        );

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

    /**
     * Evolución mensual del costo: promedio por cirugía, volumen y gasto
     * total de cada mes, más el desglose por componente.
     *
     * Es la única vista que responde «¿estamos mejorando?»: sin serie
     * temporal, todos los demás indicadores son una foto sin antes ni después.
     *
     * @return array<string, mixed>
     */
    public function tendenciaMensual(): array
    {
        $filas = $this->baseCostosContabilizables()
            ->leftJoin('facturaciones', 'facturaciones.cirugia_id', '=', 'cirugias.id')
            ->select([
                'facturaciones.valor_facturado',
                'costos_cirugia.costo_total',
                'costos_cirugia.costo_recurso_humano',
                'costos_cirugia.costo_sala',
                'costos_cirugia.costo_equipos',
                'costos_cirugia.costo_insumos',
                'costos_cirugia.costo_indirecto',
                'cirugias.fecha',
            ])
            ->toBase()
            ->get()
            ->filter(fn ($fila): bool => $fila->fecha !== null);

        $meses = $filas
            ->groupBy(fn ($fila): string => Carbon::parse($fila->fecha)->format('Y-m'))
            ->map(function (Collection $grupo, string $mes): array {
                $costos = $grupo->map(fn ($f): float => (float) $f->costo_total)->all();
                $n = count($costos);

                // Resultado del mes solo sobre las cirugías ya facturadas:
                // restar el costo de las que aún no tienen factura inventaría
                // una pérdida que todavía no existe.
                $facturadas = $grupo->filter(fn ($f): bool => $f->valor_facturado !== null);
                $facturado = (float) $facturadas->sum(fn ($f): float => (float) $f->valor_facturado);
                $costoFacturadas = (float) $facturadas->sum(fn ($f): float => (float) $f->costo_total);

                return [
                    'mes' => $mes,
                    'etiqueta' => Carbon::createFromFormat('Y-m', $mes)
                        ->translatedFormat('M Y'),
                    'n' => $n,
                    'costo_promedio' => round(Estadistica::media($costos), 2),
                    'costo_total' => round(array_sum($costos), 2),
                    'n_facturadas' => $facturadas->count(),
                    'facturado' => round($facturado, 2),
                    'costo_facturadas' => round($costoFacturadas, 2),
                    'margen' => round($facturado - $costoFacturadas, 2),
                    'margen_pct' => $facturado > 0 ? round(($facturado - $costoFacturadas) / $facturado, 4) : null,
                    'recurso_humano' => $this->promedioDe($grupo, 'costo_recurso_humano'),
                    'sala' => $this->promedioDe($grupo, 'costo_sala'),
                    'equipos' => $this->promedioDe($grupo, 'costo_equipos'),
                    'insumos' => $this->promedioDe($grupo, 'costo_insumos'),
                    'indirectos' => $this->promedioDe($grupo, 'costo_indirecto'),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        // Variación del último mes contra el anterior: el titular de la
        // gráfica, para no obligar a leer dos puntos y restarlos de cabeza.
        $n = count($meses);
        $ultimo = $n > 0 ? $meses[$n - 1] : null;
        $previo = $n > 1 ? $meses[$n - 2] : null;

        $variacion = null;

        if ($ultimo !== null && $previo !== null && $previo['costo_promedio'] > 0) {
            $variacion = round(
                ($ultimo['costo_promedio'] - $previo['costo_promedio']) / $previo['costo_promedio'],
                4,
            );
        }

        return [
            'meses' => $meses,
            'ultimo' => $ultimo,
            'variacion_ultimo_mes' => $variacion,
        ];
    }

    /**
     * @param  Collection<int, \stdClass>  $grupo
     */
    protected function promedioDe(Collection $grupo, string $columna): float
    {
        return round(
            Estadistica::media($grupo->map(fn ($f): float => (float) $f->{$columna})->all()),
            2,
        );
    }

    public function nivelVariabilidad(?float $cv): ?string
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
     * Costos contabilizables: solo cirugías realizadas y con hora de fin.
     * Las programadas, en proceso o canceladas —y las realizadas sin
     * marcar como terminadas— no deben sesgar los indicadores.
     * El HospitalScope de CostoCirugia acota todo al hospital activo.
     *
     * @return Builder<CostoCirugia>
     */
    protected function baseCostosContabilizables(): Builder
    {
        return $this->acotarAlPeriodo(
            CostoCirugia::query()
                ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
                ->where('cirugias.estado', EstadoCirugia::Realizada->value)
                ->whereNotNull('cirugias.hora_fin'),
        );
    }

    /**
     * Aplica la ventana temporal sobre `cirugias.fecha`. La tabla `cirugias`
     * debe estar en la consulta (por join o por ser la tabla base).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $consulta
     * @return Builder<TModel>
     */
    protected function acotarAlPeriodo(Builder $consulta): Builder
    {
        if ($this->periodo->desde !== null) {
            $consulta->whereDate('cirugias.fecha', '>=', $this->periodo->desde->toDateString());
        }

        if ($this->periodo->hasta !== null) {
            $consulta->whereDate('cirugias.fecha', '<=', $this->periodo->hasta->toDateString());
        }

        return $consulta;
    }

    /**
     * Join base: costo contabilizable → cirugía → procedimiento principal.
     *
     * @return Builder<CostoCirugia>
     */
    protected function baseCostosPorProcedimiento(): Builder
    {
        return $this->baseCostosContabilizables()
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
