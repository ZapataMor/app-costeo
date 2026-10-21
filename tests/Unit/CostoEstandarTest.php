<?php

namespace Tests\Unit;

use App\Enums\ClasificacionDesviacion;
use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\Paciente;
use App\Models\PlantillaInsumo;
use App\Models\PlantillaPersonal;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Services\Costing\TdabcCostingService;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Costo estándar vs. real (backlog #3) sobre la cesárea de referencia: con el
 * protocolo que la describe exactamente, el estándar también es $520.000.
 */
class CostoEstandarTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_una_cirugia_hecha_segun_protocolo_queda_en_rango(): void
    {
        $cirugia = $this->cesarea();

        $costo = app(TdabcCostingService::class)->calcular($cirugia);

        $this->assertEqualsWithDelta(520000.0, (float) $costo->costo_total, 0.01);
        $this->assertEqualsWithDelta(520000.0, (float) $costo->costo_estandar, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $costo->desviacion, 0.01);
        $this->assertSame(ClasificacionDesviacion::EnRango, $costo->clasificacion_desviacion);
    }

    /**
     * 30 minutos más de sala y de anestesia: +20.000 de sala y +25.000 de
     * anestesiólogo = $565.000, un 8,65 % sobre el estándar → leve.
     */
    public function test_una_cirugia_que_se_alarga_queda_en_sobrecosto_leve(): void
    {
        $cirugia = $this->cesarea(minutosSala: 150, minutosAnestesia: 150);

        $costo = app(TdabcCostingService::class)->calcular($cirugia);

        $this->assertEqualsWithDelta(565000.0, (float) $costo->costo_total, 0.01);
        $this->assertEqualsWithDelta(520000.0, (float) $costo->costo_estandar, 0.01);
        $this->assertEqualsWithDelta(0.0865, (float) $costo->desviacion_pct, 0.0001);
        $this->assertSame(ClasificacionDesviacion::SobrecostoLeve, $costo->clasificacion_desviacion);
    }

    public function test_el_estandar_usa_las_tarifas_congeladas_y_no_las_vigentes(): void
    {
        $cirugia = $this->cesarea();
        // El insumo sube de precio después de registrada la cirugía.
        Insumo::query()->update(['costo_unitario' => 300_000]);

        $costo = app(TdabcCostingService::class)->calcular($cirugia->fresh());

        $this->assertEqualsWithDelta(520000.0, (float) $costo->costo_estandar, 0.01);
    }

    public function test_sin_protocolo_no_hay_estandar_ni_clasificacion(): void
    {
        $cirugia = $this->cesarea(conPlantilla: false);

        $costo = app(TdabcCostingService::class)->calcular($cirugia);

        $this->assertNull($costo->costo_estandar);
        $this->assertNull($costo->clasificacion_desviacion);
    }

    public function test_los_umbrales_del_hospital_definen_las_bandas(): void
    {
        $this->assertSame(ClasificacionDesviacion::Subejecucion, ClasificacionDesviacion::clasificar(-0.05, -0.05, 0.05, 0.20));
        $this->assertSame(ClasificacionDesviacion::EnRango, ClasificacionDesviacion::clasificar(-0.049, -0.05, 0.05, 0.20));
        $this->assertSame(ClasificacionDesviacion::SobrecostoLeve, ClasificacionDesviacion::clasificar(0.05, -0.05, 0.05, 0.20));
        $this->assertSame(ClasificacionDesviacion::SobrecostoLeve, ClasificacionDesviacion::clasificar(0.20, -0.05, 0.05, 0.20));
        $this->assertSame(ClasificacionDesviacion::SobrecostoAlto, ClasificacionDesviacion::clasificar(0.2001, -0.05, 0.05, 0.20));
    }

    protected function cesarea(int $minutosSala = 120, int $minutosAnestesia = 120, bool $conPlantilla = true): Cirugia
    {
        $hospital = Hospital::factory()->create(['horas_dia' => 12, 'dias_mes' => 26, 'factor_indirecto' => 0]);
        HospitalContext::set($hospital->id);

        $sala = SalaOperatoria::factory()->create(['hospital_id' => $hospital->id, 'costo_hora' => 40_000]);
        $tarifas = [
            'cirujano' => [10_000_000, 4_600_000, 1_000_000],
            'ayudante' => [6_500_000, 2_460_000, 400_000],
            'anestesiologo' => [10_400_000, 4_200_000, 1_000_000],
            'instrumentador' => [4_200_000, 1_640_000, 400_000],
            'circulante' => [3_120_000, 1_260_000, 300_000],
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

        $procedimiento = ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $hospital->id,
            'duracion_estimada_minutos' => 120,
        ]);
        $insumo = Insumo::factory()->create(['hospital_id' => $hospital->id, 'costo_unitario' => 150_000]);

        if ($conPlantilla) {
            foreach (['cirujano' => 90, 'ayudante' => 90, 'anestesiologo' => null, 'instrumentador' => null, 'circulante' => null] as $rol => $minutos) {
                PlantillaPersonal::create([
                    'procedimiento_quirurgico_id' => $procedimiento->id,
                    'rol' => $rol,
                    'fase' => 'quirurgica',
                    'cantidad' => 1,
                    'minutos' => $minutos,
                ]);
            }
            PlantillaInsumo::create([
                'procedimiento_quirurgico_id' => $procedimiento->id,
                'insumo_id' => $insumo->id,
                'fase' => 'quirurgica',
                'cantidad' => 1,
            ]);
        }

        $cirugia = Cirugia::create([
            'hospital_id' => $hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $hospital->id])->id,
            'sala_operatoria_id' => $sala->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => now()->parse('2026-06-10 08:00:00')->addMinutes($minutosSala)->toDateTimeString(),
            'tipo' => 'programada',
            'estado' => 'realizada',
        ]);
        $cirugia->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

        foreach ([['cirujano', 90], ['ayudante', 90], ['anestesiologo', $minutosAnestesia], ['instrumentador', 120], ['circulante', 120]] as [$rol, $minutos]) {
            MiembroEquipoQuirurgico::create([
                'cirugia_id' => $cirugia->id,
                'recurso_humano_id' => $recursos[$rol]->id,
                'rol' => $rol,
                'minutos_participacion' => $minutos,
            ]);
        }

        $cirugia->consumos()->create([
            'insumo_id' => $insumo->id,
            'cantidad' => 1,
            'costo_unitario_registrado' => 150_000,
            'costo_total' => 150_000,
        ]);

        return $cirugia;
    }
}
