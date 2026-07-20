<?php

namespace App\Http\Controllers\Cirugias;

use App\Enums\EstadoCirugia;
use App\Enums\RolQuirurgico;
use App\Enums\TipoCirugia;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCirugiaRequest;
use App\Models\Cirugia;
use App\Models\EquipoMedico;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Services\Cirugias\PresentarCirugiaDetalle;
use App\Services\Cirugias\RegistrarCirugia;
use App\Services\Costing\TdabcCostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CirugiaController extends Controller
{
    public function index(): Response
    {
        // El digitador solo registra: no ve el costo ni entra al detalle.
        $esDigitador = auth()->user()->isDigitador();

        $cirugias = Cirugia::query()
            ->with(['paciente', 'procedimientos', 'costo'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Cirugia $cirugia): array => [
                'id' => $cirugia->id,
                'fecha' => $cirugia->fecha->toDateString(),
                'paciente' => $cirugia->paciente?->only(['nombres', 'apellidos']),
                'procedimiento_principal' => $cirugia->procedimientoPrincipal()?->only(['codigo_cups', 'nombre']),
                'tipo' => $cirugia->tipo,
                'estado' => $cirugia->estado,
                'duracion_minutos' => $cirugia->duracionMinutos(),
                'costo_total' => $esDigitador ? null : $cirugia->costo?->costo_total,
            ]);

        return Inertia::render('cirugias/index', [
            'cirugias' => $cirugias,
            'puedeCostear' => ! $esDigitador,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('cirugias/create', [
            'pacientes' => Paciente::orderBy('apellidos')->get(['id', 'nombres', 'apellidos']),
            'salas' => SalaOperatoria::where('activa', true)->orderBy('nombre')->get(['id', 'nombre', 'costo_hora']),
            'procedimientos' => ProcedimientoQuirurgico::orderBy('nombre')
                ->get(['id', 'codigo_cups', 'nombre', 'duracion_estimada_minutos']),
            'recursos' => RecursoHumano::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'rol', 'especialidad']),
            'insumos' => Insumo::where('activo', true)->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'unidad', 'costo_unitario']),
            'equiposMedicos' => EquipoMedico::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'costo_hora']),
            'tipos' => TipoCirugia::values(),
            'estados' => EstadoCirugia::values(),
            'rolesQuirurgicos' => RolQuirurgico::values(),
        ]);
    }

    public function store(StoreCirugiaRequest $request, RegistrarCirugia $registrar, TdabcCostingService $motor): RedirectResponse
    {
        $cirugia = $registrar->ejecutar($request->validated());

        // Costeo automático: si el procedimiento ya se realizó, el costo
        // TDABC queda disponible para el administrador en el módulo Costeo
        // sin acción manual. Best-effort: nunca bloquea el registro.
        if ($cirugia->estado === EstadoCirugia::Realizada->value) {
            try {
                $motor->calcular($cirugia);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // El digitador vuelve al listado (no tiene acceso al detalle/costeo);
        // el administrador va al detalle como hasta ahora.
        if ($request->user()->isDigitador()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Procedimiento registrado.']);

            return redirect()->route('cirugias.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procedimiento registrado. Ya puedes calcular su costo.']);

        return redirect()->route('cirugias.show', $cirugia);
    }

    /**
     * El route-model-binding aplica el HospitalScope: una cirugía de otro
     * hospital responde 404.
     */
    public function show(Cirugia $cirugia, PresentarCirugiaDetalle $presentar): Response
    {
        return Inertia::render('cirugias/show', [
            'cirugia' => $presentar->ejecutar($cirugia),
            'costo' => $cirugia->costo,
        ]);
    }

    public function calcular(Cirugia $cirugia, TdabcCostingService $motor): RedirectResponse
    {
        Gate::authorize('operar-hospital');

        if ($cirugia->estado !== EstadoCirugia::Realizada->value) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Solo se costean cirugías en estado «realizada»: costear una cirugía '.
                    'en proceso o cancelada contaminaría los indicadores.',
            ]);

            return back();
        }

        $motor->calcular($cirugia);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Costo TDABC calculado.']);

        // Vuelve a la página que pidió el cálculo (detalle de registro o
        // detalle del módulo Costeo); sin referencia, al detalle de registro.
        return redirect()->back(fallback: route('cirugias.show', $cirugia));
    }
}
