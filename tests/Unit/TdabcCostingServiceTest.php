<?php

namespace Tests\Unit;

use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Services\Costing\TdabcCostingService;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TdabcCostingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    /**
     * Caso de prueba obligatorio de la tesis (sección 5.3.3):
     *
     *   cirujano       $50.000/h × 1,5 h =  75.000
     *   ayudante       $30.000/h × 1,5 h =  45.000
     *   anestesiólogo  $50.000/h × 2 h   = 100.000
     *   instrumentador $20.000/h × 2 h   =  40.000
     *   circulante     $15.000/h × 2 h   =  30.000
     *   sala           $40.000/h × 2 h   =  80.000
     *   insumos                          = 150.000
     *   ─────────────────────────────────────────
     *   TOTAL                            = 520.000 COP
     */
    public function test_una_cesarea_cuesta_520_mil_pesos(): void
    {
        $cirugia = $this->crearCesareaDeReferencia();

        $costo = app(TdabcCostingService::class)->calcular($cirugia);

        $this->assertEqualsWithDelta(75000.0 + 45000.0 + 100000.0 + 40000.0 + 30000.0, (float) $costo->costo_recurso_humano, 0.01);
        $this->assertEqualsWithDelta(80000.0, (float) $costo->costo_sala, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $costo->costo_equipos, 0.01);
        $this->assertEqualsWithDelta(150000.0, (float) $costo->costo_insumos, 0.01);
        $this->assertEqualsWithDelta(520000.0, (float) $costo->costo_directo, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $costo->costo_indirecto, 0.01);

        // El total exigido por el caso de prueba
        $this->assertEqualsWithDelta(520000.0, (float) $costo->costo_total, 0.01);
    }

    public function test_costo_por_minuto_se_deriva_del_salario_y_los_minutos_disponibles(): void
    {
        $hospital = Hospital::factory()->create(['horas_dia' => 12, 'dias_mes' => 26]);

        // 12 × 26 × 60 = 18.720 minutos disponibles/mes
        $this->assertSame(18720, $hospital->minutosDisponiblesMes());

        // 15.600.000 ÷ 18.720 = 833,33/min = $50.000/hora
        $cirujano = RecursoHumano::factory()->create([
            'hospital_id' => $hospital->id,
            'salario_mensual' => 10_000_000,
            'prestaciones_mensuales' => 4_600_000,
            'costos_indirectos_mensuales' => 1_000_000,
        ]);

        $this->assertEqualsWithDelta(833.3333, $cirujano->costoPorMinuto(), 0.001);
        $this->assertEqualsWithDelta(50000.0, $cirujano->costoPorMinuto() * 60, 0.01);
    }

    public function test_el_factor_indirecto_del_hospital_se_aplica_sobre_el_costo_directo(): void
    {
        $cirugia = $this->crearCesareaDeReferencia(factorIndirecto: 0.10);

        $costo = app(TdabcCostingService::class)->calcular($cirugia);

        $this->assertEqualsWithDelta(520000.0, (float) $costo->costo_directo, 0.01);
        $this->assertEqualsWithDelta(52000.0, (float) $costo->costo_indirecto, 0.01);
        $this->assertEqualsWithDelta(572000.0, (float) $costo->costo_total, 0.01);
    }

    public function test_recalcular_actualiza_el_costo_existente_sin_duplicarlo(): void
    {
        $cirugia = $this->crearCesareaDeReferencia();
        $motor = app(TdabcCostingService::class);

        $primero = $motor->calcular($cirugia);
        $segundo = $motor->calcular($cirugia->fresh());

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, $cirugia->costo()->count());
    }

    protected function crearCesareaDeReferencia(float $factorIndirecto = 0): Cirugia
    {
        $hospital = Hospital::factory()->create([
            'horas_dia' => 12,
            'dias_mes' => 26,
            'factor_indirecto' => $factorIndirecto,
        ]);
        HospitalContext::set($hospital->id);

        $sala = SalaOperatoria::factory()->create([
            'hospital_id' => $hospital->id,
            'costo_hora' => 40_000,
        ]);

        // Salarios que producen exactamente las tarifas/hora del caso de prueba
        $tarifas = [
            'cirujano' => [10_000_000, 4_600_000, 1_000_000],       // $50.000/h
            'ayudante' => [6_500_000, 2_460_000, 400_000],          // $30.000/h
            'anestesiologo' => [10_400_000, 4_200_000, 1_000_000],  // $50.000/h
            'instrumentador' => [4_200_000, 1_640_000, 400_000],    // $20.000/h
            'circulante' => [3_120_000, 1_260_000, 300_000],        // $15.000/h
        ];

        $recursos = [];
        foreach ($tarifas as $rol => [$salario, $prestaciones, $indirectos]) {
            $recursos[$rol] = RecursoHumano::factory()->create([
                'hospital_id' => $hospital->id,
                'rol' => $rol,
                'salario_mensual' => $salario,
                'prestaciones_mensuales' => $prestaciones,
                'costos_indirectos_mensuales' => $indirectos,
            ]);
        }

        $paciente = Paciente::factory()->create(['hospital_id' => $hospital->id]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $hospital->id,
            'nombre' => 'Cesárea segmentaria [SEMILLA]',
        ]);

        $cirugia = Cirugia::create([
            'hospital_id' => $hospital->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $sala->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => '2026-06-10 10:00:00', // 2 horas de sala
            'tipo' => 'programada',
            'estado' => 'realizada',
        ]);
        $cirugia->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

        $participaciones = [
            ['cirujano', 90],        // 1,5 h
            ['ayudante', 90],        // 1,5 h
            ['anestesiologo', 120],  // 2 h
            ['instrumentador', 120], // 2 h
            ['circulante', 120],     // 2 h
        ];
        foreach ($participaciones as [$rol, $minutos]) {
            MiembroEquipoQuirurgico::create([
                'cirugia_id' => $cirugia->id,
                'recurso_humano_id' => $recursos[$rol]->id,
                'rol' => $rol,
                'minutos_participacion' => $minutos,
            ]);
        }

        $insumo = Insumo::factory()->create([
            'hospital_id' => $hospital->id,
            'nombre' => 'Paquete quirúrgico cesárea [SEMILLA]',
            'costo_unitario' => 150_000,
        ]);
        $cirugia->consumos()->create([
            'insumo_id' => $insumo->id,
            'cantidad' => 1,
            'costo_unitario_registrado' => 150_000,
            'costo_total' => 150_000,
        ]);

        return $cirugia;
    }
}
