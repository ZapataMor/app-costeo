<?php

namespace App\Http\Controllers\Parametros;

use App\Enums\NivelConfiabilidad;
use App\Enums\RolQuirurgico;
use App\Http\Controllers\Concerns\FiltraListado;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecursoHumanoRequest;
use App\Http\Requests\UpdateRecursoHumanoRequest;
use App\Models\RecursoHumano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RecursoHumanoController extends Controller
{
    use FiltraListado;

    public function index(Request $request): Response
    {
        $recursos = $this->aplicarFiltros(
            RecursoHumano::query(),
            $request,
            ['nombre', 'especialidad'],
            ['rol' => 'rol', 'activo' => 'activo', 'confiabilidad' => 'nivel_confiabilidad'],
        );

        return Inertia::render('parametros/recursos-humanos/index', [
            'recursos' => $recursos->orderBy('nombre')->paginate(15)->withQueryString(),
            'filtros' => $this->filtrosActivos($request, ['rol', 'activo', 'confiabilidad']),
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('parametros/recursos-humanos/create', $this->catalogos());
    }

    public function store(StoreRecursoHumanoRequest $request): RedirectResponse
    {
        RecursoHumano::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurso humano registrado.']);

        return back();
    }

    public function edit(RecursoHumano $recursoHumano): Response
    {
        return Inertia::render('parametros/recursos-humanos/edit', [
            'recurso' => $recursoHumano,
            ...$this->catalogos(),
        ]);
    }

    public function update(UpdateRecursoHumanoRequest $request, RecursoHumano $recursoHumano): RedirectResponse
    {
        $recursoHumano->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurso humano actualizado.']);

        return redirect()->route('parametros.recursos-humanos.index');
    }

    public function destroy(RecursoHumano $recursoHumano): RedirectResponse
    {
        Gate::authorize('operar-hospital');

        if ($recursoHumano->participaciones()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: participa en cirugías registradas. Puedes desactivarlo editándolo.',
            ]);

            return back();
        }

        $recursoHumano->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurso humano eliminado.']);

        return redirect()->route('parametros.recursos-humanos.index');
    }

    /** @return array<string, mixed> */
    protected function catalogos(): array
    {
        return [
            'roles' => RolQuirurgico::values(),
            'nivelesConfiabilidad' => NivelConfiabilidad::values(),
        ];
    }
}
