<?php

namespace App\Http\Controllers\Costeo;

use App\Enums\EstadoCirugia;
use App\Enums\NivelComplejidad;
use App\Http\Controllers\Controller;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\ProcedimientoQuirurgico;
use App\Services\Cirugias\PresentarCirugiaDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Explorador de procedimientos del módulo Costeo: catálogo filtrable de
 * procedimientos del hospital → cirugías realizadas de cada uno → detalle
 * completo de la cirugía con su costo TDABC ítem por ítem.
 *
 * Cada cirugía se agrupa bajo su procedimiento principal, el mismo
 * criterio de los KPIs (KpiService), para que los números coincidan
 * entre pantallas. El HospitalScope acota todo al hospital activo.
 */
class ProcedimientoCosteoController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = [
            'q' => trim((string) $request->query('q', '')),
            'especialidad' => (string) $request->query('especialidad', ''),
            'complejidad' => (string) $request->query('complejidad', ''),
        ];

        $procedimientos = ProcedimientoQuirurgico::query()
            ->when($filtros['q'] !== '', fn ($query) => $query->where(
                fn ($sub) => $sub
                    ->where('nombre', 'like', "%{$filtros['q']}%")
                    ->orWhere('codigo_cups', 'like', "%{$filtros['q']}%"),
            ))
            ->when($filtros['especialidad'] !== '', fn ($query) => $query
                ->where('especialidad', $filtros['especialidad']))
            ->when($filtros['complejidad'] !== '', fn ($query) => $query
                ->where('complejidad', $filtros['complejidad']))
            ->withCount([
                'cirugias as n_realizadas' => fn ($query) => $query
                    ->where('cirugias.estado', EstadoCirugia::Realizada->value)
                    ->where('cirugia_procedimiento.es_principal', true),
            ])
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        $stats = $this->estadisticasDeCostos(array_values(array_map(
            fn (ProcedimientoQuirurgico $p): int => $p->id,
            $procedimientos->items(),
        )));

        $procedimientos->through(fn (ProcedimientoQuirurgico $p): array => [
            'id' => $p->id,
            'codigo_cups' => $p->codigo_cups,
            'nombre' => $p->nombre,
            'especialidad' => $p->especialidad,
            'complejidad' => $p->complejidad,
            'duracion_estimada_minutos' => $p->duracion_estimada_minutos,
            'n_realizadas' => (int) $p->getAttribute('n_realizadas'),
            'n_costeadas' => (int) ($stats[$p->id]->n ?? 0),
            'costo_promedio' => isset($stats[$p->id])
                ? round((float) $stats[$p->id]->costo_promedio, 2)
                : null,
        ]);

        return Inertia::render('costeo/procedimientos/index', [
            'procedimientos' => $procedimientos,
            'filtros' => $filtros,
            'especialidades' => ProcedimientoQuirurgico::query()
                ->distinct()->orderBy('especialidad')->pluck('especialidad'),
            'complejidades' => NivelComplejidad::values(),
        ]);
    }

    public function show(Request $request, ProcedimientoQuirurgico $procedimiento): Response
    {
        $filtros = [
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
            'estado' => (string) $request->query('estado', ''),
        ];

        $cirugias = $this->instanciasDe($procedimiento)
            ->with(['paciente', 'sala', 'costo'])
            ->when($filtros['desde'] !== '', fn ($q) => $q->whereDate('fecha', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== '', fn ($q) => $q->whereDate('fecha', '<=', $filtros['hasta']))
            ->when($filtros['estado'] !== '', fn ($q) => $q->where('estado', $filtros['estado']))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Cirugia $c): array => [
                'id' => $c->id,
                'fecha' => $c->fecha->toDateString(),
                'hora_inicio' => $c->hora_inicio->format('H:i'),
                'hora_fin' => $c->hora_fin?->format('H:i'),
                'paciente' => $c->paciente?->only(['nombres', 'apellidos']),
                'sala' => $c->sala?->nombre,
                'estado' => $c->estado,
                'duracion_minutos' => $c->duracionMinutos(),
                'costo_total' => $c->costo?->costo_total,
            ]);

        $costos = $this->estadisticasDeCostos([$procedimiento->id])[$procedimiento->id] ?? null;

        $duraciones = $this->instanciasDe($procedimiento)
            ->where('estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('hora_fin')
            ->get(['id', 'hora_inicio', 'hora_fin'])
            ->map(fn (Cirugia $c): int => $c->duracionMinutos() ?? 0)
            ->filter(fn (int $minutos): bool => $minutos > 0);

        return Inertia::render('costeo/procedimientos/show', [
            'procedimiento' => $procedimiento->only([
                'id', 'codigo_cups', 'nombre', 'especialidad', 'complejidad',
                'duracion_estimada_minutos', 'tarifa_soat',
            ]),
            'estadisticas' => [
                'n_realizadas' => $this->instanciasDe($procedimiento)
                    ->where('estado', EstadoCirugia::Realizada->value)->count(),
                'n_costeadas' => (int) ($costos->n ?? 0),
                'costo_promedio' => $costos !== null ? round((float) $costos->costo_promedio, 2) : null,
                'costo_minimo' => $costos !== null ? round((float) $costos->costo_minimo, 2) : null,
                'costo_maximo' => $costos !== null ? round((float) $costos->costo_maximo, 2) : null,
                'duracion_promedio_minutos' => $duraciones->isEmpty()
                    ? null
                    : (int) round((float) $duraciones->avg()),
            ],
            'cirugias' => $cirugias,
            // Serie completa (sin paginar) para la gráfica: en la tabla solo
            // se ven 15 casos, y la dispersión de costos es justo lo que hay
            // que mirar entero.
            'serie' => $this->serieDe($procedimiento),
            'filtros' => $filtros,
            'estados' => EstadoCirugia::values(),
        ]);
    }

    public function cirugia(
        ProcedimientoQuirurgico $procedimiento,
        Cirugia $cirugia,
        PresentarCirugiaDetalle $presentar,
    ): Response {
        abort_unless(
            $cirugia->procedimientos()->whereKey($procedimiento->id)->exists(),
            404,
        );

        return Inertia::render('costeo/procedimientos/cirugia', [
            'procedimiento' => $procedimiento->only(['id', 'codigo_cups', 'nombre']),
            'cirugia' => $presentar->ejecutar($cirugia),
            'costo' => $cirugia->costo,
            // Contra qué se compara: un costo suelto no dice nada hasta que
            // se sabe si es alto o bajo para ese mismo procedimiento.
            'referencia' => $this->referenciaDe($procedimiento, $cirugia),
        ]);
    }

    /**
     * Costo de cada cirugía costeada del procedimiento, en orden cronológico.
     *
     * @return list<array{cirugia_id: int, fecha: string, costo_total: float, duracion_minutos: int|null}>
     */
    private function serieDe(ProcedimientoQuirurgico $procedimiento): array
    {
        $puntos = $this->instanciasDe($procedimiento)
            ->where('estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('hora_fin')
            ->whereHas('costo')
            ->with('costo')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn (Cirugia $c): array => [
                'cirugia_id' => $c->id,
                'fecha' => $c->fecha->toDateString(),
                'costo_total' => round((float) $c->costo->costo_total, 2),
                'duracion_minutos' => $c->duracionMinutos(),
            ])
            ->all();

        return array_values($puntos);
    }

    /**
     * Promedio del procedimiento y desglose promedio por componente, para
     * situar el costo de una cirugía concreta.
     *
     * @return array<string, mixed>|null
     */
    private function referenciaDe(ProcedimientoQuirurgico $procedimiento, Cirugia $cirugia): ?array
    {
        $promedios = CostoCirugia::query()
            ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('cirugias.hora_fin')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')
                    ->where('cirugia_procedimiento.es_principal', true);
            })
            ->where('cirugia_procedimiento.procedimiento_quirurgico_id', $procedimiento->id)
            // La propia cirugía queda fuera: compararla contra un promedio
            // que la incluye suaviza justo la desviación que se busca.
            ->where('cirugias.id', '!=', $cirugia->id)
            ->selectRaw(implode(', ', [
                'count(*) as n',
                'avg(costos_cirugia.costo_total) as costo_total',
                'avg(costos_cirugia.costo_recurso_humano) as recurso_humano',
                'avg(costos_cirugia.costo_sala) as sala',
                'avg(costos_cirugia.costo_equipos) as equipos',
                'avg(costos_cirugia.costo_insumos) as insumos',
                'avg(costos_cirugia.costo_indirecto) as indirectos',
            ]))
            ->toBase()
            ->first();

        if ($promedios === null || (int) $promedios->n === 0) {
            return null;
        }

        return [
            'n' => (int) $promedios->n,
            'costo_total' => round((float) $promedios->costo_total, 2),
            'recurso_humano' => round((float) $promedios->recurso_humano, 2),
            'sala' => round((float) $promedios->sala, 2),
            'equipos' => round((float) $promedios->equipos, 2),
            'insumos' => round((float) $promedios->insumos, 2),
            'indirectos' => round((float) $promedios->indirectos, 2),
        ];
    }

    /**
     * Cirugías cuyo procedimiento principal es el indicado (mismo criterio
     * de agrupación que los KPIs).
     *
     * @return Builder<Cirugia>
     */
    private function instanciasDe(ProcedimientoQuirurgico $procedimiento): Builder
    {
        return Cirugia::query()
            ->whereHas('procedimientos', fn ($query) => $query
                ->where('procedimientos_quirurgicos.id', $procedimiento->id)
                ->where('cirugia_procedimiento.es_principal', true));
    }

    /**
     * Agregados de costo TDABC por procedimiento principal, solo sobre
     * cirugías contabilizables (realizadas y con hora de fin), igual que
     * KpiService::baseCostosPorProcedimiento().
     *
     * @param  list<int>  $procedimientoIds
     * @return array<array-key, \stdClass>
     */
    private function estadisticasDeCostos(array $procedimientoIds): array
    {
        if ($procedimientoIds === []) {
            return [];
        }

        return CostoCirugia::query()
            ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('cirugias.hora_fin')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')
                    ->where('cirugia_procedimiento.es_principal', true);
            })
            ->whereIn('cirugia_procedimiento.procedimiento_quirurgico_id', $procedimientoIds)
            ->groupBy('cirugia_procedimiento.procedimiento_quirurgico_id')
            ->selectRaw(implode(', ', [
                'cirugia_procedimiento.procedimiento_quirurgico_id as procedimiento_id',
                'count(*) as n',
                'avg(costos_cirugia.costo_total) as costo_promedio',
                'min(costos_cirugia.costo_total) as costo_minimo',
                'max(costos_cirugia.costo_total) as costo_maximo',
            ]))
            ->toBase()
            ->get()
            ->keyBy('procedimiento_id')
            ->all();
    }
}
