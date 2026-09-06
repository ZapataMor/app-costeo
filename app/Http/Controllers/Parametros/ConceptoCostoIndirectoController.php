<?php

namespace App\Http\Controllers\Parametros;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Enums\NivelConfiabilidad;
use App\Http\Controllers\Concerns\FiltraListado;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConceptoCostoIndirectoRequest;
use App\Http\Requests\UpdateConceptoCostoIndirectoRequest;
use App\Models\ConceptoCostoIndirecto;
use App\Models\Hospital;
use App\Services\Costing\ActivarCategoriaCif;
use App\Support\HospitalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de bolsas de costo indirecto (Capa 1).
 *
 * El CRUD nunca enciende un concepto: `activo` está fuera del `$fillable` y
 * solo ActivarCategoriaCif lo escribe, junto con el origen del componente
 * directo equivalente. Mientras el motor de asignación no exista, capturar
 * conceptos es inocuo y el costeo sigue dando `costo_directo × factor_indirecto`.
 *
 * @see ActivarCategoriaCif
 */
class ConceptoCostoIndirectoController extends Controller
{
    use FiltraListado;

    public function index(Request $request): Response
    {
        $conceptos = $this->aplicarFiltros(
            ConceptoCostoIndirecto::query(),
            $request,
            ['nombre'],
            ['categoria' => 'categoria', 'base' => 'base_asignacion', 'activo' => 'activo'],
        );

        return Inertia::render('parametros/costos-indirectos/index', [
            'conceptos' => $conceptos
                ->orderBy('categoria')
                ->orderBy('nombre')
                ->orderByDesc('vigente_desde')
                ->paginate(15)
                ->withQueryString(),
            'filtros' => $this->filtrosActivos($request, ['categoria', 'base', 'activo']),
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('parametros/costos-indirectos/create', $this->catalogos());
    }

    public function store(StoreConceptoCostoIndirectoRequest $request): RedirectResponse
    {
        ConceptoCostoIndirecto::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Concepto de costo indirecto registrado. Queda inactivo hasta que se active su categoría.',
        ]);

        return back();
    }

    public function edit(ConceptoCostoIndirecto $concepto): Response
    {
        return Inertia::render('parametros/costos-indirectos/edit', [
            'concepto' => $concepto,
            ...$this->catalogos(),
        ]);
    }

    public function update(
        UpdateConceptoCostoIndirectoRequest $request,
        ConceptoCostoIndirecto $concepto,
    ): RedirectResponse {
        $concepto->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Concepto actualizado.']);

        return redirect()->route('parametros.costos-indirectos.index');
    }

    public function destroy(ConceptoCostoIndirecto $concepto): RedirectResponse
    {
        Gate::authorize('operar-hospital');

        // Un concepto activo sostiene la exclusión del componente directo
        // equivalente: borrarlo dejaría al hospital sin una vía ni la otra.
        if ($concepto->activo) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar un concepto activo: desactiva primero su categoría.',
            ]);

            return back();
        }

        $concepto->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Concepto eliminado.']);

        return redirect()->route('parametros.costos-indirectos.index');
    }

    /** @return array<string, mixed> */
    protected function catalogos(): array
    {
        $hospital = Hospital::find(HospitalContext::id());

        return [
            'categorias' => array_map(
                static fn (CategoriaCif $c): array => [
                    'valor' => $c->value,
                    'solapa' => $c->solapaConElDirecto(),
                    'solape' => $c->descripcionDelSolape(),
                ],
                CategoriaCif::cases(),
            ),
            'basesAsignacion' => array_map(
                static fn (BaseAsignacionCif $b): array => [
                    'valor' => $b->value,
                    'etiqueta' => $b->etiqueta(),
                    'usaPorcentaje' => $b->usaPorcentaje(),
                ],
                BaseAsignacionCif::cases(),
            ),
            'nivelesConfiabilidad' => NivelConfiabilidad::values(),
            'origenes' => $hospital?->origenesDeComponentes() ?? [],
        ];
    }
}
