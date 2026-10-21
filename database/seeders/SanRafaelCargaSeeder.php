<?php

namespace Database\Seeders;

use App\Models\ConceptoCostoIndirecto;
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
use Illuminate\Support\Facades\DB;

/**
 * Carga de volumen de la Capa 1 para el Hospital San Rafael de Maicao.
 *
 * Amplía el catálogo [SEMILLA] con un hospital de mediana complejidad
 * dimensionado con sentido común —cinco salas y el personal, los equipos y el
 * portafolio que caben en ellas— para revisar los resultados del aplicativo
 * con datos de un tamaño realista. Los datos viven en
 * `database/seeders/datos/san-rafael/`.
 *
 * No toca lo que ya sembró CatalogoQuirurgicoSeeder: la cesárea de referencia
 * de $520.000 sigue dando lo mismo. Idempotente: cada entidad se busca por su
 * clave natural, así que reejecutarlo actualiza en vez de duplicar.
 *
 *   php artisan db:seed --class=SanRafaelCargaSeeder
 *
 * Todos los datos son ficticios; su `fuente` lleva la marca [SEMILLA-CARGA].
 */
class SanRafaelCargaSeeder extends Seeder
{
    use InformaEnConsola;

    public const NIT = '800100200-1';

    public const MARCA = '[SEMILLA-CARGA]';

    /** Carga prestacional de la vinculación de planta sobre el salario. */
    private const PRESTACIONES_PLANTA = 0.48;

    /** Mantenimiento anual de un equipo biomédico, como fracción de su valor. */
    private const MANTENIMIENTO_ANUAL = 0.08;

    public function run(): void
    {
        $hospital = Hospital::query()->where('nit', self::NIT)->first();

        if ($hospital === null) {
            $this->advertir('Sin Hospital San Rafael (NIT '.self::NIT.'): ejecuta HospitalSeeder primero.');

            return;
        }

        $anterior = HospitalContext::id();
        HospitalContext::set($hospital->id);

        try {
            DB::transaction(function () use ($hospital): void {
                $this->sembrarSalas($hospital);
                $this->sembrarPersonal($hospital);
                $this->sembrarInsumos($hospital);
                $this->sembrarEquipos($hospital);
                $this->sembrarProcedimientos($hospital);
                $this->sembrarCostosIndirectos($hospital);
            });

            $this->resumir($hospital);
        } finally {
            HospitalContext::set($anterior);
        }
    }

    protected function sembrarSalas(Hospital $hospital): void
    {
        /** @var list<array{nombre: string, ubicacion: string, costo_hora: int, equipamiento: list<string>}> $salas */
        $salas = $this->datos('salas');

        foreach ($salas as $sala) {
            SalaOperatoria::updateOrCreate(
                ['hospital_id' => $hospital->id, 'nombre' => $sala['nombre']],
                $sala + [
                    'activa' => true,
                    'fuente' => 'Estudio de costos de la central quirúrgica '.self::MARCA,
                    'nivel_confiabilidad' => 'estimado',
                ],
            );
        }
    }

    protected function sembrarPersonal(Hospital $hospital): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: int, 4: string, 5: int, 6: bool}> $personal */
        $personal = $this->datos('personal');

        foreach ($personal as [$nombre, $rol, $especialidad, $salario, $vinculacion, $indirectos, $activo]) {
            $planta = $vinculacion === 'planta';

            RecursoHumano::updateOrCreate(
                ['hospital_id' => $hospital->id, 'nombre' => $nombre],
                [
                    'rol' => $rol,
                    'especialidad' => $especialidad,
                    'salario_mensual' => $salario,
                    'prestaciones_mensuales' => $planta ? round($salario * self::PRESTACIONES_PLANTA, -3) : 0,
                    'costos_indirectos_mensuales' => $indirectos,
                    'activo' => $activo,
                    'fuente' => ($planta ? 'Nómina 2026, vinculación de planta ' : 'Contrato de prestación de servicios (OPS) 2026 ').self::MARCA,
                    'nivel_confiabilidad' => 'medido',
                ],
            );
        }
    }

    protected function sembrarInsumos(Hospital $hospital): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: int, 5: string|null}> $insumos */
        $insumos = $this->datos('insumos');

        foreach ($insumos as [$codigo, $nombre, $categoria, $unidad, $costo, $atc]) {
            Insumo::updateOrCreate(
                ['hospital_id' => $hospital->id, 'codigo' => $codigo],
                [
                    'nombre' => $nombre,
                    'categoria' => $categoria,
                    'unidad' => $unidad,
                    'costo_unitario' => $costo,
                    'codigo_atc' => $atc,
                    'activo' => true,
                    'fuente' => 'Facturas de compra 2026 '.self::MARCA,
                    'nivel_confiabilidad' => 'medido',
                ],
            );
        }
    }

    protected function sembrarEquipos(Hospital $hospital): void
    {
        /** @var list<array{0: string, 1: string, 2: int, 3: int, 4: int}> $equipos */
        $equipos = $this->datos('equipos');

        foreach ($equipos as [$codigo, $nombre, $valor, $vidaUtil, $horasAnio]) {
            EquipoMedico::updateOrCreate(
                ['hospital_id' => $hospital->id, 'codigo' => $codigo],
                [
                    'nombre' => $nombre,
                    'valor_adquisicion' => $valor,
                    'vida_util_anios' => $vidaUtil,
                    'costo_hora' => $this->costoHoraEquipo($valor, $vidaUtil, $horasAnio),
                    'activo' => true,
                    'fuente' => "Inventario de activos fijos; {$horasAnio} h de uso/año ".self::MARCA,
                    'nivel_confiabilidad' => 'estimado',
                ],
            );
        }
    }

    /**
     * (depreciación anual + mantenimiento anual) ÷ horas de uso al año,
     * redondeado a la centena.
     */
    protected function costoHoraEquipo(int $valor, int $vidaUtil, int $horasAnio): float
    {
        return round(($valor / $vidaUtil + $valor * self::MANTENIMIENTO_ANUAL) / $horasAnio, -2);
    }

    protected function sembrarProcedimientos(Hospital $hospital): void
    {
        /** @var array{kits: array<string, array<string, array<string, int|float>>>, complementos: array<array-key, array<string, mixed>>, procedimientos: list<array<string, mixed>>} $datos */
        $datos = $this->datos('procedimientos');

        $insumos = Insumo::query()->pluck('id', 'codigo');
        $equipos = EquipoMedico::query()->whereNotNull('codigo')->pluck('id', 'codigo');

        $this->completarProtocolosSemilla($datos['complementos'], $datos['kits'], $insumos->all(), $equipos->all());

        foreach ($datos['procedimientos'] as $p) {
            /** @var array{0: int, 1: int, 2: int, 3: int} $tiempos */
            $tiempos = $p['tiempos'];

            $procedimiento = ProcedimientoQuirurgico::updateOrCreate(
                ['hospital_id' => $hospital->id, 'codigo_cups' => $p['cups']],
                [
                    'nombre' => $p['nombre'],
                    'especialidad' => $p['especialidad'],
                    'complejidad' => $p['complejidad'],
                    'duracion_estimada_minutos' => $tiempos[0],
                    'minutos_prequirurgico' => $tiempos[1],
                    'minutos_recuperacion' => $tiempos[2],
                    'minutos_recambio' => $tiempos[3],
                    'tarifa_soat' => $p['tarifa'],
                    'fuente' => 'Protocolos institucionales y tarifa SOAT −25 % '.self::MARCA,
                    'nivel_confiabilidad' => 'supuesto',
                ],
            );

            foreach ($this->insumosDelProtocolo($p, $datos['kits']) as $fase => $lineas) {
                foreach ($lineas as $codigo => $cantidad) {
                    PlantillaInsumo::updateOrCreate(
                        [
                            'procedimiento_quirurgico_id' => $procedimiento->id,
                            'insumo_id' => $insumos[$codigo]
                                ?? throw new \RuntimeException("Insumo {$codigo} inexistente en el protocolo {$p['cups']}."),
                            'fase' => $fase,
                        ],
                        ['cantidad' => $cantidad],
                    );
                }
            }

            /** @var list<string> $codigosEquipo */
            $codigosEquipo = $p['equipos'];
            foreach ($codigosEquipo as $codigo) {
                PlantillaEquipo::updateOrCreate([
                    'procedimiento_quirurgico_id' => $procedimiento->id,
                    'equipo_medico_id' => $equipos[$codigo]
                        ?? throw new \RuntimeException("Equipo {$codigo} inexistente en el protocolo {$p['cups']}."),
                ]);
            }

            /** @var array<string, int> $personal */
            $personal = $p['personal'];
            foreach ($personal as $rol => $cantidad) {
                PlantillaPersonal::updateOrCreate(
                    ['procedimiento_quirurgico_id' => $procedimiento->id, 'rol' => $rol, 'fase' => 'quirurgica'],
                    ['cantidad' => $cantidad],
                );
            }
        }
    }

    /**
     * Añade a los protocolos [SEMILLA] las líneas que les faltan. Nunca
     * modifica una línea existente (firstOrCreate), así que el catálogo
     * semilla conserva sus cantidades.
     *
     * @param  array<array-key, array<string, mixed>>  $complementos
     * @param  array<string, array<string, array<string, int|float>>>  $kits
     * @param  array<string, int>  $insumos
     * @param  array<string, int>  $equipos
     */
    protected function completarProtocolosSemilla(array $complementos, array $kits, array $insumos, array $equipos): void
    {
        foreach ($complementos as $cups => $complemento) {
            $procedimiento = ProcedimientoQuirurgico::query()->where('codigo_cups', (string) $cups)->first();

            if ($procedimiento === null) {
                continue;
            }

            foreach ($this->insumosDelProtocolo($complemento, $kits) as $fase => $lineas) {
                foreach ($lineas as $codigo => $cantidad) {
                    PlantillaInsumo::firstOrCreate(
                        ['procedimiento_quirurgico_id' => $procedimiento->id, 'insumo_id' => $insumos[$codigo], 'fase' => $fase],
                        ['cantidad' => $cantidad],
                    );
                }
            }

            /** @var list<string> $codigosEquipo */
            $codigosEquipo = $complemento['equipos'] ?? [];
            foreach ($codigosEquipo as $codigo) {
                PlantillaEquipo::firstOrCreate([
                    'procedimiento_quirurgico_id' => $procedimiento->id,
                    'equipo_medico_id' => $equipos[$codigo],
                ]);
            }
        }
    }

    /**
     * Suma los kits del protocolo y los insumos propios, por fase. Un mismo
     * insumo en dos kits (p. ej. ondansetrón en la anestesia y en la
     * analgesia) acumula su cantidad dentro de la fase.
     *
     * @param  array<string, mixed>  $procedimiento
     * @param  array<string, array<string, array<string, int|float>>>  $kits
     * @return array<string, array<string, int|float>>
     */
    protected function insumosDelProtocolo(array $procedimiento, array $kits): array
    {
        /** @var list<string> $nombresKit */
        $nombresKit = $procedimiento['kits'];

        $bloques = array_map(
            fn (string $kit) => $kits[$kit] ?? throw new \RuntimeException("Kit {$kit} inexistente."),
            $nombresKit,
        );
        $bloques[] = [
            'pre' => $procedimiento['pre'] ?? [],
            'quirurgica' => $procedimiento['insumos'] ?? [],
            'post' => $procedimiento['post'] ?? [],
        ];

        $porFase = [];
        foreach ($bloques as $bloque) {
            /** @var array<string, array<string, int|float>> $bloque */
            foreach ($bloque as $fase => $lineas) {
                foreach ($lineas as $codigo => $cantidad) {
                    $porFase[$fase][$codigo] = ($porFase[$fase][$codigo] ?? 0) + $cantidad;
                }
            }
        }

        return $porFase;
    }

    /**
     * Las bolsas se crean inactivas (`activo` no es asignable en masa): solo
     * ActivarCategoriaCif puede encenderlas.
     */
    protected function sembrarCostosIndirectos(Hospital $hospital): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: int|float|null}> $conceptos */
        $conceptos = $this->datos('costos-indirectos');

        $equipos = EquipoMedico::query()->where('activo', true)->whereNotNull('valor_adquisicion')->get();
        $calculados = [
            'Depreciación de equipos biomédicos de cirugía' => $equipos->sum(
                fn (EquipoMedico $e) => (float) $e->valor_adquisicion / max(1, (int) $e->vida_util_anios) / 12,
            ),
            'Mantenimiento preventivo y correctivo biomédico' => $equipos->sum(
                fn (EquipoMedico $e) => (float) $e->valor_adquisicion * self::MANTENIMIENTO_ANUAL / 12,
            ),
        ];

        foreach ($conceptos as [$nombre, $categoria, $base, $valor]) {
            $esPorcentaje = $base === 'porcentaje_directo';
            $monto = $valor ?? round($calculados[$nombre], -3);

            ConceptoCostoIndirecto::updateOrCreate(
                ['hospital_id' => $hospital->id, 'nombre' => $nombre, 'categoria' => $categoria],
                [
                    'vigente_desde' => '2026-01-01',
                    'base_asignacion' => $base,
                    'monto_mensual' => $esPorcentaje ? null : $monto,
                    'porcentaje' => $esPorcentaje ? $monto : null,
                    'vigente_hasta' => null,
                    'fuente' => ($valor === null ? 'Calculado del inventario de equipos ' : 'Presupuesto 2026 del servicio de cirugía ').self::MARCA,
                    'nivel_confiabilidad' => $valor === null ? 'estimado' : 'supuesto',
                ],
            );
        }
    }

    protected function resumir(Hospital $hospital): void
    {
        $contar = fn (string $modelo, ?callable $filtro = null) => (string) $modelo::query()
            ->when($filtro !== null, $filtro)
            ->count();

        $this->informar("Carga de Capa 1 aplicada a {$hospital->nombre}.");
        $this->tabla(['Entidad', 'Total en el hospital'], [
            ['Salas operatorias', $contar(SalaOperatoria::class)],
            ['Recursos humanos (activos)', $contar(RecursoHumano::class, fn ($q) => $q->where('activo', true))],
            ['Recursos humanos (total)', $contar(RecursoHumano::class)],
            ['Insumos', $contar(Insumo::class)],
            ['Equipos médicos', $contar(EquipoMedico::class)],
            ['Procedimientos', $contar(ProcedimientoQuirurgico::class)],
            ['Bolsas CIF (inactivas)', $contar(ConceptoCostoIndirecto::class)],
        ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function datos(string $archivo): array
    {
        $datos = require database_path("seeders/datos/san-rafael/{$archivo}.php");

        return is_array($datos) ? $datos : throw new \RuntimeException("Datos {$archivo} inválidos.");
    }
}
