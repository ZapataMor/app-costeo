<?php

namespace App\Http\Controllers\Parametros;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Enums\NivelConfiabilidad;
use App\Exceptions\CapacidadCifNoDisponibleException;
use App\Exceptions\SolapeDeCostoIndirectoException;
use App\Http\Controllers\Concerns\FiltraListado;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActivarCategoriaCifRequest;
use App\Http\Requests\StoreConceptoCostoIndirectoRequest;
use App\Http\Requests\UpdateConceptoCostoIndirectoRequest;
use App\Models\ConceptoCostoIndirecto;
use App\Models\Hospital;
use App\Services\Costing\ActivarCategoriaCif;
use App\Services\Costing\DesactivarCategoriaCif;
use App\Support\HospitalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de bolsas de costo indirecto (Capa 1) y encendido por categoría.
 *
 * El CRUD nunca enciende un concepto: `activo` está fuera del `$fillable` y
 * solo ActivarCategoriaCif lo escribe, junto con el origen del componente
 * directo equivalente. Las acciones `activar`/`desactivar` de este controlador
 * son la única puerta a ese servicio, y por eso son las únicas que pueden
 * cambiar cómo se costea el indirecto de las cirugías nuevas.
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
        $datos = $request->validated();

        // Una bolsa encendida ya está repartiéndose en las cirugías que se
        // registren desde hoy. Cambiarle el monto o el inductor en caliente
        // reescribe todas las tasas futuras sin dejar rastro de cuándo
        // cambió; para eso existen las vigencias: se cierra la actual y se
        // abre otra. Los campos que no mueven dinero sí se pueden corregir.
        if ($concepto->activo && ($campos = $this->camposEconomicosModificados($concepto, $datos)) !== []) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Esta bolsa está activa: no se puede cambiar '
                    .implode(', ', $campos).' sobre la marcha. Cierra su vigencia '
                    .'y registra una nueva, o desactiva primero la categoría.',
            ]);

            return back();
        }

        $concepto->update($datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Concepto actualizado.']);

        return redirect()->route('parametros.costos-indirectos.index');
    }

    /** Enciende todas las bolsas de una categoría y excluye su equivalente directo. */
    public function activar(ActivarCategoriaCifRequest $request, ActivarCategoriaCif $activar): RedirectResponse
    {
        $hospital = Hospital::findOrFail(HospitalContext::id());
        $categoria = CategoriaCif::from($request->string('categoria')->value());

        try {
            $activados = $activar->ejecutar($hospital, $categoria, $request->boolean('confirmado'));
        } catch (SolapeDeCostoIndirectoException|CapacidadCifNoDisponibleException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Categoría activada: {$activados} bolsa(s) entran ahora al costo indirecto"
                .($categoria->solapaConElDirecto()
                    ? ' y '.$categoria->descripcionDelSolape().' deja de sumarse al directo.'
                    : '.'),
        ]);

        return back();
    }

    /** Apaga la categoría y devuelve el componente directo a su valor digitado. */
    public function desactivar(
        ActivarCategoriaCifRequest $request,
        DesactivarCategoriaCif $desactivar,
    ): RedirectResponse {
        $hospital = Hospital::findOrFail(HospitalContext::id());
        $categoria = CategoriaCif::from($request->string('categoria')->value());

        $desactivadas = $desactivar->ejecutar($hospital, $categoria);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Categoría desactivada: {$desactivadas} bolsa(s) salen del costo indirecto"
                .($categoria->solapaConElDirecto()
                    ? ' y '.$categoria->descripcionDelSolape().' vuelve al costo directo.'
                    : '.'),
        ]);

        return back();
    }

    /**
     * Campos cuyo cambio alteraría el dinero que reparte una bolsa activa.
     *
     * @param  array<string, mixed>  $datos
     * @return list<string>
     */
    protected function camposEconomicosModificados(ConceptoCostoIndirecto $concepto, array $datos): array
    {
        $etiquetas = [
            'categoria' => 'la categoría',
            'base_asignacion' => 'la base de asignación',
            'monto_mensual' => 'el monto mensual',
            'porcentaje' => 'el porcentaje',
            'vigente_desde' => 'el inicio de la vigencia',
        ];

        $modificados = [];

        foreach ($etiquetas as $campo => $etiqueta) {
            if (! array_key_exists($campo, $datos)) {
                continue;
            }

            $actual = $concepto->getAttribute($campo);
            $actual = $actual instanceof \BackedEnum ? $actual->value : $actual;
            $actual = $actual instanceof \DateTimeInterface ? $actual->format('Y-m-d') : $actual;
            $nuevo = $datos[$campo];

            // Comparación laxa a propósito: «20000000.00» y 20000000 son el
            // mismo monto, y un decimal:2 siempre vuelve como string.
            if ($actual === null xor $nuevo === null) {
                $modificados[] = $etiqueta;
            } elseif ($actual !== null && (string) $actual !== (string) $nuevo
                && (! is_numeric($actual) || ! is_numeric($nuevo)
                    || abs((float) $actual - (float) $nuevo) > 0.00001)) {
                $modificados[] = $etiqueta;
            }
        }

        return $modificados;
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
        $activar = app(ActivarCategoriaCif::class);

        // Cuántas bolsas hay por categoría y cuántas están encendidas: la UI
        // no puede ofrecer «activar» sin conceptos ni repetir la activación.
        $porCategoria = $hospital === null ? collect() : ConceptoCostoIndirecto::query()
            ->selectRaw('categoria, count(*) as total, sum(case when activo then 1 else 0 end) as activos')
            ->groupBy('categoria')
            ->get()
            ->keyBy('categoria');

        return [
            'categorias' => array_map(
                static fn (CategoriaCif $c): array => [
                    'valor' => $c->value,
                    'solapa' => $c->solapaConElDirecto(),
                    'solape' => $c->descripcionDelSolape(),
                    'total' => (int) ($porCategoria[$c->value]->total ?? 0),
                    'activos' => (int) ($porCategoria[$c->value]->activos ?? 0),
                    // Registros digitados que habría que resolver antes de
                    // encender la bolsa; la UI los nombra en el diálogo.
                    'conflictos' => $hospital === null
                        ? []
                        : $activar->componentesDigitados($hospital, $c),
                ],
                CategoriaCif::cases(),
            ),
            'capacidades' => $hospital?->denominadoresCif() ?? [],
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
