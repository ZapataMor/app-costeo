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
        $cirugias = Cirugia::query()
            ->with(['paciente', 'procedimientos', 'costo'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Cirugia $cirugia): array => [
                'id' => $cirugia->id,
                'fecha' => $cirugia->fecha?->toDateString(),
                'paciente' => $cirugia->paciente?->only(['nombres', 'apellidos']),
                'procedimiento_principal' => $cirugia->procedimientoPrincipal()?->only(['codigo_cups', 'nombre']),
                'tipo' => $cirugia->tipo,
                'estado' => $cirugia->estado,
                'duracion_minutos' => $cirugia->duracionMinutos(),
                'costo_total' => $cirugia->costo?->costo_total,
            ]);

        return Inertia::render('cirugias/index', ['cirugias' => $cirugias]);
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

    public function store(StoreCirugiaRequest $request, RegistrarCirugia $registrar): RedirectResponse
    {
        $cirugia = $registrar->ejecutar($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cirugía registrada. Ya puedes calcular su costo.']);

        return redirect()->route('cirugias.show', $cirugia);
    }

    /**
     * El route-model-binding aplica el HospitalScope: una cirugía de otro
     * hospital responde 404.
     */
    public function show(Cirugia $cirugia): Response
    {
        $cirugia->load([
            'paciente',
            'sala',
            'procedimientos',
            'equipoQuirurgico.recursoHumano',
            'consumos.insumo',
            'equiposMedicos',
            'costo',
        ]);

        return Inertia::render('cirugias/show', [
            'cirugia' => [
                'id' => $cirugia->id,
                'fecha' => $cirugia->fecha?->toDateString(),
                'hora_inicio' => $cirugia->hora_inicio?->format('Y-m-d H:i'),
                'hora_fin' => $cirugia->hora_fin?->format('Y-m-d H:i'),
                'duracion_minutos' => $cirugia->duracionMinutos(),
                'tipo' => $cirugia->tipo,
                'estado' => $cirugia->estado,
                'diagnostico_cie10' => $cirugia->diagnostico_cie10,
                'observaciones' => $cirugia->observaciones,
                'paciente' => $cirugia->paciente?->only(['nombres', 'apellidos']),
                'sala' => $cirugia->sala?->only(['nombre', 'costo_hora']),
                'procedimientos' => $cirugia->procedimientos->map(fn (ProcedimientoQuirurgico $p): array => [
                    'id' => $p->id,
                    'codigo_cups' => $p->codigo_cups,
                    'nombre' => $p->nombre,
                    'es_principal' => (bool) $p->pivot->es_principal,
                ]),
                'equipo' => $cirugia->equipoQuirurgico->map(fn ($miembro): array => [
                    'nombre' => $miembro->recursoHumano?->nombre,
                    'rol' => $miembro->rol,
                    'minutos_participacion' => $miembro->minutos_participacion,
                ]),
                'consumos' => $cirugia->consumos->map(fn ($consumo): array => [
                    'insumo' => $consumo->insumo?->nombre,
                    'unidad' => $consumo->insumo?->unidad,
                    'cantidad' => $consumo->cantidad,
                    'costo_unitario_registrado' => $consumo->costo_unitario_registrado,
                    'costo_total' => $consumo->costo_total,
                ]),
                'equipos_medicos' => $cirugia->equiposMedicos->map(fn (EquipoMedico $equipo): array => [
                    'nombre' => $equipo->nombre,
                    'minutos_uso' => $equipo->pivot?->minutos_uso,
                ]),
            ],
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

        return redirect()->route('cirugias.show', $cirugia);
    }
}
