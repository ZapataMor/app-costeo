<?php

namespace Database\Seeders;

use App\Enums\CategoriaCif;
use App\Enums\EstadoCirugia;
use App\Models\Cirugia;
use App\Models\ConceptoCostoIndirecto;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\ResultadoClinico;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Services\Cirugias\RegistrarCirugia;
use App\Services\Costing\ActivarCategoriaCif;
use App\Services\Costing\DetectorSobrecostos;
use App\Services\Costing\TdabcCostingService;
use App\Services\Indicators\KpiService;
use App\Support\HospitalContext;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Movimiento de volumen para el Hospital San Rafael: pacientes, cirugías,
 * costeo, facturación y resultados clínicos del 2026-04-01 al 2026-10-18.
 *
 * Se apoya en el catálogo de SanRafaelCargaSeeder y lo recorre día a día como
 * lo haría el hospital:
 *
 *   - La demanda sale de `datos/san-rafael/demanda.php` (~330 casos/mes):
 *     programadas de lunes a sábado desde las 07:00, urgencias a cualquier
 *     hora y cualquier día, cada caso en su sala habitual.
 *   - Cada cirugía se registra con RegistrarCirugia —el mismo servicio de la
 *     aplicación—, así que congela tarifas y parámetros CIF como un registro
 *     real. Insumos, equipos y personal salen del protocolo con variación.
 *   - Cada cirujano tiene su propio ritmo (0,85–1,20 del tiempo estándar):
 *     es lo que hace legible la variabilidad por profesional.
 *   - ~4 % de casos con complicación (más tiempo, más insumos, más estancia)
 *     alimentan las alertas de sobrecosto de forma natural.
 *   - El 2026-07-01 el hospital pasa del costo directo a las bolsas CIF
 *     (ActivarCategoriaCif): abril–junio se costean por la vía directa y de
 *     julio en adelante por bolsas, para poder comparar ambos métodos.
 *   - Lo operado el último día queda «en recuperación», sin costear; ~2,5 %
 *     de las programadas se cancelan.
 *
 * Determinista (semilla fija del generador): dos corridas sobre una base
 * limpia producen los mismos datos. No es acumulativo: si ya hay movimiento
 * de esta carga, no hace nada.
 *
 *   php artisan db:seed --class=SanRafaelMovimientoSeeder
 *
 * Pacientes y cirugías son ficticios; la cirugía lleva [SEMILLA-CARGA] en sus
 * observaciones y el documento de los pacientes empieza por 1030.
 */
class SanRafaelMovimientoSeeder extends Seeder
{
    use InformaEnConsola;

    public const DESDE = '2026-04-01';

    public const HASTA = '2026-10-18';

    public const CAMBIO_A_BOLSAS = '2026-07-01';

    public const PREFIJO_DOCUMENTO = 1_030_000_000;

    private const SEMILLA_ALEATORIA = 20_261_019;

    /** Insumos que se suman al protocolo cuando la cirugía se complica. */
    private const EXTRAS_COMPLICACION = ['MAT-055' => 2, 'MAT-007' => 2, 'MED-052' => 2, 'MAT-043' => 1, 'MED-062' => 4, 'MED-034' => 1];

    /** Consumos no previstos en el protocolo (un ~6 % de los casos). */
    private const EXTRAS_IMPREVISTOS = ['MAT-002' => 5, 'MAT-008' => 1, 'MED-017' => 1, 'MED-033' => 1, 'MAT-058' => 1, 'MED-014' => 1];

    /** Equipos que acompañan toda la estancia en sala. */
    private const EQUIPOS_DE_SALA = ['EQ-ANE-', 'EQ-MON-', 'EQ-ASP-', 'EQ-003', 'EQ-CAL-', 'EQ-CUN-'];

    /** Equipos que solo se usan de incisión a cierre. */
    private const EQUIPOS_DE_CORTE = ['EQ-001', 'EQ-ELB-'];

    /** Periodo sembrado; los tests lo acotan para no sembrar seis meses. */
    public string $desde = self::DESDE;

    public string $hasta = self::HASTA;

    private Hospital $hospital;

    /** @var array<string, ProcedimientoQuirurgico> */
    private array $procedimientos = [];

    /** @var array<string, int> */
    private array $salas = [];

    /** @var array<string, list<int>> */
    private array $personalPorRol = [];

    /** @var array<string, list<int>> */
    private array $cirujanosPorEspecialidad = [];

    /** @var array<int, float> */
    private array $ritmoCirujano = [];

    /** @var list<int> */
    private array $digitadores = [];

    /** @var array<int, float> */
    private array $estacionalidad = [];

    /** @var array<int, true> */
    private array $complicadas = [];

    private int $consecutivoPaciente = 0;

    public function run(
        RegistrarCirugia $registrar,
        TdabcCostingService $motor,
        DetectorSobrecostos $detector,
        ActivarCategoriaCif $activar,
    ): void {
        $hospital = Hospital::query()->where('nit', SanRafaelCargaSeeder::NIT)->first();

        if ($hospital === null) {
            $this->advertir('Sin Hospital San Rafael: ejecuta HospitalSeeder y SanRafaelCargaSeeder primero.');

            return;
        }

        $this->hospital = $hospital;
        $anterior = HospitalContext::id();
        HospitalContext::set($hospital->id);

        try {
            if ($this->yaSembrado()) {
                $this->advertir('San Rafael ya tiene el movimiento de esta carga: no se siembra de nuevo.');

                return;
            }

            if (! $this->cargarContexto()) {
                return;
            }

            mt_srand(self::SEMILLA_ALEATORIA);
            $bolsasActivas = ConceptoCostoIndirecto::query()->where('activo', true)->exists();

            if ($bolsasActivas) {
                $this->advertir('Ya hay bolsas CIF activas: todo el periodo se costeará por bolsas.');
            }

            $registradas = 0;
            $hasta = CarbonImmutable::parse($this->hasta);

            for ($dia = CarbonImmutable::parse($this->desde); $dia->lessThanOrEqualTo($hasta); $dia = $dia->addDay()) {
                if (! $bolsasActivas && $dia->toDateString() === self::CAMBIO_A_BOLSAS) {
                    $this->activarBolsas($activar);
                    $bolsasActivas = true;
                }

                $agenda = $this->programarDia($dia);

                DB::transaction(function () use ($agenda, $registrar, $dia, $hasta, &$registradas): void {
                    foreach ($agenda as $caso) {
                        $this->registrarCaso($registrar, $caso, $dia->equalTo($hasta));
                        $registradas++;
                    }
                });
            }

            $this->informar("Cirugías registradas: {$registradas}. Costeando…");

            $resultado = (new CosteoSeeder)->costear($hospital, $motor, $detector);

            $this->sembrarFacturacionYResultados();
            $this->resumir($resultado['costeadas'], $resultado['alertas']);
        } finally {
            HospitalContext::set($anterior);
        }
    }

    protected function yaSembrado(): bool
    {
        return Cirugia::query()->where('observaciones', 'like', '%'.SanRafaelCargaSeeder::MARCA.'%')->exists();
    }

    protected function cargarContexto(): bool
    {
        $this->procedimientos = ProcedimientoQuirurgico::query()
            ->with(['plantillaInsumos.insumo', 'plantillaEquipos.equipoMedico', 'plantillaPersonal'])
            ->get()
            ->keyBy('codigo_cups')
            ->all();

        $this->salas = SalaOperatoria::query()->pluck('id', 'nombre')->all();

        foreach (RecursoHumano::query()->where('activo', true)->orderBy('id')->get() as $recurso) {
            $this->personalPorRol[$recurso->rol][] = $recurso->id;

            if ($recurso->rol === 'cirujano' && $recurso->especialidad !== null) {
                $this->cirujanosPorEspecialidad[$recurso->especialidad][] = $recurso->id;
            }
        }

        $this->digitadores = array_values(User::query()
            ->where('hospital_id', $this->hospital->id)
            ->where('role', 'digitador')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());

        if (count($this->salas) < 5 || count($this->procedimientos) < 50) {
            $this->advertir('Falta el catálogo de volumen: ejecuta SanRafaelCargaSeeder primero.');

            return false;
        }

        // El ritmo se asigna una vez y en orden de id: es estable entre corridas.
        mt_srand(self::SEMILLA_ALEATORIA + 1);
        foreach ($this->personalPorRol['cirujano'] ?? [] as $id) {
            $this->ritmoCirujano[$id] = mt_rand(85, 120) / 100;
        }

        return true;
    }

    /**
     * Activa las cinco categorías. El usuario de la carga ya decidió que los
     * componentes directos equivalentes pasen a derivarse de las bolsas.
     */
    protected function activarBolsas(ActivarCategoriaCif $activar): void
    {
        foreach (CategoriaCif::cases() as $categoria) {
            $activar->ejecutar($this->hospital->refresh(), $categoria, confirmado: true);
        }

        $this->informar('2026-07-01: bolsas CIF activadas; desde aquí el costeo va por bolsas.');
    }

    /**
     * Casos del día con su hora de entrada a sala.
     *
     * Programadas: de lunes a sábado, encadenadas en su sala desde las 07:00
     * con el recambio de por medio. Urgencias: a cualquier hora; si su sala
     * está ocupada entran al terminar el caso en curso.
     *
     * @return list<array<string, mixed>>
     */
    protected function programarDia(CarbonImmutable $dia): array
    {
        /** @var list<array{0: string, 1: int, 2: string, 3: string, 4: string, 5: float}> $demanda */
        $demanda = $this->datos('demanda');
        $esDomingo = $dia->isSunday();
        $factorMes = $this->estacionalidad[$dia->month] ??= mt_rand(90, 110) / 100;

        $programadas = [];
        $urgencias = [];

        foreach ($demanda as [$cups, $mensual, $sala, $cie10, $perfil, $proporcionUrgencias]) {
            $base = ['cups' => $cups, 'sala' => $sala, 'cie10' => $cie10, 'perfil' => $perfil];

            $lambdaProgramadas = $esDomingo ? 0.0 : $mensual * (1 - $proporcionUrgencias) * $factorMes / 26;
            for ($i = $this->poisson($lambdaProgramadas); $i > 0; $i--) {
                $programadas[] = $this->caso($base, false);
            }

            for ($i = $this->poisson($mensual * $proporcionUrgencias * $factorMes / 30.4); $i > 0; $i--) {
                // Más urgencias de día que de madrugada.
                $minuto = mt_rand(1, 100) <= 75 ? mt_rand(7 * 60, 22 * 60) : mt_rand(0, 7 * 60 - 1);
                $urgencias[] = $this->caso($base, true) + ['minuto' => $minuto];
            }
        }

        usort($urgencias, fn (array $a, array $b) => $a['minuto'] <=> $b['minuto']);
        shuffle($programadas);

        $apertura = $dia->setTime(7, 0);
        $cursor = [];
        $agenda = [];

        // Urgencias de madrugada: retrasan el arranque de la jornada.
        foreach ($urgencias as $urgencia) {
            if ($urgencia['minuto'] >= 7 * 60) {
                continue;
            }
            $inicio = $this->maximo($dia->addMinutes($urgencia['minuto']), $cursor[$urgencia['sala']] ?? $dia);
            $agenda[] = $urgencia + ['inicio' => $inicio];
            $cursor[$urgencia['sala']] = $inicio->addMinutes($urgencia['duracion'] + $urgencia['recambio']);
        }

        foreach ($programadas as $caso) {
            $inicio = $this->maximo($cursor[$caso['sala']] ?? $apertura, $apertura)->addMinutes(mt_rand(0, 10));
            $agenda[] = $caso + ['inicio' => $inicio];
            $cursor[$caso['sala']] = $inicio->addMinutes($caso['duracion'] + $caso['recambio']);
        }

        foreach ($urgencias as $urgencia) {
            if ($urgencia['minuto'] < 7 * 60) {
                continue;
            }
            $inicio = $this->maximo($dia->addMinutes($urgencia['minuto']), $cursor[$urgencia['sala']] ?? $dia);
            $agenda[] = $urgencia + ['inicio' => $inicio];
            $cursor[$urgencia['sala']] = $inicio->addMinutes($urgencia['duracion'] + $urgencia['recambio']);
        }

        // Se registran en orden cronológico, como llegan al sistema.
        usort($agenda, fn (array $a, array $b) => $a['inicio'] <=> $b['inicio']);

        return $agenda;
    }

    /**
     * Sortea lo que define al caso antes de agendarlo: cirujano, si se
     * complica y cuánto dura.
     *
     * @param  array{cups: string, sala: string, cie10: string, perfil: string}  $base
     * @return array<string, mixed>
     */
    protected function caso(array $base, bool $urgencia): array
    {
        $procedimiento = $this->procedimientos[$base['cups']];
        $cirujanos = $this->cirujanosPorEspecialidad[$procedimiento->especialidad] ?? $this->personalPorRol['cirujano'];
        $cirujano = $cirujanos[mt_rand(0, count($cirujanos) - 1)];

        $complicada = mt_rand(1, 1000) <= ($urgencia ? 70 : 30);
        $cancelada = ! $urgencia && mt_rand(1, 1000) <= 25;

        // Distribución sesgada a la derecha: una cirugía se alarga más de lo
        // que se acorta.
        $variacion = (mt_rand(85, 115) + max(0, mt_rand(-20, 35))) / 100;
        $duracion = $procedimiento->duracion_estimada_minutos
            * $this->ritmoCirujano[$cirujano]
            * $variacion
            * ($urgencia ? 1.08 : 1.0)
            * ($complicada ? mt_rand(150, 190) / 100 : 1.0);

        return $base + [
            'urgencia' => $urgencia,
            'cirujano' => $cirujano,
            'complicada' => $complicada,
            'cancelada' => $cancelada,
            'duracion' => max(10, (int) round($duracion)),
            'recambio' => $procedimiento->minutos_recambio ?? 20,
        ];
    }

    /**
     * @param  array<string, mixed>  $caso
     */
    protected function registrarCaso(RegistrarCirugia $registrar, array $caso, bool $ultimoDia): void
    {
        /** @var CarbonImmutable $inicio */
        $inicio = $caso['inicio'];
        /** @var int $duracion */
        $duracion = $caso['duracion'];
        $procedimiento = $this->procedimientos[(string) $caso['cups']];
        $paciente = $this->crearPaciente((string) $caso['perfil'], $inicio);

        $datos = [
            'registrado_por' => $this->digitadores === [] ? null : $this->digitadores[mt_rand(0, count($this->digitadores) - 1)],
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $this->salas[(string) $caso['sala']],
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio,
            'tipo' => $caso['urgencia'] ? 'urgencia' : 'programada',
            'diagnostico_cie10' => $caso['cie10'],
            'observaciones' => 'Carga de prueba '.SanRafaelCargaSeeder::MARCA,
            'procedimientos' => [['id' => $procedimiento->id, 'es_principal' => true]],
        ];

        if ($caso['cancelada']) {
            $registrar->ejecutar($datos + [
                'estado' => EstadoCirugia::Cancelada->value,
                'observaciones' => 'Cancelada antes de la inducción (ayuno incompleto o falta de reserva de sangre). '.SanRafaelCargaSeeder::MARCA,
            ]);

            return;
        }

        $tiempos = $this->tiempos($procedimiento, $inicio, $duracion);

        $cirugia = $registrar->ejecutar($datos + [
            'estado' => $ultimoDia ? EstadoCirugia::EnRecuperacion->value : EstadoCirugia::Realizada->value,
            'hora_ingreso_paciente' => $tiempos['ingreso'],
            'hora_incision' => $tiempos['incision'],
            'hora_cierre' => $tiempos['cierre'],
            'hora_fin' => $tiempos['fin'],
            'hora_salida_recuperacion' => $ultimoDia ? null : $tiempos['salida'],
            'observaciones' => ($caso['complicada'] ? 'Complicación intraoperatoria: sangrado mayor al esperado. ' : '')
                .'Carga de prueba '.SanRafaelCargaSeeder::MARCA,
            'equipo' => $this->equipoHumano($procedimiento, (int) $caso['cirujano'], $tiempos, $duracion),
            'consumos' => $this->consumos($procedimiento, $duracion, (bool) $caso['complicada']),
            'equipos_medicos' => $this->equiposMedicos($procedimiento, $tiempos, $duracion),
        ]);

        if ($caso['complicada']) {
            $this->complicadas[$cirugia->id] = true;
        }
    }

    /**
     * Marcas del ciclo. La inducción y la educción dependen de la anestesia,
     * que se lee del protocolo: tubo endotraqueal = general, aguja espinal =
     * raquídea, lo demás local o sedación.
     *
     * @return array{ingreso: CarbonImmutable, incision: CarbonImmutable, cierre: CarbonImmutable, fin: CarbonImmutable, salida: CarbonImmutable, induccion: int}
     */
    protected function tiempos(ProcedimientoQuirurgico $procedimiento, CarbonImmutable $inicio, int $duracion): array
    {
        $codigos = $procedimiento->plantillaInsumos->map(fn ($linea) => $linea->insumo?->codigo)->all();
        [$induccion, $educcion] = match (true) {
            array_intersect(['DIS-012', 'DIS-013'], $codigos) !== [] => [15, 10],
            array_intersect(['DIS-022', 'DIS-023'], $codigos) !== [] => [12, 5],
            default => [5, 3],
        };
        $induccion = min($induccion, intdiv($duracion, 3));
        $educcion = min($educcion, intdiv($duracion, 4));

        $fin = $inicio->addMinutes($duracion);
        $pre = (int) round(($procedimiento->minutos_prequirurgico ?? 30) * mt_rand(80, 140) / 100);
        $recuperacion = (int) round(($procedimiento->minutos_recuperacion ?? 90) * mt_rand(80, 140) / 100);

        return [
            'ingreso' => $inicio->subMinutes($pre),
            'incision' => $inicio->addMinutes($induccion),
            'cierre' => $fin->subMinutes($educcion),
            'fin' => $fin,
            'salida' => $fin->addMinutes($recuperacion),
            'induccion' => $induccion,
        ];
    }

    /**
     * Cirujano y ayudante cuentan de incisión a cierre (más el lavado); el
     * resto del equipo, toda la estancia en sala.
     *
     * @param  array{ingreso: CarbonImmutable, incision: CarbonImmutable, cierre: CarbonImmutable, fin: CarbonImmutable, salida: CarbonImmutable, induccion: int}  $tiempos
     * @return list<array<string, mixed>>
     */
    protected function equipoHumano(ProcedimientoQuirurgico $procedimiento, int $cirujano, array $tiempos, int $duracion): array
    {
        $operando = (int) $tiempos['incision']->diffInMinutes($tiempos['cierre']);
        $equipo = [];

        foreach ($procedimiento->plantillaPersonal as $linea) {
            $candidatos = $linea->rol === 'cirujano' ? [$cirujano] : ($this->personalPorRol[$linea->rol] ?? []);

            if ($linea->rol !== 'cirujano') {
                shuffle($candidatos);
            }

            foreach (array_slice($candidatos, 0, $linea->cantidad) as $recurso) {
                $minutos = match ($linea->rol) {
                    'cirujano' => $operando + mt_rand(5, 15),
                    'ayudante' => $operando + mt_rand(0, 10),
                    default => $duracion,
                };

                $equipo[] = [
                    'recurso_humano_id' => $recurso,
                    'rol' => $linea->rol,
                    'fase' => $linea->fase->value,
                    'minutos_participacion' => max(5, min(1440, $minutos)),
                ];
            }
        }

        return $equipo;
    }

    /**
     * El protocolo con variación: casi nunca se gasta exactamente lo
     * previsto. Los fraccionarios (gases) escalan con la duración real.
     *
     * @return list<array{insumo_id: int, fase: string, cantidad: float|int}>
     */
    protected function consumos(ProcedimientoQuirurgico $procedimiento, int $duracion, bool $complicada): array
    {
        $porClave = [];
        $codigos = [];

        foreach ($procedimiento->plantillaInsumos as $linea) {
            if ($linea->insumo === null || mt_rand(1, 100) <= 4) {
                continue;
            }

            $cantidad = (float) $linea->cantidad;
            $consumido = $cantidad < 1
                ? round(max(0.05, $cantidad * $duracion / max(1, $procedimiento->duracion_estimada_minutos)), 2)
                : max(1, (int) round($cantidad * mt_rand(85, 125) / 100));

            $porClave[$linea->insumo_id.'|'.$linea->fase->value] = [
                'insumo_id' => $linea->insumo_id,
                'fase' => $linea->fase->value,
                'cantidad' => $consumido,
            ];
            $codigos[$linea->insumo->codigo] = $linea->insumo_id;
        }

        $extras = $complicada ? self::EXTRAS_COMPLICACION : [];
        if (mt_rand(1, 100) <= 6) {
            $codigo = array_rand(self::EXTRAS_IMPREVISTOS);
            $extras[$codigo] = ($extras[$codigo] ?? 0) + self::EXTRAS_IMPREVISTOS[$codigo];
        }

        foreach ($extras as $codigo => $cantidad) {
            $insumoId = $codigos[$codigo] ?? $this->insumoId($codigo);
            $clave = $insumoId.'|quirurgica';
            $previo = $porClave[$clave]['cantidad'] ?? 0;

            $porClave[$clave] = ['insumo_id' => $insumoId, 'fase' => 'quirurgica', 'cantidad' => $previo + $cantidad];
        }

        return array_values($porClave);
    }

    /**
     * @param  array{ingreso: CarbonImmutable, incision: CarbonImmutable, cierre: CarbonImmutable, fin: CarbonImmutable, salida: CarbonImmutable, induccion: int}  $tiempos
     * @return list<array{id: int, minutos_uso: int}>
     */
    protected function equiposMedicos(ProcedimientoQuirurgico $procedimiento, array $tiempos, int $duracion): array
    {
        $operando = (int) $tiempos['incision']->diffInMinutes($tiempos['cierre']);
        $equipos = [];

        foreach ($procedimiento->plantillaEquipos as $linea) {
            $codigo = (string) $linea->equipoMedico?->codigo;

            $minutos = match (true) {
                $this->empiezaPor($codigo, self::EQUIPOS_DE_SALA) => $duracion,
                $this->empiezaPor($codigo, self::EQUIPOS_DE_CORTE) => $operando,
                default => (int) round($operando * mt_rand(60, 95) / 100),
            };

            $equipos[] = ['id' => $linea->equipo_medico_id, 'minutos_uso' => max(5, min(1440, $minutos))];
        }

        return $equipos;
    }

    /**
     * @param  list<string>  $prefijos
     */
    protected function empiezaPor(string $codigo, array $prefijos): bool
    {
        foreach ($prefijos as $prefijo) {
            if (str_starts_with($codigo, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    /** @var array<string, int> */
    private array $cacheInsumos = [];

    protected function insumoId(string $codigo): int
    {
        return $this->cacheInsumos[$codigo] ??= (int) DB::table('insumos')
            ->where('hospital_id', $this->hospital->id)
            ->where('codigo', $codigo)
            ->value('id');
    }

    // ── Pacientes ─────────────────────────────────────────────────────────

    /** @var list<string> */
    private const NOMBRES_F = [
        'María José', 'Luz Marina', 'Yorlenis', 'Deisy', 'Katherine', 'Shirley', 'Nelcy', 'Aura Rosa',
        'Yaneth', 'Dayana', 'Marielis', 'Sindy', 'Roselia', 'Anais', 'Elvira', 'Yulieth', 'Karina',
        'Zoraida', 'Leidis', 'Mileidys', 'Yesenia', 'Rosa Elena', 'Ana Isabel', 'Daniela', 'Valentina',
        'Camila', 'Yuranis', 'Keila', 'Eulalia', 'Lorena', 'Nayibe', 'Wendy', 'Arelis', 'Yarlenis',
        'Magalys', 'Carmen Alicia', 'Ingrid', 'Yolima', 'Dilia', 'Sofía',
    ];

    /** @var list<string> */
    private const NOMBRES_M = [
        'José Gregorio', 'Wilmer', 'Alfonso', 'Deiver', 'Jhon Jairo', 'Eder', 'Ramiro', 'Alexander',
        'Uriel', 'Wilfrido', 'Yeison', 'Nairo', 'Everardo', 'Adalberto', 'Hugo', 'Amaury', 'Luis Carlos',
        'Jesús David', 'Rafael', 'Hernando', 'Jorge Luis', 'Kevin', 'Brayan', 'Sebastián', 'Santiago',
        'Juan Camilo', 'Edinson', 'Orlando', 'Remberto', 'Álvaro', 'Pedro Manuel', 'Fredy', 'Aníbal',
        'Manuel Antonio', 'Carlos Arturo', 'Yonis', 'Neider', 'Rubén', 'Ismael', 'Matías',
    ];

    /** @var list<string> */
    private const APELLIDOS = [
        'Epiayú', 'Uriana', 'Pushaina', 'Ipuana', 'Jusayú', 'Arpushana', 'Epinayú', 'Gouriyú', 'Sapuana',
        'Uriana', 'Pana', 'Bouriyú', 'Jayariyú', 'Iguarán', 'Gámez', 'Redondo', 'Brito', 'Solano',
        'Mengual', 'Ojeda', 'Freyle', 'Deluque', 'Cotes', 'Movil', 'Choles', 'Curvelo', 'Barros',
        'Mejía', 'Zuleta', 'Daza', 'Fince', 'Pimienta', 'Toncel', 'Bolaño', 'Vence', 'Ospino',
        'Amaya', 'Peralta', 'Fragozo', 'Aragón', 'Rois', 'Pinto', 'Cuello', 'Romero', 'Gómez',
        'Rodríguez', 'Martínez', 'Pérez', 'González', 'Hernández', 'Castillo', 'Rangel', 'Medina',
        'Cardona', 'Suárez', 'Montiel', 'Polanco', 'Arregocés', 'Zúñiga', 'Molina',
    ];

    /** @var array<string, int> municipio => peso */
    private const MUNICIPIOS = [
        'Maicao' => 55, 'Uribia' => 14, 'Manaure' => 10, 'Riohacha' => 7, 'Albania' => 6,
        'Hatonuevo' => 3, 'Fonseca' => 3, 'Dibulla' => 2,
    ];

    /** @var list<string> */
    private const EPS_SUBSIDIADO = ['Cajacopi', 'Anas Wayuu', 'Dusakawi', 'Coosalud', 'Mutual Ser', 'Nueva EPS'];

    /** @var list<string> */
    private const EPS_CONTRIBUTIVO = ['Nueva EPS', 'Sanitas', 'Salud Total', 'Sura', 'Coosalud'];

    /**
     * Paciente con el perfil demográfico del procedimiento: gestantes jóvenes
     * para la obstetricia, hombres mayores para la próstata, niños para la
     * amigdalectomía… En Maicao, frontera con Venezuela, ~7 % son migrantes
     * con Permiso por Protección Temporal.
     */
    protected function crearPaciente(string $perfil, CarbonImmutable $fecha): Paciente
    {
        [$sexo, $edadMin, $edadMax] = match ($perfil) {
            'gestante' => ['F', 16, 40],
            'mujer' => ['F', 20, 50],
            'mujer_mayor' => ['F', 40, 72],
            'hombre' => ['M', 18, 65],
            'hombre_mayor' => ['M', 55, 88],
            'nino' => [mt_rand(0, 1) === 1 ? 'F' : 'M', 2, 12],
            'nino_varon' => ['M', 1, 12],
            'joven' => [mt_rand(1, 100) <= 50 ? 'F' : 'M', 8, 40],
            'trauma' => [mt_rand(1, 100) <= 70 ? 'M' : 'F', 15, 65],
            'adulto_mayor' => [mt_rand(1, 100) <= 50 ? 'F' : 'M', 55, 85],
            'anciano' => [mt_rand(1, 100) <= 55 ? 'F' : 'M', 55, 88],
            default => [mt_rand(1, 100) <= 55 ? 'F' : 'M', 18, 75],
        };

        // Sesgo hacia el extremo joven del rango: la pirámide de La Guajira
        // es ancha en la base.
        $edad = $edadMin + (int) floor(($edadMax - $edadMin + 1) * (mt_rand(0, 1000) / 1000) ** 1.3);
        $edad = min($edad, $edadMax);

        $migrante = mt_rand(1, 100) <= 7;
        $regimen = match (true) {
            $migrante => mt_rand(1, 100) <= 60 ? 'subsidiado' : 'no_asegurado',
            default => $this->sortearPeso(['subsidiado' => 74, 'contributivo' => 22, 'especial' => 4]),
        };
        $asegurador = match ($regimen) {
            'subsidiado' => self::EPS_SUBSIDIADO[mt_rand(0, count(self::EPS_SUBSIDIADO) - 1)],
            'contributivo' => self::EPS_CONTRIBUTIVO[mt_rand(0, count(self::EPS_CONTRIBUTIVO) - 1)],
            'especial' => mt_rand(0, 1) === 1 ? 'Fomag (Magisterio)' : 'Sanidad Militar',
            default => null,
        };

        $nombres = $sexo === 'F' ? self::NOMBRES_F : self::NOMBRES_M;
        $this->consecutivoPaciente++;

        return Paciente::create([
            'hospital_id' => $this->hospital->id,
            'tipo_documento' => match (true) {
                $migrante => 'PT',
                $edad < 7 => 'RC',
                $edad < 18 => 'TI',
                default => 'CC',
            },
            'documento' => (string) (self::PREFIJO_DOCUMENTO + $this->consecutivoPaciente),
            'nombres' => $nombres[mt_rand(0, count($nombres) - 1)],
            'apellidos' => self::APELLIDOS[mt_rand(0, count(self::APELLIDOS) - 1)].' '.self::APELLIDOS[mt_rand(0, count(self::APELLIDOS) - 1)],
            'fecha_nacimiento' => $fecha->subYears($edad)->subDays(mt_rand(0, 364))->toDateString(),
            'sexo' => $sexo,
            'regimen' => $regimen,
            'asegurador' => $asegurador,
            'zona' => mt_rand(1, 100) <= 35 ? 'rural' : 'urbana',
            'municipio' => $migrante ? 'Maicao' : $this->sortearPeso(self::MUNICIPIOS),
        ]);
    }

    // ── Facturación y resultados ──────────────────────────────────────────

    /**
     * Factura a la tarifa SOAT −25 % con un margen por ítems adicionales,
     * con glosas en ~12 % de las cuentas y un recaudo que depende de la
     * antigüedad de la factura y del régimen. Lo de las últimas semanas aún
     * no está facturado del todo, como en la vida real.
     */
    protected function sembrarFacturacionYResultados(): void
    {
        $corte = CarbonImmutable::parse($this->hasta)->addDay();

        Cirugia::query()
            ->where('observaciones', 'like', '%'.SanRafaelCargaSeeder::MARCA.'%')
            ->has('costo')
            ->with(['procedimientos', 'paciente'])
            ->chunkById(200, function ($cirugias) use ($corte): void {
                DB::transaction(function () use ($cirugias, $corte): void {
                    foreach ($cirugias as $cirugia) {
                        $this->registrarResultado($cirugia);
                        $this->facturar($cirugia, $corte);
                    }
                });
            });
    }

    protected function registrarResultado(Cirugia $cirugia): void
    {
        $complicada = isset($this->complicadas[$cirugia->id]);

        if (! $complicada && mt_rand(1, 100) > 93) {
            return; // seguimiento sin diligenciar
        }

        $procedimiento = $cirugia->procedimientoPrincipal();
        [$min, $max] = match ($procedimiento?->complejidad) {
            'alta' => [2, 5],
            'media' => [1, 3],
            default => [0, 1],
        };
        $infeccion = mt_rand(1, 1000) <= ($complicada ? 150 : 25);

        ResultadoClinico::create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'complicacion_intraoperatoria' => $complicada,
            'descripcion_complicacion_intra' => $complicada ? 'Sangrado mayor al esperado; requirió hemostasia adicional.' : null,
            'complicacion_postoperatoria' => $infeccion,
            'descripcion_complicacion_post' => $infeccion ? 'Infección del sitio operatorio.' : null,
            'dias_estancia' => mt_rand($min, $max) + ($complicada ? mt_rand(3, 9) : 0) + ($infeccion ? mt_rand(2, 6) : 0),
            'reingreso_30_dias' => mt_rand(1, 1000) <= ($infeccion ? 300 : 20),
            'mortalidad' => mt_rand(1, 10000) <= ($complicada && $procedimiento?->complejidad === 'alta' ? 600 : 15),
        ]);
    }

    protected function facturar(Cirugia $cirugia, CarbonImmutable $corte): void
    {
        $procedimiento = $cirugia->procedimientoPrincipal();
        $fecha = CarbonImmutable::parse($cirugia->fecha);

        if ($procedimiento?->tarifa_soat === null) {
            return;
        }

        $fechaFactura = $fecha->addDays(mt_rand(3, 20));
        $probabilidad = $fecha->lessThan('2026-09-01') ? 96 : 70;

        if ($fechaFactura->greaterThanOrEqualTo($corte) || mt_rand(1, 100) > $probabilidad) {
            return;
        }

        $tarifa = (float) $procedimiento->tarifa_soat;
        $facturado = round($tarifa * KpiService::FACTOR_REFERENCIA_SOAT * mt_rand(98, 112) / 100, 2);
        $glosado = mt_rand(1, 100) <= 12 ? round($facturado * mt_rand(5, 25) / 100, 2) : 0.0;

        $antiguedad = (int) $fechaFactura->diffInDays($corte);
        [$min, $max] = match (true) {
            $antiguedad < 30 => [0, 30],
            $antiguedad < 90 => [40, 85],
            default => [70, 100],
        };
        $factorRegimen = $cirugia->paciente?->regimen === 'contributivo' ? 1.0 : 0.9;
        $recaudado = round(($facturado - $glosado) * min(1, mt_rand($min, $max) / 100 * $factorRegimen), 2);

        Facturacion::create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'valor_facturado' => $facturado,
            'valor_glosado' => $glosado,
            'valor_recaudado' => $recaudado,
            'tarifa_referencia_soat' => $tarifa,
            'fecha_facturacion' => $fechaFactura->toDateString(),
        ]);
    }

    // ── Utilidades ────────────────────────────────────────────────────────

    /** Variable de Poisson (Knuth): llegadas de casos en un día. */
    protected function poisson(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }

        $limite = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $limite);

        return $k - 1;
    }

    /**
     * @template T of string
     *
     * @param  array<T, int>  $pesos
     * @return T
     */
    protected function sortearPeso(array $pesos): string
    {
        $tiro = mt_rand(1, array_sum($pesos));

        foreach ($pesos as $valor => $peso) {
            $tiro -= $peso;
            if ($tiro <= 0) {
                return $valor;
            }
        }

        return array_key_first($pesos);
    }

    protected function maximo(CarbonImmutable $a, CarbonImmutable $b): CarbonImmutable
    {
        return $a->greaterThan($b) ? $a : $b;
    }

    protected function resumir(int $costeadas, int $alertas): void
    {
        $marca = '%'.SanRafaelCargaSeeder::MARCA.'%';
        $deLaCarga = fn () => Cirugia::query()->where('observaciones', 'like', $marca);

        $this->tabla(['Movimiento de San Rafael', 'Total'], [
            ['Pacientes nuevos', (string) $this->consecutivoPaciente],
            ['Cirugías registradas', (string) $deLaCarga()->count()],
            ['  realizadas', (string) $deLaCarga()->where('estado', 'realizada')->count()],
            ['  en recuperación (sin costear)', (string) $deLaCarga()->where('estado', 'en_recuperacion')->count()],
            ['  canceladas', (string) $deLaCarga()->where('estado', 'cancelada')->count()],
            ['  urgencias', (string) $deLaCarga()->where('tipo', 'urgencia')->count()],
            ['  con complicación', (string) count($this->complicadas)],
            ['Costeadas en esta corrida', (string) $costeadas],
            ['Alertas de sobrecosto del hospital', (string) $alertas],
            ['Facturas', (string) Facturacion::query()->whereIn('cirugia_id', $deLaCarga()->select('id'))->count()],
            ['Resultados clínicos', (string) ResultadoClinico::query()->whereIn('cirugia_id', $deLaCarga()->select('id'))->count()],
        ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function datos(string $archivo): array
    {
        static $cache = [];

        if (! isset($cache[$archivo])) {
            $datos = require database_path("seeders/datos/san-rafael/{$archivo}.php");
            $cache[$archivo] = is_array($datos) ? $datos : throw new \RuntimeException("Datos {$archivo} inválidos.");
        }

        return $cache[$archivo];
    }
}
