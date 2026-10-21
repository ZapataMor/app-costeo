<?php

namespace Tests\Feature\Conocimiento;

use App\Enums\RolUsuario;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\Paciente;
use App\Models\PlantillaInsumo;
use App\Models\PlantillaPersonal;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Services\Costing\TdabcCostingService;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Escenario mínimo para la Capa 4: un hospital con una sala, cinco roles,
 * un procedimiento con protocolo (cesárea de referencia: estándar $520.000)
 * y helpers para registrar cirugías costeadas con el motor real.
 */
abstract class ConocimientoTestCase extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected User $admin;

    protected SalaOperatoria $sala;

    protected ProcedimientoQuirurgico $procedimiento;

    protected Insumo $insumo;

    /** @var array<string, RecursoHumano> */
    protected array $recursos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create(['horas_dia' => 12, 'dias_mes' => 26, 'factor_indirecto' => 0]);
        HospitalContext::set($this->hospital->id);
        $this->admin = User::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->sala = SalaOperatoria::factory()->create(['hospital_id' => $this->hospital->id, 'costo_hora' => 40_000]);

        foreach ([
            'cirujano' => [10_000_000, 4_600_000, 1_000_000],
            'ayudante' => [6_500_000, 2_460_000, 400_000],
            'anestesiologo' => [10_400_000, 4_200_000, 1_000_000],
            'instrumentador' => [4_200_000, 1_640_000, 400_000],
            'circulante' => [3_120_000, 1_260_000, 300_000],
        ] as $rol => [$salario, $prestaciones, $indirectos]) {
            $this->recursos[$rol] = RecursoHumano::factory()->create([
                'hospital_id' => $this->hospital->id,
                'rol' => $rol,
                'especialidad' => $rol === 'cirujano' ? 'Ginecobstetricia' : null,
                'salario_mensual' => $salario,
                'prestaciones_mensuales' => $prestaciones,
                'costos_indirectos_mensuales' => $indirectos,
                'activo' => true,
            ]);
        }

        $this->procedimiento = ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $this->hospital->id,
            'codigo_cups' => '740001',
            'nombre' => 'Cesárea segmentaria',
            'especialidad' => 'Ginecobstetricia',
            'duracion_estimada_minutos' => 120,
            'tarifa_soat' => 850_000,
        ]);
        $this->insumo = Insumo::factory()->create(['hospital_id' => $this->hospital->id, 'costo_unitario' => 150_000]);

        foreach (['cirujano' => 90, 'ayudante' => 90, 'anestesiologo' => null, 'instrumentador' => null, 'circulante' => null] as $rol => $minutos) {
            PlantillaPersonal::create([
                'procedimiento_quirurgico_id' => $this->procedimiento->id,
                'rol' => $rol,
                'fase' => 'quirurgica',
                'cantidad' => 1,
                'minutos' => $minutos,
            ]);
        }
        PlantillaInsumo::create([
            'procedimiento_quirurgico_id' => $this->procedimiento->id,
            'insumo_id' => $this->insumo->id,
            'fase' => 'quirurgica',
            'cantidad' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    /**
     * Registra y costea una cesárea. `$minutosSala` alarga sala y anestesia;
     * `$insumos` es la cantidad de paquetes consumidos.
     */
    protected function cirugia(int $minutosSala = 120, int $insumos = 1, string $fecha = '2026-06-10', ?RecursoHumano $cirujano = null): CostoCirugia
    {
        $inicio = now()->parse("{$fecha} 08:00:00");

        $cirugia = Cirugia::create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $this->hospital->id])->id,
            'sala_operatoria_id' => $this->sala->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $inicio->copy()->addMinutes($minutosSala),
            'tipo' => 'programada',
            'estado' => 'realizada',
        ]);
        $cirugia->procedimientos()->attach($this->procedimiento->id, ['es_principal' => true]);

        foreach (['cirujano' => 90, 'ayudante' => 90, 'anestesiologo' => $minutosSala, 'instrumentador' => $minutosSala, 'circulante' => $minutosSala] as $rol => $minutos) {
            MiembroEquipoQuirurgico::create([
                'cirugia_id' => $cirugia->id,
                'recurso_humano_id' => ($rol === 'cirujano' && $cirujano !== null ? $cirujano : $this->recursos[$rol])->id,
                'rol' => $rol,
                'minutos_participacion' => $minutos,
            ]);
        }

        $cirugia->consumos()->create([
            'insumo_id' => $this->insumo->id,
            'cantidad' => $insumos,
            'costo_unitario_registrado' => 150_000,
            'costo_total' => 150_000 * $insumos,
        ]);

        return app(TdabcCostingService::class)->calcular($cirugia);
    }

    protected function profesional(?RecursoHumano $recurso = null): User
    {
        $usuario = User::factory()->create(['hospital_id' => $this->hospital->id]);
        $usuario->forceFill([
            'role' => RolUsuario::Profesional->value,
            'recurso_humano_id' => ($recurso ?? $this->recursos['cirujano'])->id,
        ])->save();

        return $usuario;
    }
}
