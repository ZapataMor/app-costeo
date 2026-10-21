<?php

namespace App\Http\Controllers\Conocimiento;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conocimiento\GuardarHiloRequest;
use App\Models\ComunidadPractica;
use App\Models\HiloComunidad;
use App\Models\Hospital;
use App\Models\User;
use App\Services\Conocimiento\GestorComunidades;
use App\Support\HospitalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comunidades de práctica por especialidad (tesis, componente 1).
 */
class ComunidadController extends Controller
{
    public function __construct(protected GestorComunidades $gestor) {}

    public function index(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $this->gestor->asegurar(Hospital::query()->findOrFail(HospitalContext::id()));

        $comunidades = ComunidadPractica::query()
            ->where('activa', true)
            ->withCount(['miembros', 'hilos'])
            ->withMax('hilos', 'ultima_actividad_en')
            ->orderByRaw('especialidad is not null')
            ->orderBy('nombre')
            ->get()
            ->map(fn (ComunidadPractica $c): array => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'especialidad' => $c->especialidad,
                'descripcion' => $c->descripcion,
                'miembros' => (int) $c->getAttribute('miembros_count'),
                'hilos' => (int) $c->getAttribute('hilos_count'),
                'ultima_actividad' => $c->getAttribute('hilos_max_ultima_actividad_en'),
                'soy_miembro' => $c->miembros()->whereKey($usuario->id)->exists(),
            ])
            ->all();

        return Inertia::render('conocimiento/comunidades/index', ['comunidades' => $comunidades]);
    }

    public function show(Request $request, ComunidadPractica $comunidad): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $hilos = $comunidad->hilos()
            ->with('autor:id,name')
            ->withCount('respuestas')
            ->withExists(['respuestas as resuelto' => fn ($q) => $q->where('es_solucion', true)])
            ->orderByDesc('fijado')
            ->orderByDesc('ultima_actividad_en')
            ->paginate(15)
            ->through(fn (HiloComunidad $h): array => [
                'id' => $h->id,
                'titulo' => $h->titulo,
                'autor' => $h->autor?->name,
                'respuestas' => (int) $h->getAttribute('respuestas_count'),
                'resuelto' => (bool) $h->getAttribute('resuelto'),
                'fijado' => $h->fijado,
                'cerrado' => $h->cerrado,
                'enlazable_tipo' => $h->enlazable_tipo,
                'leccion_aprendida_id' => $h->leccion_aprendida_id,
                'ultima_actividad_en' => $h->ultima_actividad_en->toDateTimeString(),
            ]);

        return Inertia::render('conocimiento/comunidades/show', [
            'comunidad' => [
                'id' => $comunidad->id,
                'nombre' => $comunidad->nombre,
                'especialidad' => $comunidad->especialidad,
                'descripcion' => $comunidad->descripcion,
                'miembros' => $comunidad->miembros()->with('recursoHumano:id,rol,especialidad')->orderBy('name')->get()
                    ->map(fn (User $m): array => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'rol' => (string) $m->getRelationValue('pivot')?->getAttribute('rol'),
                        'perfil' => $m->recursoHumano->especialidad ?? ($m->isAdminHospital() ? 'Administración' : null),
                    ])->all(),
            ],
            'hilos' => $hilos,
            'permisos' => [
                'participar' => $this->gestor->puedeParticipar($usuario, $comunidad),
                'moderar' => $this->gestor->puedeModerar($usuario, $comunidad),
            ],
            'enlace' => $request->only(['enlazable_tipo', 'enlazable_id', 'titulo']),
        ]);
    }

    public function storeHilo(GuardarHiloRequest $request, ComunidadPractica $comunidad): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($this->gestor->puedeParticipar($usuario, $comunidad), 403, 'No es miembro de esta comunidad.');

        $datos = $request->validated();

        // Lo enlazado tiene que ser del mismo hospital: `enlazado()` lo busca
        // con el scope del tenant y aquí se comprueba que exista.
        if (! empty($datos['enlazable_tipo'])) {
            $clase = HiloComunidad::ENLAZABLES[$datos['enlazable_tipo']];
            abort_unless($clase::query()->whereKey($datos['enlazable_id'])->exists(), 422, 'Lo que intenta enlazar no existe en este hospital.');
        }

        $hilo = $comunidad->hilos()->create($datos + [
            'hospital_id' => $comunidad->hospital_id,
            'autor_id' => $usuario->id,
            'ultima_actividad_en' => now(),
        ]);

        return redirect()->route('conocimiento.hilos.show', $hilo);
    }
}
