<?php

namespace Database\Seeders;

use App\Models\EquipoMedico;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\PlantillaEquipo;
use App\Models\PlantillaInsumo;
use App\Models\PlantillaPersonal;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Support\HospitalContext;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;

/**
 * Catálogo maestro de un hospital: todo lo que debe existir ANTES de poder
 * registrar la primera cirugía.
 *
 *   salas · recursos humanos · procedimientos · insumos · equipos médicos
 *   y las plantillas (protocolos) que dejan cada procedimiento preorganizado.
 *
 * No crea cirugías ni facturación: eso es movimiento, no catálogo.
 *
 * Idempotente: cada entidad se busca por su clave natural dentro del hospital
 * (nombre de sala, código CUPS, código de insumo…), así que reejecutarlo
 * actualiza precios en vez de duplicar registros.
 *
 * Los códigos CUPS son cadenas numéricas, así que PHP convierte esas claves a
 * int al usarlas en un array; de ahí el `array-key` en vez de `string`.
 *
 * @phpstan-type Protocolo array{duracion: int, insumos: list<string>, equipos: list<string>}
 * @phpstan-type CatalogoSembrado array{
 *     salas: list<SalaOperatoria>,
 *     personal: array<string, list<RecursoHumano>>,
 *     procedimientos: array<array-key, ProcedimientoQuirurgico>,
 *     insumos: array<string, Insumo>,
 *     equipos: array<string, EquipoMedico>,
 *     protocolos: array<array-key, Protocolo>
 * }
 */
class CatalogoQuirurgicoSeeder extends Seeder
{
    use InformaEnConsola;

    /**
     * Qué catálogo recibe cada hospital, por NIT.
     */
    public const CATALOGO_POR_NIT = [
        '800100200-1' => 'principal',
        '800300400-2' => 'secundario',
    ];

    public function run(): void
    {
        foreach (self::CATALOGO_POR_NIT as $nit => $perfil) {
            $hospital = Hospital::query()->where('nit', $nit)->first();

            if ($hospital === null) {
                $this->advertir("Sin hospital con NIT {$nit}: se omite su catálogo. Ejecuta HospitalSeeder primero.");

                continue;
            }

            $this->sembrar($hospital, $perfil);
            $this->informar("Catálogo «{$perfil}» sembrado en {$hospital->nombre}.");
        }
    }

    /**
     * Siembra un catálogo en un hospital y devuelve los modelos creados,
     * indexados por su clave natural, para que otros seeders (por ejemplo
     * DemoSeeder) construyan movimiento encima sin volver a consultarlos.
     *
     * @return CatalogoSembrado
     */
    public function sembrar(Hospital $hospital, string $perfil = 'principal'): array
    {
        $catalogo = $this->catalogos()[$perfil]
            ?? throw new \InvalidArgumentException("Catálogo desconocido: {$perfil}");

        $anterior = HospitalContext::id();
        HospitalContext::set($hospital->id);

        try {
            $salas = $this->sembrarSalas($hospital, $catalogo['salas']);
            $personal = $this->sembrarPersonal($hospital, $catalogo['personal']);
            $procedimientos = $this->sembrarProcedimientos($hospital, $catalogo['procedimientos']);
            $insumos = $this->sembrarInsumos($hospital, $catalogo['insumos']);
            $equipos = $this->sembrarEquipos($hospital, $catalogo['equipos']);

            $this->sembrarPlantillas($catalogo['protocolos'], $procedimientos, $insumos, $equipos);
        } finally {
            HospitalContext::set($anterior);
        }

        return [
            'salas' => $salas,
            'personal' => $personal,
            'procedimientos' => $procedimientos,
            'insumos' => $insumos,
            'equipos' => $equipos,
            'protocolos' => $catalogo['protocolos'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $definiciones
     * @return list<SalaOperatoria>
     */
    protected function sembrarSalas(Hospital $hospital, array $definiciones): array
    {
        $salas = [];

        foreach ($definiciones as $datos) {
            $salas[] = SalaOperatoria::updateOrCreate(
                ['hospital_id' => $hospital->id, 'nombre' => $datos['nombre']],
                $datos + [
                    'fuente' => 'Estimación jefe de central quirúrgica [SEMILLA]',
                    'nivel_confiabilidad' => 'estimado',
                ],
            );
        }

        return $salas;
    }

    /**
     * @param  list<array<string, mixed>>  $definiciones
     * @return array<string, list<RecursoHumano>>
     */
    protected function sembrarPersonal(Hospital $hospital, array $definiciones): array
    {
        $personal = [];

        foreach ($definiciones as $datos) {
            $recurso = RecursoHumano::updateOrCreate(
                ['hospital_id' => $hospital->id, 'nombre' => $datos['nombre']],
                $datos + [
                    'especialidad' => null,
                    'fuente' => 'Nómina y contratos [SEMILLA]',
                    'nivel_confiabilidad' => 'medido',
                ],
            );

            $personal[$datos['rol']][] = $recurso;
        }

        return $personal;
    }

    /**
     * @param  list<array<string, mixed>>  $definiciones
     * @return array<array-key, ProcedimientoQuirurgico>
     */
    protected function sembrarProcedimientos(Hospital $hospital, array $definiciones): array
    {
        $procedimientos = [];

        foreach ($definiciones as $datos) {
            $procedimientos[$datos['codigo_cups']] = ProcedimientoQuirurgico::updateOrCreate(
                ['hospital_id' => $hospital->id, 'codigo_cups' => $datos['codigo_cups']],
                $datos + [
                    'fuente' => 'Protocolos institucionales [SEMILLA]',
                    'nivel_confiabilidad' => 'supuesto',
                ],
            );
        }

        return $procedimientos;
    }

    /**
     * @param  list<array<string, mixed>>  $definiciones
     * @return array<string, Insumo>
     */
    protected function sembrarInsumos(Hospital $hospital, array $definiciones): array
    {
        $insumos = [];

        foreach ($definiciones as $datos) {
            $insumos[$datos['codigo']] = Insumo::updateOrCreate(
                ['hospital_id' => $hospital->id, 'codigo' => $datos['codigo']],
                $datos + [
                    'codigo_atc' => null,
                    'fuente' => 'Facturas de compra [SEMILLA]',
                    'nivel_confiabilidad' => 'medido',
                ],
            );
        }

        return $insumos;
    }

    /**
     * @param  list<array<string, mixed>>  $definiciones
     * @return array<string, EquipoMedico>
     */
    protected function sembrarEquipos(Hospital $hospital, array $definiciones): array
    {
        $equipos = [];

        foreach ($definiciones as $datos) {
            $equipos[$datos['codigo']] = EquipoMedico::updateOrCreate(
                ['hospital_id' => $hospital->id, 'codigo' => $datos['codigo']],
                $datos + [
                    'fuente' => 'Inventario de activos fijos [SEMILLA]',
                    'nivel_confiabilidad' => 'estimado',
                ],
            );
        }

        return $equipos;
    }

    /**
     * Deja cada protocolo preorganizado con lo que consume siempre, el equipo
     * que lo hace y los aparatos que usa: así el registro de una cirugía nace
     * lleno y el digitador solo marca la excepción.
     *
     * @param  array<array-key, Protocolo>  $protocolos
     * @param  array<array-key, ProcedimientoQuirurgico>  $procedimientos
     * @param  array<string, Insumo>  $insumos
     * @param  array<string, EquipoMedico>  $equipos
     */
    protected function sembrarPlantillas(
        array $protocolos,
        array $procedimientos,
        array $insumos,
        array $equipos,
    ): void {
        foreach ($protocolos as $cups => $protocolo) {
            $procedimiento = $procedimientos[$cups];

            foreach ($protocolo['insumos'] as $codigo) {
                PlantillaInsumo::updateOrCreate(
                    [
                        'procedimiento_quirurgico_id' => $procedimiento->id,
                        'insumo_id' => $insumos[$codigo]->id,
                        // La profilaxis antibiótica se pone antes de entrar a sala.
                        'fase' => $codigo === 'MED-003' ? 'pre' : 'quirurgica',
                    ],
                    ['cantidad' => 1],
                );
            }

            foreach ($protocolo['equipos'] as $codigo) {
                PlantillaEquipo::updateOrCreate([
                    'procedimiento_quirurgico_id' => $procedimiento->id,
                    'equipo_medico_id' => $equipos[$codigo]->id,
                ]);
            }

            // El equipo quirúrgico mínimo de cualquiera de estos procedimientos.
            foreach (['cirujano', 'ayudante', 'anestesiologo', 'instrumentador', 'circulante'] as $rol) {
                PlantillaPersonal::firstOrCreate(
                    [
                        'procedimiento_quirurgico_id' => $procedimiento->id,
                        'rol' => $rol,
                        'fase' => 'quirurgica',
                    ],
                    ['cantidad' => 1],
                );
            }
        }
    }

    /**
     * Datos del catálogo. Para una instalación real, reemplaza estos arrays
     * por los del hospital: tarifas de nómina, precios de compra reales y los
     * códigos CUPS oficiales que factura la institución.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function catalogos(): array
    {
        return [
            // Hospital de mediana complejidad con central quirúrgica de 2 salas.
            //
            // Las tarifas del primer recurso de cada rol reproducen exactamente
            // el caso de prueba TDABC (18.720 min/mes disponibles):
            //   cirujano $50.000/h · ayudante $30.000/h · anestesiólogo $50.000/h
            //   instrumentador $20.000/h · circulante $15.000/h
            'principal' => [
                'salas' => [
                    [
                        'nombre' => 'Sala 1',
                        'ubicacion' => 'Piso 2 - Central quirúrgica',
                        'costo_hora' => 40_000,
                        'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica', 'monitor de signos'],
                    ],
                    [
                        'nombre' => 'Sala 2',
                        'ubicacion' => 'Piso 2 - Central quirúrgica',
                        'costo_hora' => 45_000,
                        'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica', 'torre de laparoscopia'],
                    ],
                ],

                'personal' => [
                    // 15.600.000/mes → $50.000/h
                    ['nombre' => 'Dra. Carmen Epiayú [SEMILLA]', 'rol' => 'cirujano', 'salario_mensual' => 10_000_000, 'prestaciones_mensuales' => 4_600_000, 'costos_indirectos_mensuales' => 1_000_000, 'especialidad' => 'Ginecobstetricia'],
                    ['nombre' => 'Dr. Rafael Brito [SEMILLA]', 'rol' => 'cirujano', 'salario_mensual' => 11_000_000, 'prestaciones_mensuales' => 5_000_000, 'costos_indirectos_mensuales' => 1_000_000, 'especialidad' => 'Cirugía general'],
                    // 9.360.000/mes → $30.000/h
                    ['nombre' => 'Dr. Luis Pushaina [SEMILLA]', 'rol' => 'ayudante', 'salario_mensual' => 6_500_000, 'prestaciones_mensuales' => 2_460_000, 'costos_indirectos_mensuales' => 400_000],
                    ['nombre' => 'Dra. Ana Redondo [SEMILLA]', 'rol' => 'ayudante', 'salario_mensual' => 7_000_000, 'prestaciones_mensuales' => 2_800_000, 'costos_indirectos_mensuales' => 500_000],
                    // 15.600.000/mes → $50.000/h
                    ['nombre' => 'Dr. Jorge Ipuana [SEMILLA]', 'rol' => 'anestesiologo', 'salario_mensual' => 10_400_000, 'prestaciones_mensuales' => 4_200_000, 'costos_indirectos_mensuales' => 1_000_000, 'especialidad' => 'Anestesiología'],
                    ['nombre' => 'Dra. Paula Freyle [SEMILLA]', 'rol' => 'anestesiologo', 'salario_mensual' => 11_200_000, 'prestaciones_mensuales' => 4_500_000, 'costos_indirectos_mensuales' => 900_000, 'especialidad' => 'Anestesiología'],
                    // 6.240.000/mes → $20.000/h
                    ['nombre' => 'Inst. Kelly Mengual [SEMILLA]', 'rol' => 'instrumentador', 'salario_mensual' => 4_200_000, 'prestaciones_mensuales' => 1_640_000, 'costos_indirectos_mensuales' => 400_000],
                    ['nombre' => 'Inst. Deiber Solano [SEMILLA]', 'rol' => 'instrumentador', 'salario_mensual' => 4_500_000, 'prestaciones_mensuales' => 1_800_000, 'costos_indirectos_mensuales' => 350_000],
                    // 4.680.000/mes → $15.000/h
                    ['nombre' => 'Aux. Yolima Arpushana [SEMILLA]', 'rol' => 'circulante', 'salario_mensual' => 3_120_000, 'prestaciones_mensuales' => 1_260_000, 'costos_indirectos_mensuales' => 300_000],
                    ['nombre' => 'Aux. Miguel Ojeda [SEMILLA]', 'rol' => 'circulante', 'salario_mensual' => 3_400_000, 'prestaciones_mensuales' => 1_400_000, 'costos_indirectos_mensuales' => 250_000],
                ],

                // Códigos CUPS de ejemplo, no oficiales.
                'procedimientos' => [
                    ['codigo_cups' => '740001', 'nombre' => 'Cesárea segmentaria [SEMILLA]', 'especialidad' => 'Ginecobstetricia', 'complejidad' => 'media', 'duracion_estimada_minutos' => 120, 'tarifa_soat' => 850_000],
                    ['codigo_cups' => '780201', 'nombre' => 'Apendicectomía abierta [SEMILLA]', 'especialidad' => 'Cirugía general', 'complejidad' => 'media', 'duracion_estimada_minutos' => 90, 'tarifa_soat' => 780_000],
                    ['codigo_cups' => '741500', 'nombre' => 'Colecistectomía laparoscópica [SEMILLA]', 'especialidad' => 'Cirugía general', 'complejidad' => 'alta', 'duracion_estimada_minutos' => 120, 'tarifa_soat' => 1_900_000],
                    ['codigo_cups' => '744201', 'nombre' => 'Herniorrafia inguinal [SEMILLA]', 'especialidad' => 'Cirugía general', 'complejidad' => 'baja', 'duracion_estimada_minutos' => 90, 'tarifa_soat' => 950_000],
                ],

                // Los seis primeros componen los $150.000 de insumos de la
                // cesárea de referencia del caso de prueba.
                'insumos' => [
                    ['codigo' => 'MED-001', 'nombre' => 'Oxitocina 10 UI [SEMILLA]', 'categoria' => 'medicamento', 'costo_unitario' => 5_000, 'unidad' => 'ampolla', 'codigo_atc' => 'H01BB02'],
                    ['codigo' => 'MED-002', 'nombre' => 'Bupivacaína 0.5% [SEMILLA]', 'categoria' => 'medicamento', 'costo_unitario' => 25_000, 'unidad' => 'ampolla', 'codigo_atc' => 'N01BB01'],
                    ['codigo' => 'MAT-001', 'nombre' => 'Sutura absorbible 1-0 [SEMILLA]', 'categoria' => 'material', 'costo_unitario' => 15_000, 'unidad' => 'unidad'],
                    ['codigo' => 'MAT-002', 'nombre' => 'Compresas estériles [SEMILLA]', 'categoria' => 'material', 'costo_unitario' => 2_000, 'unidad' => 'unidad'],
                    ['codigo' => 'MAT-003', 'nombre' => 'Guantes estériles [SEMILLA]', 'categoria' => 'material', 'costo_unitario' => 3_000, 'unidad' => 'par'],
                    ['codigo' => 'MAT-004', 'nombre' => 'Campos quirúrgicos desechables [SEMILLA]', 'categoria' => 'material', 'costo_unitario' => 32_000, 'unidad' => 'paquete'],
                    ['codigo' => 'MED-003', 'nombre' => 'Cefazolina 1 g [SEMILLA]', 'categoria' => 'medicamento', 'costo_unitario' => 8_000, 'unidad' => 'ampolla', 'codigo_atc' => 'J01DB04'],
                    ['codigo' => 'MAT-005', 'nombre' => 'Hoja de bisturí No. 21 [SEMILLA]', 'categoria' => 'material', 'costo_unitario' => 4_000, 'unidad' => 'unidad'],
                    ['codigo' => 'MAT-006', 'nombre' => 'Gasas estériles [SEMILLA]', 'categoria' => 'material', 'costo_unitario' => 1_500, 'unidad' => 'paquete'],
                    ['codigo' => 'DIS-001', 'nombre' => 'Sonda Foley 16 Fr [SEMILLA]', 'categoria' => 'dispositivo', 'costo_unitario' => 12_000, 'unidad' => 'unidad'],
                    ['codigo' => 'DIS-002', 'nombre' => 'Malla de polipropileno [SEMILLA]', 'categoria' => 'dispositivo', 'costo_unitario' => 180_000, 'unidad' => 'unidad'],
                    ['codigo' => 'DIS-003', 'nombre' => 'Clips de laparoscopia [SEMILLA]', 'categoria' => 'dispositivo', 'costo_unitario' => 95_000, 'unidad' => 'set'],
                ],

                'equipos' => [
                    ['codigo' => 'EQ-001', 'nombre' => 'Electrobisturí [SEMILLA]', 'valor_adquisicion' => 45_000_000, 'vida_util_anios' => 8, 'costo_hora' => 15_000],
                    ['codigo' => 'EQ-002', 'nombre' => 'Torre de laparoscopia [SEMILLA]', 'valor_adquisicion' => 280_000_000, 'vida_util_anios' => 10, 'costo_hora' => 60_000],
                    ['codigo' => 'EQ-003', 'nombre' => 'Monitor multiparámetro [SEMILLA]', 'valor_adquisicion' => 30_000_000, 'vida_util_anios' => 7, 'costo_hora' => 12_000],
                ],

                'protocolos' => [
                    '740001' => ['duracion' => 120, 'equipos' => [], 'insumos' => ['MED-001', 'MED-002', 'MAT-001', 'MAT-002', 'MAT-003', 'MAT-004', 'MED-003']],
                    '780201' => ['duracion' => 90, 'equipos' => ['EQ-001'], 'insumos' => ['MED-003', 'MAT-005', 'MAT-001', 'MAT-006', 'MAT-003', 'MAT-004']],
                    '741500' => ['duracion' => 120, 'equipos' => ['EQ-002', 'EQ-001', 'EQ-003'], 'insumos' => ['MED-003', 'DIS-003', 'MAT-001', 'MAT-006', 'MAT-003', 'MAT-004', 'DIS-001']],
                    '744201' => ['duracion' => 90, 'equipos' => ['EQ-001'], 'insumos' => ['MED-003', 'DIS-002', 'MAT-001', 'MAT-006', 'MAT-003', 'MAT-005']],
                ],
            ],

            // Hospital pequeño: una sala, un profesional por rol, un solo
            // procedimiento. Sirve para demostrar el aislamiento multi-tenant.
            'secundario' => [
                'salas' => [
                    [
                        'nombre' => 'Sala única',
                        'ubicacion' => null,
                        'costo_hora' => 38_000,
                        'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica'],
                    ],
                ],

                'personal' => [
                    ['nombre' => 'Dra. Sandra Iguarán [SEMILLA]', 'rol' => 'cirujano', 'salario_mensual' => 9_500_000, 'prestaciones_mensuales' => 4_300_000, 'costos_indirectos_mensuales' => 800_000],
                    ['nombre' => 'Dr. Óscar Deluque [SEMILLA]', 'rol' => 'ayudante', 'salario_mensual' => 6_000_000, 'prestaciones_mensuales' => 2_300_000, 'costos_indirectos_mensuales' => 350_000],
                    ['nombre' => 'Dr. Fabio Cotes [SEMILLA]', 'rol' => 'anestesiologo', 'salario_mensual' => 10_000_000, 'prestaciones_mensuales' => 4_000_000, 'costos_indirectos_mensuales' => 900_000],
                    ['nombre' => 'Inst. Leidy Pana [SEMILLA]', 'rol' => 'instrumentador', 'salario_mensual' => 4_000_000, 'prestaciones_mensuales' => 1_500_000, 'costos_indirectos_mensuales' => 350_000],
                    ['nombre' => 'Aux. Jaider Movil [SEMILLA]', 'rol' => 'circulante', 'salario_mensual' => 3_000_000, 'prestaciones_mensuales' => 1_150_000, 'costos_indirectos_mensuales' => 250_000],
                ],

                'procedimientos' => [
                    ['codigo_cups' => '740001', 'nombre' => 'Cesárea segmentaria [SEMILLA]', 'especialidad' => 'Ginecobstetricia', 'complejidad' => 'media', 'duracion_estimada_minutos' => 110, 'tarifa_soat' => 820_000],
                ],

                'insumos' => [
                    ['codigo' => 'MED-001', 'nombre' => 'Oxitocina 10 UI [SEMILLA]', 'categoria' => 'medicamento', 'costo_unitario' => 5_500, 'unidad' => 'ampolla', 'codigo_atc' => 'H01BB02'],
                    ['codigo' => 'MAT-001', 'nombre' => 'Sutura absorbible 1-0 [SEMILLA]', 'categoria' => 'material', 'costo_unitario' => 16_000, 'unidad' => 'unidad'],
                    ['codigo' => 'MAT-002', 'nombre' => 'Compresas estériles [SEMILLA]', 'categoria' => 'material', 'costo_unitario' => 2_200, 'unidad' => 'unidad'],
                    ['codigo' => 'MAT-003', 'nombre' => 'Campos quirúrgicos [SEMILLA]', 'categoria' => 'material', 'costo_unitario' => 30_000, 'unidad' => 'paquete'],
                ],

                'equipos' => [],

                'protocolos' => [
                    '740001' => ['duracion' => 110, 'equipos' => [], 'insumos' => ['MED-001', 'MAT-001', 'MAT-002', 'MAT-003']],
                ],
            ],
        ];
    }
}
