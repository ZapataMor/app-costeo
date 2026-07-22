<?php

namespace App\Http\Controllers\Cirugias;

use App\Enums\EstadoCirugia;
use App\Enums\FaseCiclo;
use App\Enums\Regimen;
use App\Enums\RolQuirurgico;
use App\Enums\TipoCirugia;
use App\Http\Controllers\Controller;
use App\Http\Requests\CerrarCirugiaRequest;
use App\Http\Requests\StoreCirugiaRequest;
use App\Http\Requests\UpdateCirugiaRequest;
use App\Models\AlertaSobrecosto;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\EquipoMedico;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\PlantillaEquipo;
use App\Models\PlantillaInsumo;
use App\Models\PlantillaPersonal;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Models\Scopes\HospitalScope;
use App\Services\Cirugias\ActualizarCirugia;
use App\Services\Cirugias\PresentarCirugiaDetalle;
use App\Services\Cirugias\RegistrarCirugia;
use App\Services\Costing\TdabcCostingService;
use App\Support\HospitalContext;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CirugiaController extends Controller
{
    public function index(Request $request): Response
    {
        $esDigitador = $request->user()->isDigitador();

        // El digitador no ve el histórico del hospital —ni los datos de otros
        // pacientes ni los costos—: su pantalla es el botón de registrar más
        // lo que él mismo capturó, para poder corregirlo.
        if ($esDigitador) {
            return Inertia::render('cirugias/inicio', [
                'mios' => $this->registrosPropios($request->user()->id),
            ]);
        }

        $busqueda = trim((string) $request->string('q'));
        $estado = trim((string) $request->string('estado'));
        $pendientes = $request->boolean('pendientes');

        $cirugias = Cirugia::query()
            ->with(['paciente', 'procedimientos', 'costo'])
            // Búsqueda por paciente o por procedimiento (nombre o CUPS): son
            // las dos formas en que alguien recuerda una cirugía.
            ->when($busqueda !== '', fn (Builder $q) => $q->where(
                fn (Builder $q) => $q
                    ->whereHas('paciente', fn (Builder $p) => $p
                        ->where('nombres', 'like', "%{$busqueda}%")
                        ->orWhere('apellidos', 'like', "%{$busqueda}%"))
                    ->orWhereHas('procedimientos', fn (Builder $p) => $p
                        ->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('codigo_cups', 'like', "%{$busqueda}%")),
            ))
            ->when($estado !== '', fn (Builder $q) => $q->where('estado', $estado))
            ->when($request->filled('desde'), fn (Builder $q) => $q->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn (Builder $q) => $q->whereDate('fecha', '<=', $request->date('hasta')))
            // Bandeja de pendientes: lo que no entrará a los indicadores
            // mientras no se complete.
            ->when($pendientes, fn (Builder $q) => $q->where(
                fn (Builder $q) => self::filtroPendientes($q)
            ))
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
                'costo_total' => $cirugia->costo?->costo_total,
                // Un procedimiento abierto se cierra desde el listado sin
                // volver a pasar por el formulario completo.
                'puede_cerrarse' => $cirugia->pasoDeCierre() !== null,
                // Qué marca pide el modal: la salida de sala o el egreso.
                'paso_cierre' => $cirugia->pasoDeCierre(),
                'hora_inicio' => $cirugia->hora_inicio->format('Y-m-d\TH:i'),
                'hora_fin' => $cirugia->hora_fin?->format('Y-m-d\TH:i'),
                // Por qué no cuenta para los indicadores: sin el motivo, la
                // insignia «No contabilizada» obliga a adivinar qué falta.
                'motivo_pendiente' => $this->motivoPendiente($cirugia),
            ]);

        return Inertia::render('cirugias/index', [
            'cirugias' => $cirugias,
            'estados' => EstadoCirugia::values(),
            'filtros' => [
                'q' => $busqueda,
                'estado' => $estado,
                'desde' => (string) $request->string('desde'),
                'hasta' => (string) $request->string('hasta'),
                'pendientes' => $pendientes ? '1' : '',
            ],
            // Total real de pendientes en el hospital, no solo en esta página.
            'totalPendientes' => Cirugia::query()
                ->where(fn (Builder $q) => self::filtroPendientes($q))
                ->count(),
        ]);
    }

    /** Qué le falta a esta cirugía para entrar a los indicadores. */
    private function motivoPendiente(Cirugia $cirugia): ?string
    {
        if ($cirugia->hora_fin === null) {
            return 'Falta la salida de sala';
        }

        if ($cirugia->estado !== EstadoCirugia::Realizada->value) {
            return 'Está en «'.str_replace('_', ' ', $cirugia->estado).'»';
        }

        if ($cirugia->costo === null) {
            return 'Falta calcular el costo';
        }

        return null;
    }

    /**
     * Qué cuenta como pendiente: todo lo que hoy no llega a los indicadores.
     *
     * Incluye las cirugías ya cerradas que se quedaron sin costo calculado.
     * Antes solo miraba el cierre, así que una cirugía terminada y sin costear
     * era invisible: no aparecía en la bandeja, pero sí bajaba la completitud
     * del panel, y no había forma de encontrarla.
     */
    private static function filtroPendientes(Builder $query): void
    {
        $query
            ->whereNull('hora_fin')
            ->orWhere('estado', '!=', EstadoCirugia::Realizada->value)
            ->orWhere(fn (Builder $q) => $q
                ->where('estado', EstadoCirugia::Realizada->value)
                ->whereNotNull('hora_fin')
                ->whereDoesntHave('costo'));
    }

    public function create(): Response
    {
        return Inertia::render('cirugias/create', $this->catalogos());
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
        $cirugia->loadMissing(['facturacion', 'resultadoClinico']);

        return Inertia::render('cirugias/show', [
            'cirugia' => $presentar->ejecutar($cirugia),
            'costo' => $cirugia->costo,
            'facturacion' => $cirugia->facturacion,
            'resultadoClinico' => $cirugia->resultadoClinico,
        ]);
    }

    public function edit(Cirugia $cirugia): Response
    {
        // El digitador solo corrige lo que él mismo capturó.
        Gate::authorize('corregir-cirugia', $cirugia);

        $cirugia->load(['procedimientos', 'equipoQuirurgico', 'consumos', 'equiposMedicos']);

        return Inertia::render('cirugias/edit', [
            ...$this->catalogos(),
            'cirugia' => [
                'id' => $cirugia->id,
                'paciente_id' => (string) $cirugia->paciente_id,
                'sala_operatoria_id' => (string) ($cirugia->sala_operatoria_id ?? ''),
                'fecha' => $cirugia->fecha->toDateString(),
                // El input datetime-local no acepta segundos ni zona horaria.
                'hora_ingreso_paciente' => $cirugia->hora_ingreso_paciente?->format('Y-m-d\TH:i') ?? '',
                'hora_inicio' => $cirugia->hora_inicio->format('Y-m-d\TH:i'),
                'hora_incision' => $cirugia->hora_incision?->format('Y-m-d\TH:i') ?? '',
                'hora_cierre' => $cirugia->hora_cierre?->format('Y-m-d\TH:i') ?? '',
                'hora_fin' => $cirugia->hora_fin?->format('Y-m-d\TH:i') ?? '',
                'hora_salida_recuperacion' => $cirugia->hora_salida_recuperacion?->format('Y-m-d\TH:i') ?? '',
                'tipo' => $cirugia->tipo,
                'estado' => $cirugia->estado,
                'diagnostico_cie10' => $cirugia->diagnostico_cie10 ?? '',
                'observaciones' => $cirugia->observaciones ?? '',
                'procedimientos' => $cirugia->procedimientos
                    ->map(fn (ProcedimientoQuirurgico $p): array => [
                        'id' => (string) $p->id,
                        'es_principal' => (bool) $p->pivot?->getAttribute('es_principal'),
                    ])->values(),
                'equipo' => $cirugia->equipoQuirurgico
                    ->map(fn ($miembro): array => [
                        'recurso_humano_id' => (string) $miembro->recurso_humano_id,
                        'rol' => $miembro->rol,
                        'fase' => $miembro->fase->value,
                        'hora_inicio' => $miembro->hora_inicio?->format('Y-m-d\TH:i') ?? '',
                        'hora_fin' => $miembro->hora_fin?->format('Y-m-d\TH:i') ?? '',
                        'minutos_participacion' => (string) $miembro->minutos_participacion,
                    ])->values(),
                'consumos' => $cirugia->consumos
                    ->map(fn ($consumo): array => [
                        'insumo_id' => (string) $consumo->insumo_id,
                        'fase' => $consumo->fase->value,
                        'cantidad' => rtrim(rtrim((string) $consumo->cantidad, '0'), '.'),
                    ])->values(),
                'equipos_medicos' => $cirugia->equiposMedicos
                    ->map(fn (EquipoMedico $equipo): array => [
                        'id' => (string) $equipo->id,
                        'minutos_uso' => (string) $equipo->pivot?->getAttribute('minutos_uso'),
                    ])->values(),
            ],
        ]);
    }

    public function update(
        UpdateCirugiaRequest $request,
        Cirugia $cirugia,
        ActualizarCirugia $actualizar,
        TdabcCostingService $motor,
    ): RedirectResponse {
        Gate::authorize('corregir-cirugia', $cirugia);

        $actualizar->ejecutar($cirugia, $request->validated());

        $this->sincronizarCosto($cirugia, $motor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procedimiento actualizado.']);

        // El digitador no tiene detalle costeado: vuelve a su pantalla.
        return $request->user()->isDigitador()
            ? redirect()->route('cirugias.index')
            : redirect()->route('cirugias.show', $cirugia);
    }

    /**
     * Cierre desde el listado, en los dos pasos del ciclo real:
     *
     *   1. sala  → sale de quirófano y queda «en recuperación». Todavía no se
     *              costea: el ciclo no ha terminado.
     *   2. ciclo → egresa de recuperación, queda «realizada» y se costea.
     *
     * Partirlo evita el dato falso de dar por terminado un procedimiento
     * mientras el paciente sigue en el hospital, sin dejar el registro varado:
     * «en recuperación» es visible y accionable en la bandeja de pendientes.
     */
    public function cerrar(CerrarCirugiaRequest $request, Cirugia $cirugia, TdabcCostingService $motor): RedirectResponse
    {
        Gate::authorize('corregir-cirugia', $cirugia);

        if ($request->paso() === 'sala') {
            $cirugia->update([
                'hora_fin' => $request->date('hora_fin'),
                'estado' => EstadoCirugia::EnRecuperacion->value,
            ]);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Salida de sala registrada. Queda en recuperación: se costeará al registrar el egreso.',
            ]);

            return back();
        }

        $cirugia->update([
            'hora_salida_recuperacion' => $request->date('hora_salida_recuperacion'),
            'estado' => EstadoCirugia::Realizada->value,
        ]);

        $this->sincronizarCosto($cirugia, $motor);

        Inertia::flash('toast', $this->toastCosteo(
            $cirugia,
            'Ciclo completo: procedimiento cerrado y costeado.',
        ));

        return back();
    }

    public function destroy(Cirugia $cirugia): RedirectResponse
    {
        Gate::authorize('operar-hospital');

        // Las tablas hijas (procedimientos, equipo, consumos, equipos y
        // costo) están en cascada por clave foránea.
        $cirugia->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procedimiento eliminado.']);

        return redirect()->route('cirugias.index');
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

        Inertia::flash('toast', $this->toastCosteo($cirugia, 'Costo TDABC calculado.'));

        // Vuelve a la página que pidió el cálculo (detalle de registro o
        // detalle del módulo Costeo); sin referencia, al detalle de registro.
        return redirect()->back(fallback: route('cirugias.show', $cirugia));
    }

    /**
     * Lo que este digitador capturó y todavía puede corregir: lo de hoy, más
     * cualquier registro suyo que siga abierto de días anteriores —si no,
     * un procedimiento sin cerrar quedaría varado fuera de su alcance.
     *
     * Sin costos: el digitador no los ve.
     *
     * @return list<array<string, mixed>>
     */
    protected function registrosPropios(int $usuarioId): array
    {
        return Cirugia::query()
            ->with(['paciente', 'procedimientos'])
            ->where('registrado_por', $usuarioId)
            ->where(fn (Builder $q) => $q
                ->whereDate('created_at', today())
                ->orWhereNull('hora_fin')
                ->orWhere('estado', '!=', EstadoCirugia::Realizada->value))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Cirugia $cirugia): array => [
                'id' => $cirugia->id,
                'fecha' => $cirugia->fecha->toDateString(),
                'paciente' => $cirugia->paciente?->only(['nombres', 'apellidos']),
                'procedimiento_principal' => $cirugia->procedimientoPrincipal()?->only(['codigo_cups', 'nombre']),
                'estado' => $cirugia->estado,
                'duracion_minutos' => $cirugia->duracionMinutos(),
                'puede_cerrarse' => $cirugia->pasoDeCierre() !== null,
                'paso_cierre' => $cirugia->pasoDeCierre(),
                'hora_inicio' => $cirugia->hora_inicio->format('Y-m-d\TH:i'),
                'hora_fin' => $cirugia->hora_fin?->format('Y-m-d\TH:i'),
            ])
            ->all();
    }

    /**
     * Deja el costo TDABC coherente con el estado actual del procedimiento:
     * lo recalcula si quedó «realizada» y lo borra si dejó de estarlo, para
     * que una corrección no deje un costo huérfano contaminando los KPIs.
     * Best-effort: nunca bloquea el guardado.
     */
    protected function sincronizarCosto(Cirugia $cirugia, TdabcCostingService $motor): void
    {
        if ($cirugia->estado !== EstadoCirugia::Realizada->value) {
            CostoCirugia::withoutGlobalScope(HospitalScope::class)
                ->where('cirugia_id', $cirugia->id)
                ->delete();

            // Sin costo no hay sobrecosto: dejar viva la alerta mandaría a
            // revisar un exceso que ya no existe en ningún indicador.
            AlertaSobrecosto::withoutGlobalScope(HospitalScope::class)
                ->where('cirugia_id', $cirugia->id)
                ->delete();

            return;
        }

        try {
            $motor->calcular($cirugia);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Avisa del sobrecosto en el momento en que se detecta.
     *
     * La bandeja de alertas espera a que alguien entre a mirarla; este toast
     * es lo que hace que la revisión ocurra cuando el equipo todavía recuerda
     * qué pasó en ese quirófano, que es la única ventana en que la causa se
     * puede averiguar de verdad. Por eso desplaza al mensaje de éxito en vez
     * de sumarse: enterrar la alerta bajo un «listo» sería no avisar.
     *
     * @return array{type: string, message: string}
     */
    protected function toastCosteo(Cirugia $cirugia, string $exito): array
    {
        $alerta = AlertaSobrecosto::withoutGlobalScope(HospitalScope::class)
            ->where('cirugia_id', $cirugia->id)
            ->pendientes()
            ->first();

        if ($alerta === null) {
            return ['type' => 'success', 'message' => $exito];
        }

        return [
            'type' => 'warning',
            'message' => sprintf(
                'Sobrecosto detectado: %s%% sobre lo habitual de este procedimiento, '.
                'principalmente en %s. Queda pendiente de revisión en Costeo → Alertas.',
                number_format((float) $alerta->exceso_pct * 100, 0, ',', '.'),
                mb_strtolower($alerta->componente_dominante->etiqueta()),
            ),
        ];
    }

    /**
     * Catálogos de Capa 1 que alimentan el formulario de registro y el de
     * corrección. Solo los activos: un insumo dado de baja no debe poder
     * entrar en un procedimiento nuevo.
     *
     * @return array<string, mixed>
     */
    protected function catalogos(): array
    {
        $hospital = Hospital::query()->find(HospitalContext::id());

        return [
            // El documento va descifrado para poder buscar por él en el
            // selector: identificar al paciente por cédula es como llega la
            // información al quirófano. Solo lo ve quien ya opera el hospital.
            'pacientes' => Paciente::orderBy('apellidos')
                ->get(['id', 'nombres', 'apellidos', 'tipo_documento', 'documento'])
                ->map(fn (Paciente $paciente): array => [
                    ...$paciente->only(['id', 'nombres', 'apellidos', 'tipo_documento']),
                    'documento' => $paciente->documento,
                ]),
            'salas' => SalaOperatoria::where('activa', true)->orderBy('nombre')->get(['id', 'nombre', 'costo_hora']),
            'procedimientos' => ProcedimientoQuirurgico::orderBy('nombre')
                // Los tiempos estándar del protocolo prellenan las marcas de
                // fase en el formulario: el digitador corrige la excepción.
                ->with(ProcedimientoQuirurgico::RELACIONES_PLANTILLA)
                ->get([
                    'id', 'codigo_cups', 'nombre', 'duracion_estimada_minutos',
                    'minutos_prequirurgico', 'minutos_recuperacion',
                ])
                ->map(fn (ProcedimientoQuirurgico $procedimiento): array => [
                    ...$procedimiento->only([
                        'id', 'codigo_cups', 'nombre', 'duracion_estimada_minutos',
                        'minutos_prequirurgico', 'minutos_recuperacion',
                    ]),
                    // La plantilla del protocolo: con ella nace prellenado el
                    // registro, y contra ella se compara lo que de verdad se
                    // usó. Las líneas se envían como texto porque así entran
                    // directo a los campos del formulario.
                    'plantilla' => [
                        'insumos' => $procedimiento->plantillaInsumos
                            ->map(fn (PlantillaInsumo $fila): array => [
                                'insumo_id' => (string) $fila->insumo_id,
                                'fase' => $fila->fase->value,
                                'cantidad' => rtrim(rtrim((string) $fila->cantidad, '0'), '.'),
                                'opcional' => $fila->opcional,
                            ])->values(),
                        'personal' => $procedimiento->plantillaPersonal
                            ->map(fn (PlantillaPersonal $fila): array => [
                                'rol' => $fila->rol,
                                'fase' => $fila->fase->value,
                                'cantidad' => $fila->cantidad,
                                'recurso_humano_id' => (string) ($fila->recurso_humano_id ?? ''),
                                'minutos' => (string) ($fila->minutos ?? ''),
                                'opcional' => $fila->opcional,
                            ])->values(),
                        'equipos' => $procedimiento->plantillaEquipos
                            ->map(fn (PlantillaEquipo $fila): array => [
                                'equipo_medico_id' => (string) $fila->equipo_medico_id,
                                'minutos_uso' => (string) ($fila->minutos_uso ?? ''),
                                'opcional' => $fila->opcional,
                            ])->values(),
                    ],
                ]),
            // costo_mensual permite estimar el costo TDABC en vivo dentro del
            // formulario, con la misma fórmula del motor de costeo.
            'recursos' => RecursoHumano::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'rol', 'especialidad', 'salario_mensual', 'prestaciones_mensuales', 'costos_indirectos_mensuales'])
                ->map(fn (RecursoHumano $recurso): array => [
                    ...$recurso->only(['id', 'nombre', 'rol', 'especialidad']),
                    'costo_mensual' => $recurso->costoMensualTotal(),
                ]),
            'insumos' => Insumo::where('activo', true)->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'unidad', 'costo_unitario']),
            'equiposMedicos' => EquipoMedico::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'costo_hora']),
            'tipos' => TipoCirugia::values(),
            'estados' => EstadoCirugia::values(),
            'rolesQuirurgicos' => RolQuirurgico::values(),
            'fases' => FaseCiclo::values(),
            'regimenes' => Regimen::values(),
            'parametrosTdabc' => [
                'minutos_disponibles_mes' => $hospital?->minutosDisponiblesMes(),
                'factor_indirecto' => $hospital !== null ? (float) $hospital->factor_indirecto : null,
            ],
        ];
    }
}
