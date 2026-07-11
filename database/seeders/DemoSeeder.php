<?php

namespace Database\Seeders;

use App\Enums\RolQuirurgico;
use App\Models\Cirugia;
use App\Models\ConsumoInsumo;
use App\Models\EquipoMedico;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\ResultadoClinico;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Services\Costing\TdabcCostingService;
use App\Services\Indicators\KpiService;
use App\Support\HospitalContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de ejemplo para revisión — TODOS los registros son ficticios y
 * están marcados con [SEMILLA]. No corresponden a hospitales reales.
 *
 * Incluye la cirugía de referencia del caso de prueba TDABC:
 * una cesárea cuyo costo total debe dar exactamente $520.000 COP.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $motor = app(TdabcCostingService::class);

        // ── Super administrador (ve todos los hospitales) ────────────────
        User::factory()->superAdmin()->create([
            'name' => 'Super Admin [SEMILLA]',
            'email' => 'superadmin@demo.test',
        ]);

        // ── Hospital 1: dataset principal ────────────────────────────────
        $sanJose = Hospital::create([
            'nombre' => 'Hospital San Rafael de Maicao [SEMILLA]',
            'nit' => '800100200-1',
            'nivel_complejidad' => 'mediana',
            'municipio' => 'Maicao',
            'departamento' => 'La Guajira',
            'horas_dia' => 12,
            'dias_mes' => 26,
            'factor_indirecto' => 0, // indirectos ya asignados en los recursos
        ]);

        User::factory()->create([
            'name' => 'Admin San Rafael [SEMILLA]',
            'email' => 'admin@sanrafael.test',
            'hospital_id' => $sanJose->id,
        ]);

        HospitalContext::set($sanJose->id);
        $this->seedHospitalPrincipal($sanJose, $motor);

        // ── Hospital 2: dataset pequeño para demostrar multi-tenant ─────
        $remedios = Hospital::create([
            'nombre' => 'Hospital Nuestra Señora de Riohacha [SEMILLA]',
            'nit' => '800300400-2',
            'nivel_complejidad' => 'mediana',
            'municipio' => 'Riohacha',
            'departamento' => 'La Guajira',
            'horas_dia' => 12,
            'dias_mes' => 26,
            'factor_indirecto' => 0.12, // demuestra la asignación de indirectos
        ]);

        User::factory()->create([
            'name' => 'Admin Riohacha [SEMILLA]',
            'email' => 'admin@riohacha.test',
            'hospital_id' => $remedios->id,
        ]);

        HospitalContext::set($remedios->id);
        $this->seedHospitalSecundario($remedios, $motor);

        HospitalContext::clear();
    }

    protected function seedHospitalPrincipal(Hospital $hospital, TdabcCostingService $motor): void
    {
        $salas = [
            SalaOperatoria::create([
                'hospital_id' => $hospital->id,
                'nombre' => 'Sala 1',
                'ubicacion' => 'Piso 2 - Central quirúrgica',
                'costo_hora' => 40000,
                'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica', 'monitor de signos'],
            ]),
            SalaOperatoria::create([
                'hospital_id' => $hospital->id,
                'nombre' => 'Sala 2',
                'ubicacion' => 'Piso 2 - Central quirúrgica',
                'costo_hora' => 45000,
                'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica', 'torre de laparoscopia'],
            ]),
        ];

        // Recursos humanos: el primer equipo reproduce EXACTAMENTE las
        // tarifas del caso de prueba (minutos disponibles = 18.720/mes):
        //   cirujano $50.000/h, ayudante $30.000/h, anestesiólogo $50.000/h,
        //   instrumentador $20.000/h, circulante $15.000/h.
        $equipos = [
            RolQuirurgico::Cirujano->value => [
                $this->crearRecurso($hospital, 'Dra. Carmen Epiayú [SEMILLA]', 'cirujano', 10_000_000, 4_600_000, 1_000_000, 'Ginecobstetricia'), // 15.600.000 → $50.000/h
                $this->crearRecurso($hospital, 'Dr. Rafael Brito [SEMILLA]', 'cirujano', 11_000_000, 5_000_000, 1_000_000, 'Cirugía general'),
            ],
            RolQuirurgico::Ayudante->value => [
                $this->crearRecurso($hospital, 'Dr. Luis Pushaina [SEMILLA]', 'ayudante', 6_500_000, 2_460_000, 400_000), // 9.360.000 → $30.000/h
                $this->crearRecurso($hospital, 'Dra. Ana Redondo [SEMILLA]', 'ayudante', 7_000_000, 2_800_000, 500_000),
            ],
            RolQuirurgico::Anestesiologo->value => [
                $this->crearRecurso($hospital, 'Dr. Jorge Ipuana [SEMILLA]', 'anestesiologo', 10_400_000, 4_200_000, 1_000_000, 'Anestesiología'), // 15.600.000 → $50.000/h
                $this->crearRecurso($hospital, 'Dra. Paula Freyle [SEMILLA]', 'anestesiologo', 11_200_000, 4_500_000, 900_000, 'Anestesiología'),
            ],
            RolQuirurgico::Instrumentador->value => [
                $this->crearRecurso($hospital, 'Inst. Kelly Mengual [SEMILLA]', 'instrumentador', 4_200_000, 1_640_000, 400_000), // 6.240.000 → $20.000/h
                $this->crearRecurso($hospital, 'Inst. Deiber Solano [SEMILLA]', 'instrumentador', 4_500_000, 1_800_000, 350_000),
            ],
            RolQuirurgico::Circulante->value => [
                $this->crearRecurso($hospital, 'Aux. Yolima Arpushana [SEMILLA]', 'circulante', 3_120_000, 1_260_000, 300_000), // 4.680.000 → $15.000/h
                $this->crearRecurso($hospital, 'Aux. Miguel Ojeda [SEMILLA]', 'circulante', 3_400_000, 1_400_000, 250_000),
            ],
        ];

        // Procedimientos (códigos CUPS de ejemplo, no oficiales)
        $cesarea = ProcedimientoQuirurgico::create([
            'hospital_id' => $hospital->id,
            'codigo_cups' => '740001',
            'nombre' => 'Cesárea segmentaria [SEMILLA]',
            'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'media',
            'duracion_estimada_minutos' => 120,
            'tarifa_soat' => 850_000,
        ]);
        $apendicectomia = ProcedimientoQuirurgico::create([
            'hospital_id' => $hospital->id,
            'codigo_cups' => '780201',
            'nombre' => 'Apendicectomía abierta [SEMILLA]',
            'especialidad' => 'Cirugía general',
            'complejidad' => 'media',
            'duracion_estimada_minutos' => 90,
            'tarifa_soat' => 780_000,
        ]);
        $colecistectomia = ProcedimientoQuirurgico::create([
            'hospital_id' => $hospital->id,
            'codigo_cups' => '741500',
            'nombre' => 'Colecistectomía laparoscópica [SEMILLA]',
            'especialidad' => 'Cirugía general',
            'complejidad' => 'alta',
            'duracion_estimada_minutos' => 120,
            'tarifa_soat' => 1_900_000,
        ]);
        $herniorrafia = ProcedimientoQuirurgico::create([
            'hospital_id' => $hospital->id,
            'codigo_cups' => '744201',
            'nombre' => 'Herniorrafia inguinal [SEMILLA]',
            'especialidad' => 'Cirugía general',
            'complejidad' => 'baja',
            'duracion_estimada_minutos' => 90,
            'tarifa_soat' => 950_000,
        ]);

        // Insumos: los seis primeros componen los $150.000 de la cesárea
        $oxitocina = $this->crearInsumo($hospital, 'MED-001', 'Oxitocina 10 UI [SEMILLA]', 'medicamento', 5_000, 'ampolla', 'H01BB02');
        $bupivacaina = $this->crearInsumo($hospital, 'MED-002', 'Bupivacaína 0.5% [SEMILLA]', 'medicamento', 25_000, 'ampolla', 'N01BB01');
        $sutura = $this->crearInsumo($hospital, 'MAT-001', 'Sutura absorbible 1-0 [SEMILLA]', 'material', 15_000, 'unidad');
        $compresas = $this->crearInsumo($hospital, 'MAT-002', 'Compresas estériles [SEMILLA]', 'material', 2_000, 'unidad');
        $guantes = $this->crearInsumo($hospital, 'MAT-003', 'Guantes estériles [SEMILLA]', 'material', 3_000, 'par');
        $campos = $this->crearInsumo($hospital, 'MAT-004', 'Campos quirúrgicos desechables [SEMILLA]', 'material', 32_000, 'paquete');

        $cefazolina = $this->crearInsumo($hospital, 'MED-003', 'Cefazolina 1 g [SEMILLA]', 'medicamento', 8_000, 'ampolla', 'J01DB04');
        $bisturi = $this->crearInsumo($hospital, 'MAT-005', 'Hoja de bisturí No. 21 [SEMILLA]', 'material', 4_000, 'unidad');
        $sondaFoley = $this->crearInsumo($hospital, 'DIS-001', 'Sonda Foley 16 Fr [SEMILLA]', 'dispositivo', 12_000, 'unidad');
        $malla = $this->crearInsumo($hospital, 'DIS-002', 'Malla de polipropileno [SEMILLA]', 'dispositivo', 180_000, 'unidad');
        $clips = $this->crearInsumo($hospital, 'DIS-003', 'Clips de laparoscopia [SEMILLA]', 'dispositivo', 95_000, 'set');
        $gasas = $this->crearInsumo($hospital, 'MAT-006', 'Gasas estériles [SEMILLA]', 'material', 1_500, 'paquete');

        // Equipos médicos
        $electro = EquipoMedico::create([
            'hospital_id' => $hospital->id, 'nombre' => 'Electrobisturí [SEMILLA]', 'codigo' => 'EQ-001',
            'valor_adquisicion' => 45_000_000, 'vida_util_anios' => 8, 'costo_hora' => 15_000,
        ]);
        $laparoscopio = EquipoMedico::create([
            'hospital_id' => $hospital->id, 'nombre' => 'Torre de laparoscopia [SEMILLA]', 'codigo' => 'EQ-002',
            'valor_adquisicion' => 280_000_000, 'vida_util_anios' => 10, 'costo_hora' => 60_000,
        ]);
        $monitor = EquipoMedico::create([
            'hospital_id' => $hospital->id, 'nombre' => 'Monitor multiparámetro [SEMILLA]', 'codigo' => 'EQ-003',
            'valor_adquisicion' => 30_000_000, 'vida_util_anios' => 7, 'costo_hora' => 12_000,
        ]);

        // ── Cirugía de referencia: cesárea de $520.000 ───────────────────
        //   cirujano 50.000/h × 1,5 h  =  75.000
        //   ayudante 30.000/h × 1,5 h  =  45.000
        //   anestesiólogo 50.000/h × 2 h = 100.000
        //   instrumentador 20.000/h × 2 h = 40.000
        //   circulante 15.000/h × 2 h  =  30.000
        //   sala 40.000/h × 2 h        =  80.000
        //   insumos                    = 150.000
        //   TOTAL                      = 520.000
        $pacienteRef = Paciente::factory()->create([
            'hospital_id' => $hospital->id,
            'nombres' => 'María José',
            'apellidos' => 'Uriana Gámez',
            'sexo' => 'F',
        ]);

        $cesareaRef = Cirugia::create([
            'hospital_id' => $hospital->id,
            'paciente_id' => $pacienteRef->id,
            'sala_operatoria_id' => $salas[0]->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => '2026-06-10 10:00:00',
            'tipo' => 'programada',
            'estado' => 'realizada',
            'diagnostico_cie10' => 'O82',
            'observaciones' => 'Cirugía de referencia del caso de prueba TDABC: costo total $520.000 [SEMILLA]',
        ]);
        $cesareaRef->procedimientos()->attach($cesarea->id, ['es_principal' => true]);

        $participaciones = [
            [$equipos['cirujano'][0], 'cirujano', 90],
            [$equipos['ayudante'][0], 'ayudante', 90],
            [$equipos['anestesiologo'][0], 'anestesiologo', 120],
            [$equipos['instrumentador'][0], 'instrumentador', 120],
            [$equipos['circulante'][0], 'circulante', 120],
        ];
        foreach ($participaciones as [$recurso, $rol, $minutos]) {
            MiembroEquipoQuirurgico::create([
                'cirugia_id' => $cesareaRef->id,
                'recurso_humano_id' => $recurso->id,
                'rol' => $rol,
                'minutos_participacion' => $minutos,
            ]);
        }

        $consumosCesarea = [
            [$oxitocina, 2],    // 10.000
            [$bupivacaina, 1],  // 25.000
            [$sutura, 3],       // 45.000
            [$compresas, 10],   // 20.000
            [$guantes, 6],      // 18.000
            [$campos, 1],       // 32.000
        ];
        foreach ($consumosCesarea as [$insumo, $cantidad]) {
            $this->registrarConsumo($cesareaRef, $insumo, $cantidad);
        }

        $motor->calcular($cesareaRef);

        Facturacion::create([
            'cirugia_id' => $cesareaRef->id,
            'hospital_id' => $hospital->id,
            'valor_facturado' => 637_500, // SOAT 850.000 − 25 %
            'valor_glosado' => 0,
            'valor_recaudado' => 637_500,
            'tarifa_referencia_soat' => 850_000,
            'fecha_facturacion' => '2026-06-15',
        ]);

        ResultadoClinico::create([
            'cirugia_id' => $cesareaRef->id,
            'hospital_id' => $hospital->id,
            'dias_estancia' => 3,
        ]);

        // ── Cirugías aleatorias para poblar los dashboards ───────────────
        $plantillas = [
            ['procedimiento' => $cesarea, 'duracion' => 120, 'equipos' => [], 'insumos' => [$oxitocina, $bupivacaina, $sutura, $compresas, $guantes, $campos, $cefazolina], 'n' => 9],
            ['procedimiento' => $apendicectomia, 'duracion' => 90, 'equipos' => [$electro], 'insumos' => [$cefazolina, $bisturi, $sutura, $gasas, $guantes, $campos], 'n' => 10],
            ['procedimiento' => $colecistectomia, 'duracion' => 120, 'equipos' => [$laparoscopio, $electro, $monitor], 'insumos' => [$cefazolina, $clips, $sutura, $gasas, $guantes, $campos, $sondaFoley], 'n' => 10],
            ['procedimiento' => $herniorrafia, 'duracion' => 90, 'equipos' => [$electro], 'insumos' => [$cefazolina, $malla, $sutura, $gasas, $guantes, $bisturi], 'n' => 8],
        ];

        foreach ($plantillas as $plantilla) {
            for ($i = 0; $i < $plantilla['n']; $i++) {
                // Dos colecistectomías con consumo desbordado: outliers deliberados
                $esOutlier = $plantilla['procedimiento']->is($colecistectomia) && $i < 2;

                $this->crearCirugiaAleatoria($hospital, $motor, $plantilla, $salas, $equipos, $esOutlier);
            }
        }
    }

    protected function seedHospitalSecundario(Hospital $hospital, TdabcCostingService $motor): void
    {
        $sala = SalaOperatoria::create([
            'hospital_id' => $hospital->id,
            'nombre' => 'Sala única',
            'costo_hora' => 38_000,
            'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica'],
        ]);

        $equipos = [
            'cirujano' => [$this->crearRecurso($hospital, 'Dra. Sandra Iguarán [SEMILLA]', 'cirujano', 9_500_000, 4_300_000, 800_000)],
            'ayudante' => [$this->crearRecurso($hospital, 'Dr. Óscar Deluque [SEMILLA]', 'ayudante', 6_000_000, 2_300_000, 350_000)],
            'anestesiologo' => [$this->crearRecurso($hospital, 'Dr. Fabio Cotes [SEMILLA]', 'anestesiologo', 10_000_000, 4_000_000, 900_000)],
            'instrumentador' => [$this->crearRecurso($hospital, 'Inst. Leidy Pana [SEMILLA]', 'instrumentador', 4_000_000, 1_500_000, 350_000)],
            'circulante' => [$this->crearRecurso($hospital, 'Aux. Jaider Movil [SEMILLA]', 'circulante', 3_000_000, 1_150_000, 250_000)],
        ];

        $cesarea = ProcedimientoQuirurgico::create([
            'hospital_id' => $hospital->id,
            'codigo_cups' => '740001',
            'nombre' => 'Cesárea segmentaria [SEMILLA]',
            'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'media',
            'duracion_estimada_minutos' => 110,
            'tarifa_soat' => 820_000,
        ]);

        $insumos = [
            $this->crearInsumo($hospital, 'MED-001', 'Oxitocina 10 UI [SEMILLA]', 'medicamento', 5_500, 'ampolla', 'H01BB02'),
            $this->crearInsumo($hospital, 'MAT-001', 'Sutura absorbible 1-0 [SEMILLA]', 'material', 16_000, 'unidad'),
            $this->crearInsumo($hospital, 'MAT-002', 'Compresas estériles [SEMILLA]', 'material', 2_200, 'unidad'),
            $this->crearInsumo($hospital, 'MAT-003', 'Campos quirúrgicos [SEMILLA]', 'material', 30_000, 'paquete'),
        ];

        $plantilla = ['procedimiento' => $cesarea, 'duracion' => 110, 'equipos' => [], 'insumos' => $insumos, 'n' => 6];

        for ($i = 0; $i < $plantilla['n']; $i++) {
            $this->crearCirugiaAleatoria($hospital, $motor, $plantilla, [$sala], $equipos, false);
        }
    }

    /**
     * @param  array{procedimiento: ProcedimientoQuirurgico, duracion: int, equipos: list<EquipoMedico>, insumos: list<Insumo>}  $plantilla
     * @param  list<SalaOperatoria>  $salas
     * @param  array<string, list<RecursoHumano>>  $equipos
     */
    protected function crearCirugiaAleatoria(
        Hospital $hospital,
        TdabcCostingService $motor,
        array $plantilla,
        array $salas,
        array $equipos,
        bool $esOutlier,
    ): void {
        $inicio = Carbon::now()
            ->subDays(random_int(7, 90))
            ->setTime(random_int(7, 16), [0, 30][random_int(0, 1)], 0);
        $duracion = (int) round($plantilla['duracion'] * random_int(80, 130) / 100);

        $paciente = Paciente::factory()->create(['hospital_id' => $hospital->id]);

        $cirugia = Cirugia::create([
            'hospital_id' => $hospital->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $salas[array_rand($salas)]->id,
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio,
            'hora_fin' => $inicio->copy()->addMinutes($duracion),
            'tipo' => random_int(1, 100) <= 70 ? 'programada' : 'urgencia',
            'estado' => 'realizada',
            'diagnostico_cie10' => null,
        ]);
        $cirugia->procedimientos()->attach($plantilla['procedimiento']->id, ['es_principal' => true]);

        // Equipo quirúrgico: cirujano y ayudante ~75 % de la duración,
        // el resto de roles acompañan toda la cirugía.
        foreach (RolQuirurgico::values() as $rol) {
            $pool = $equipos[$rol];
            $minutosBase = in_array($rol, ['cirujano', 'ayudante'], true)
                ? (int) round($duracion * 0.75)
                : $duracion;

            MiembroEquipoQuirurgico::create([
                'cirugia_id' => $cirugia->id,
                'recurso_humano_id' => $pool[array_rand($pool)]->id,
                'rol' => $rol,
                'minutos_participacion' => max(15, (int) round($minutosBase * random_int(90, 110) / 100)),
            ]);
        }

        // Equipos médicos de la plantilla
        foreach ($plantilla['equipos'] as $equipoMedico) {
            $cirugia->equiposMedicos()->attach($equipoMedico->id, [
                'minutos_uso' => max(15, (int) round($duracion * random_int(60, 100) / 100)),
            ]);
        }

        // Consumo de insumos (los outliers multiplican las cantidades ×5)
        $factorOutlier = $esOutlier ? 5 : 1;
        $seleccion = collect($plantilla['insumos'])->shuffle()->take(random_int(3, count($plantilla['insumos'])));
        foreach ($seleccion as $insumo) {
            $this->registrarConsumo($cirugia, $insumo, random_int(1, 6) * $factorOutlier);
        }

        // 10 % de las cirugías quedan sin costear (KPI de completitud < 100 %)
        $costeada = random_int(1, 100) <= 90;
        if ($costeada) {
            $motor->calcular($cirugia);
        }

        // 85 % facturadas
        if ($costeada && random_int(1, 100) <= 85) {
            $tarifaSoat = (float) $plantilla['procedimiento']->tarifa_soat;
            $facturado = round($tarifaSoat * KpiService::FACTOR_REFERENCIA_SOAT * random_int(95, 115) / 100, 2);
            $glosado = random_int(1, 100) <= 15 ? round($facturado * random_int(5, 20) / 100, 2) : 0.0;
            $recaudado = round(($facturado - $glosado) * random_int(80, 100) / 100, 2);

            Facturacion::create([
                'cirugia_id' => $cirugia->id,
                'hospital_id' => $hospital->id,
                'valor_facturado' => $facturado,
                'valor_glosado' => $glosado,
                'valor_recaudado' => $recaudado,
                'tarifa_referencia_soat' => $tarifaSoat,
                'fecha_facturacion' => $inicio->copy()->addDays(random_int(3, 15))->toDateString(),
            ]);
        }

        // 80 % con resultado clínico registrado
        if (random_int(1, 100) <= 80) {
            ResultadoClinico::factory()->create([
                'cirugia_id' => $cirugia->id,
                'hospital_id' => $hospital->id,
            ]);
        }
    }

    protected function crearRecurso(
        Hospital $hospital,
        string $nombre,
        string $rol,
        int $salario,
        int $prestaciones,
        int $indirectos,
        ?string $especialidad = null,
    ): RecursoHumano {
        return RecursoHumano::create([
            'hospital_id' => $hospital->id,
            'nombre' => $nombre,
            'rol' => $rol,
            'especialidad' => $especialidad,
            'salario_mensual' => $salario,
            'prestaciones_mensuales' => $prestaciones,
            'costos_indirectos_mensuales' => $indirectos,
        ]);
    }

    protected function crearInsumo(
        Hospital $hospital,
        string $codigo,
        string $nombre,
        string $categoria,
        int $costoUnitario,
        string $unidad,
        ?string $codigoAtc = null,
    ): Insumo {
        return Insumo::create([
            'hospital_id' => $hospital->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'categoria' => $categoria,
            'codigo_atc' => $codigoAtc,
            'unidad' => $unidad,
            'costo_unitario' => $costoUnitario,
        ]);
    }

    protected function registrarConsumo(Cirugia $cirugia, Insumo $insumo, int $cantidad): void
    {
        ConsumoInsumo::create([
            'cirugia_id' => $cirugia->id,
            'insumo_id' => $insumo->id,
            'cantidad' => $cantidad,
            'costo_unitario_registrado' => $insumo->costo_unitario,
            'costo_total' => round($cantidad * (float) $insumo->costo_unitario, 2),
        ]);
    }
}
