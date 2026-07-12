<?php

namespace App\Http\Controllers\Parametros;

use App\Enums\CategoriaInsumo;
use App\Enums\NivelConfiabilidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsumoRequest;
use App\Http\Requests\UpdateInsumoRequest;
use App\Models\Insumo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InsumoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('parametros/insumos/index', [
            'insumos' => Insumo::orderBy('nombre')->paginate(15)->withQueryString(),
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('parametros/insumos/create', $this->catalogos());
    }

    public function store(StoreInsumoRequest $request): RedirectResponse
    {
        Insumo::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Insumo registrado.']);

        return back();
    }

    public function edit(Insumo $insumo): Response
    {
        return Inertia::render('parametros/insumos/edit', [
            'insumo' => $insumo,
            ...$this->catalogos(),
        ]);
    }

    public function update(UpdateInsumoRequest $request, Insumo $insumo): RedirectResponse
    {
        $insumo->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Insumo actualizado.']);

        return redirect()->route('parametros.insumos.index');
    }

    public function destroy(Insumo $insumo): RedirectResponse
    {
        if ($insumo->consumos()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: tiene consumos registrados en cirugías. Puedes desactivarlo editándolo.',
            ]);

            return back();
        }

        $insumo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Insumo eliminado.']);

        return redirect()->route('parametros.insumos.index');
    }

    /** @return array<string, mixed> */
    protected function catalogos(): array
    {
        return [
            'categorias' => CategoriaInsumo::values(),
            'nivelesConfiabilidad' => NivelConfiabilidad::values(),
        ];
    }
}
