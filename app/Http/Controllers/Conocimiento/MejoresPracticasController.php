<?php

namespace App\Http\Controllers\Conocimiento;

use App\Enums\EstadoLeccion;
use App\Http\Controllers\Controller;
use App\Models\LeccionAprendida;
use App\Models\ProcedimientoQuirurgico;
use App\Models\User;
use App\Services\Conocimiento\AnalizadorMejoresPracticas;
use App\Support\HospitalContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mejores prácticas por procedimiento: lo que hacen distinto los casos de
 * menor costo y buen resultado, el benchmarking anónimo de profesionales y
 * las lecciones publicadas (propias y de la red).
 */
class MejoresPracticasController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $estadisticas = DB::table('costos_cirugia')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'costos_cirugia.cirugia_id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->where('costos_cirugia.hospital_id', HospitalContext::id())
            ->groupBy('cirugia_procedimiento.procedimiento_quirurgico_id')
            ->selectRaw('cirugia_procedimiento.procedimiento_quirurgico_id as id, count(*) as n, avg(costos_cirugia.costo_total) as costo, avg(costos_cirugia.desviacion_pct) as desviacion, '
                ."sum(case when costos_cirugia.clasificacion_desviacion = 'en_rango' then 1 else 0 end) as en_rango")
            ->get()
            ->keyBy('id');

        $lecciones = LeccionAprendida::query()
            ->where('estado', EstadoLeccion::Publicada->value)
            ->whereNotNull('procedimiento_quirurgico_id')
            ->groupBy('procedimiento_quirurgico_id')
            ->selectRaw('procedimiento_quirurgico_id, count(*) as n')
            ->pluck('n', 'procedimiento_quirurgico_id');

        $procedimientos = ProcedimientoQuirurgico::query()
            ->when($q !== '', fn ($b) => $b->where(fn ($s) => $s->where('nombre', 'like', "%{$q}%")->orWhere('especialidad', 'like', "%{$q}%")->orWhere('codigo_cups', 'like', "%{$q}%")))
            ->orderBy('especialidad')
            ->orderBy('nombre')
            ->get()
            ->map(function (ProcedimientoQuirurgico $p) use ($estadisticas, $lecciones): array {
                $e = $estadisticas->get($p->id);
                $n = (int) ($e->n ?? 0);

                return [
                    'id' => $p->id,
                    'codigo_cups' => $p->codigo_cups,
                    'nombre' => $p->nombre,
                    'especialidad' => $p->especialidad,
                    'n' => $n,
                    'costo_promedio' => $e !== null ? round((float) $e->costo, 2) : null,
                    'desviacion_promedio' => $e?->desviacion !== null ? round((float) $e->desviacion, 4) : null,
                    'en_rango_pct' => $n > 0 ? round((int) $e->en_rango / $n, 4) : null,
                    'lecciones' => (int) ($lecciones[$p->id] ?? 0),
                    'analizable' => $n >= AnalizadorMejoresPracticas::MINIMO_CASOS,
                ];
            })
            ->all();

        return Inertia::render('conocimiento/mejores-practicas/index', [
            'procedimientos' => $procedimientos,
            'filtros' => ['q' => $q],
        ]);
    }

    public function show(Request $request, ProcedimientoQuirurgico $procedimiento, AnalizadorMejoresPracticas $analizador): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $lecciones = LeccionAprendida::query()
            ->where('estado', EstadoLeccion::Publicada->value)
            ->where('procedimiento_quirurgico_id', $procedimiento->id)
            ->orderByDesc('publicada_en')
            ->get()
            ->map(fn (LeccionAprendida $l): array => $this->leccion($l, null));

        // La red no comparte ids de procedimiento: se cruza por código CUPS
        // y, en su defecto, por especialidad.
        $red = LeccionAprendida::query()->deLaRed()
            ->where('hospital_id', '!=', $procedimiento->hospital_id)
            ->where(fn ($q) => $q
                ->whereIn('procedimiento_quirurgico_id', ProcedimientoQuirurgico::query()->withoutGlobalScopes()->where('codigo_cups', $procedimiento->codigo_cups)->select('id'))
                ->orWhere('especialidad', $procedimiento->especialidad))
            ->with('hospital:id,nombre')
            ->orderByDesc('publicada_en')
            ->limit(6)
            ->get()
            ->map(fn (LeccionAprendida $l): array => $this->leccion($l, $l->hospital->nombre));

        return Inertia::render('conocimiento/mejores-practicas/show', [
            'procedimiento' => $procedimiento->only(['id', 'codigo_cups', 'nombre', 'especialidad', 'complejidad', 'duracion_estimada_minutos']),
            'analisis' => $analizador->analizar($procedimiento, $usuario->recurso_humano_id),
            'lecciones' => $lecciones->all(),
            'red' => $red->all(),
            'puedeVerCostos' => $usuario->gestionaConocimiento(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function leccion(LeccionAprendida $l, ?string $hospital): array
    {
        return [
            'id' => $l->id,
            'titulo' => $l->titulo,
            'tipo' => $l->tipo->value,
            'tipo_etiqueta' => $l->tipo->etiqueta(),
            'extracto' => mb_strimwidth($l->leccion, 0, 200, '…'),
            'publicada_en' => $l->publicada_en?->toDateString(),
            'hospital' => $hospital,
        ];
    }
}
