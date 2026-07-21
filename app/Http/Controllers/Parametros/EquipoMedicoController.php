<?php

namespace App\Http\Controllers\Parametros;

use App\Enums\NivelConfiabilidad;
use App\Http\Controllers\Concerns\FiltraListado;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipoMedicoRequest;
use App\Http\Requests\UpdateEquipoMedicoRequest;
use App\Models\EquipoMedico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EquipoMedicoController extends Controller
{
    use FiltraListado;

    public function index(Request $request): Response
    {
        $equipos = $this->aplicarFiltros(
            EquipoMedico::query(),
            $request,
            ['nombre'],
            ['activo' => 'activo', 'confiabilidad' => 'nivel_confiabilidad'],
        );

        return Inertia::render('parametros/equipos-medicos/index', [
            'equipos' => $equipos->orderBy('nombre')->paginate(15)->withQueryString(),
            'filtros' => $this->filtrosActivos($request, ['activo', 'confiabilidad']),
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('parametros/equipos-medicos/create', $this->catalogos());
    }

    public function store(StoreEquipoMedicoRequest $request): RedirectResponse
    {
        EquipoMedico::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipo médico registrado.']);

        return back();
    }

    public function edit(EquipoMedico $equipoMedico): Response
    {
        return Inertia::render('parametros/equipos-medicos/edit', [
            'equipo' => $equipoMedico,
            ...$this->catalogos(),
        ]);
    }

    public function update(UpdateEquipoMedicoRequest $request, EquipoMedico $equipoMedico): RedirectResponse
    {
        $equipoMedico->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipo médico actualizado.']);

        return redirect()->route('parametros.equipos-medicos.index');
    }

    public function destroy(EquipoMedico $equipoMedico): RedirectResponse
    {
        Gate::authorize('operar-hospital');

        if ($equipoMedico->cirugias()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: fue usado en cirugías registradas. Puedes desactivarlo editándolo.',
            ]);

            return back();
        }

        $equipoMedico->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipo médico eliminado.']);

        return redirect()->route('parametros.equipos-medicos.index');
    }

    /** @return array<string, mixed> */
    protected function catalogos(): array
    {
        return [
            'nivelesConfiabilidad' => NivelConfiabilidad::values(),
        ];
    }
}
