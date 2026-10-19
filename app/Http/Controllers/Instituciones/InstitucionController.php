<?php

namespace App\Http\Controllers\Instituciones;

use App\Enums\NivelComplejidad;
use App\Http\Controllers\Concerns\FiltraListado;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInstitucionRequest;
use App\Http\Requests\StoreUsuarioInstitucionRequest;
use App\Http\Requests\UpdateInstitucionRequest;
use App\Models\Hospital;
use App\Models\RegistroActividad;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de instituciones (hospitales y centros médicos) del super_admin.
 * Cada institución es un tenant: aquí nace, se identifica y se le fija la
 * capacidad TDABC inicial; sus usuarios se administran desde el detalle.
 */
class InstitucionController extends Controller
{
    use FiltraListado;

    public function index(Request $request): Response
    {
        $hospitales = $this->aplicarFiltros(
            Hospital::query(),
            $request,
            ['nombre', 'nit', 'municipio'],
            ['nivel' => 'nivel_complejidad'],
        )
            ->withCount(['users', 'cirugias'])
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Hospital $h): array => [
                'id' => $h->id,
                'nombre' => $h->nombre,
                'nit' => $h->nit,
                'nivel_complejidad' => $h->nivel_complejidad,
                'municipio' => $h->municipio,
                'departamento' => $h->departamento,
                'usuarios' => $h->users_count,
                'cirugias' => $h->cirugias_count,
            ]);

        return Inertia::render('instituciones/index', [
            'instituciones' => $hospitales,
            'filtros' => $this->filtrosActivos($request, ['nivel']),
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('instituciones/create', $this->catalogos());
    }

    public function store(StoreInstitucionRequest $request): RedirectResponse
    {
        $hospital = Hospital::create($request->validated());

        RegistroActividad::registrar(
            'creó institución',
            "{$request->user()->name} creó la institución «{$hospital->nombre}»",
            auditable: $hospital,
            hospitalId: $hospital->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Institución creada. Ahora puedes agregarle usuarios.']);

        return redirect()->route('instituciones.show', $hospital);
    }

    public function show(Hospital $hospital): Response
    {
        $usuarios = $hospital->users()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'activo', 'created_at'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'activo' => $u->activo,
                'created_at' => $u->created_at?->toDateString(),
            ]);

        return Inertia::render('instituciones/show', [
            'institucion' => $this->presentar($hospital),
            'usuarios' => $usuarios,
            'rolesAsignables' => StoreUsuarioInstitucionRequest::rolesAsignables(),
        ]);
    }

    public function edit(Hospital $hospital): Response
    {
        return Inertia::render('instituciones/edit', [
            'institucion' => $this->presentar($hospital),
            ...$this->catalogos(),
        ]);
    }

    public function update(UpdateInstitucionRequest $request, Hospital $hospital): RedirectResponse
    {
        $hospital->update($request->validated());

        RegistroActividad::registrar(
            'actualizó institución',
            "{$request->user()->name} actualizó la institución «{$hospital->nombre}»",
            auditable: $hospital,
            hospitalId: $hospital->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Institución actualizada.']);

        return redirect()->route('instituciones.show', $hospital);
    }

    /**
     * Solo se elimina una institución vacía: las tablas de dominio tienen
     * `restrictOnDelete` y borrar un hospital con cirugías costeadas sería
     * perder trazabilidad. Sus usuarios sí se eliminan con ella; dejarlos
     * sin hospital los volvería cuentas bloqueadas para siempre.
     */
    public function destroy(Request $request, Hospital $hospital): RedirectResponse
    {
        if ($this->tieneDatosDeDominio($hospital)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: la institución ya tiene datos registrados (pacientes, cirugías o catálogos).',
            ]);

            return back();
        }

        $nombre = $hospital->nombre;

        DB::transaction(function () use ($hospital): void {
            $hospital->users()->delete();
            $hospital->delete();
        });

        RegistroActividad::registrar(
            'eliminó institución',
            "{$request->user()->name} eliminó la institución «{$nombre}»",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Institución eliminada.']);

        return redirect()->route('instituciones.index');
    }

    protected function tieneDatosDeDominio(Hospital $hospital): bool
    {
        return $hospital->pacientes()->exists()
            || $hospital->cirugias()->exists()
            || $hospital->salasOperatorias()->exists()
            || $hospital->recursosHumanos()->exists()
            || $hospital->procedimientos()->exists()
            || $hospital->insumos()->exists()
            || $hospital->equiposMedicos()->exists()
            || $hospital->conceptosCostoIndirecto()->exists();
    }

    /** @return array<string, mixed> */
    protected function presentar(Hospital $hospital): array
    {
        return [
            ...$hospital->only([
                'id', 'nombre', 'nit', 'nivel_complejidad', 'municipio', 'departamento',
                'horas_dia', 'dias_mes', 'minutos_efectivos_hora',
            ]),
            'factor_indirecto' => (float) $hospital->factor_indirecto,
            'minutos_disponibles_mes' => $hospital->minutosDisponiblesMes(),
            'created_at' => $hospital->created_at?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    protected function catalogos(): array
    {
        return [
            'nivelesComplejidad' => NivelComplejidad::values(),
        ];
    }
}
