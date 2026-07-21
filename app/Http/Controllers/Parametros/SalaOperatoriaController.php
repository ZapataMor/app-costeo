<?php

namespace App\Http\Controllers\Parametros;

use App\Enums\NivelConfiabilidad;
use App\Http\Controllers\Concerns\FiltraListado;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalaOperatoriaRequest;
use App\Http\Requests\UpdateSalaOperatoriaRequest;
use App\Models\SalaOperatoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SalaOperatoriaController extends Controller
{
    use FiltraListado;

    public function index(Request $request): Response
    {
        $salas = $this->aplicarFiltros(
            SalaOperatoria::query(),
            $request,
            ['nombre'],
            ['activa' => 'activa', 'confiabilidad' => 'nivel_confiabilidad'],
        );

        return Inertia::render('parametros/salas-operatorias/index', [
            'salas' => $salas->orderBy('nombre')->paginate(15)->withQueryString(),
            'filtros' => $this->filtrosActivos($request, ['activa', 'confiabilidad']),
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('parametros/salas-operatorias/create', $this->catalogos());
    }

    public function store(StoreSalaOperatoriaRequest $request): RedirectResponse
    {
        SalaOperatoria::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sala operatoria registrada.']);

        return back();
    }

    public function edit(SalaOperatoria $salaOperatoria): Response
    {
        return Inertia::render('parametros/salas-operatorias/edit', [
            'sala' => $salaOperatoria,
            ...$this->catalogos(),
        ]);
    }

    public function update(UpdateSalaOperatoriaRequest $request, SalaOperatoria $salaOperatoria): RedirectResponse
    {
        $salaOperatoria->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sala operatoria actualizada.']);

        return redirect()->route('parametros.salas-operatorias.index');
    }

    public function destroy(SalaOperatoria $salaOperatoria): RedirectResponse
    {
        Gate::authorize('operar-hospital');

        if ($salaOperatoria->cirugias()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: tiene cirugías registradas. Puedes desactivarla editándola.',
            ]);

            return back();
        }

        $salaOperatoria->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sala operatoria eliminada.']);

        return redirect()->route('parametros.salas-operatorias.index');
    }

    /** @return array<string, mixed> */
    protected function catalogos(): array
    {
        return [
            'nivelesConfiabilidad' => NivelConfiabilidad::values(),
        ];
    }
}
