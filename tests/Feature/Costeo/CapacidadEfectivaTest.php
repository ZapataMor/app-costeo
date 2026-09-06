<?php

namespace Tests\Feature\Costeo;

use App\Enums\EstadoCirugia;
use App\Enums\FaseCiclo;
use App\Enums\RolQuirurgico;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\User;
use App\Services\Cirugias\RegistrarCirugia;
use App\Services\Costing\TdabcCostingService;
use App\Services\Indicators\PersonalCosteoService;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Capacidad práctica parametrizable (`hospitales.minutos_efectivos_hora`).
 *
 * El default de 60 hace que el resto de la suite pase sin tocarla, lo que
 * significa que el cableado nuevo podría estar muerto sin que nadie se entere.
 * Estas pruebas fuerzan un hospital a 40 minutos efectivos y verifican el
 * efecto por los DOS caminos que calculan la tarifa por minuto: el motor de
 * costeo (PHP) y PersonalCosteoService (SQL agregado).
 */
class CapacidadEfectivaTest extends TestCase
{
    use RefreshDatabase;

    /** Costo mensual que a 18.720 min da $100/min exactos. */
    private const COSTO_MENSUAL = 1_872_000;

    private const MINUTOS_PARTICIPACION = 120;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_la_capacidad_por_defecto_no_cambia_el_comportamiento_anterior(): void
    {
        $hospital = Hospital::factory()->create(['horas_dia' => 12, 'dias_mes' => 26]);

        $this->assertSame(60, $hospital->minutos_efectivos_hora);
        $this->assertSame(18_720, $hospital->minutosDisponiblesMes());
    }

    public function test_cuarenta_minutos_efectivos_reducen_la_capacidad_un_tercio(): void
    {
        $hospital = Hospital::factory()->create([
            'horas_dia' => 12,
            'dias_mes' => 26,
            'minutos_efectivos_hora' => 40,
        ]);

        $this->assertSame(12_480, $hospital->minutosDisponiblesMes());
    }

    /** Camino 1: el motor de costeo en PHP. */
    public function test_el_motor_de_costeo_encarece_el_minuto_un_50_por_ciento_con_40_minutos_efectivos(): void
    {
        $costoA60 = (float) $this->costearCirugiaCon(60)->costo_recurso_humano;
        $costoA40 = (float) $this->costearCirugiaCon(40)->costo_recurso_humano;

        // 1.872.000 ÷ 18.720 = $100/min × 120 min = 12.000
        $this->assertEqualsWithDelta(12_000.0, $costoA60, 0.01);
        // 1.872.000 ÷ 12.480 = $150/min × 120 min = 18.000
        $this->assertEqualsWithDelta(18_000.0, $costoA40, 0.01);

        $this->assertEqualsWithDelta(1.5, $costoA40 / $costoA60, 0.0001);
    }

    /** Camino 2: el SQL agregado de PersonalCosteoService. */
    public function test_el_indicador_de_personal_encarece_el_minuto_un_50_por_ciento_con_40_minutos_efectivos(): void
    {
        $costoA60 = $this->costoPropioDelIndicadorCon(60);
        $costoA40 = $this->costoPropioDelIndicadorCon(40);

        $this->assertEqualsWithDelta(12_000.0, $costoA60, 0.01);
        $this->assertEqualsWithDelta(18_000.0, $costoA40, 0.01);

        $this->assertEqualsWithDelta(1.5, $costoA40 / $costoA60, 0.0001);
    }

    /**
     * Guardián de la duplicación PHP/SQL: la fórmula de RH vive en
     * TdabcCostingService y, por rendimiento, otra vez como expresión SQL en
     * PersonalCosteoService. Si alguna de las dos cambia sin la otra, esto
     * falla antes de que las cifras se contradigan en pantalla.
     */
    public function test_la_ficha_de_costo_y_el_indicador_de_personal_dan_lo_mismo(): void
    {
        foreach ([60, 40, 45] as $minutosEfectivos) {
            $costo = $this->costearCirugiaCon($minutosEfectivos);

            HospitalContext::set($costo->hospital_id);
            $indicador = (new PersonalCosteoService)->totales();
            HospitalContext::clear();

            $this->assertEqualsWithDelta(
                (float) $costo->costo_recurso_humano,
                (float) $indicador['costo_propio_total'],
                0.01,
                "Ficha e indicador divergen con {$minutosEfectivos} minutos efectivos/hora.",
            );
        }
    }

    public function test_el_registro_congela_los_minutos_efectivos_usados(): void
    {
        $hospital = Hospital::factory()->create([
            'horas_dia' => 12,
            'dias_mes' => 26,
            'minutos_efectivos_hora' => 40,
        ]);
        HospitalContext::set($hospital->id);

        $cirugia = $this->registrar($hospital);

        $this->assertSame(40, $cirugia->minutos_efectivos_hora_registrado);
        $this->assertSame(12_480, $cirugia->minutos_disponibles_mes_registrado);

        // El snapshot manda: cambiar el parámetro no reescribe la historia.
        $hospital->update(['minutos_efectivos_hora' => 60]);

        $costo = app(TdabcCostingService::class)->calcular($cirugia->fresh());

        $this->assertSame(40, $costo->detalle['minutos_efectivos_hora']);
        $this->assertSame(12_480, $costo->detalle['minutos_disponibles_mes']);
        $this->assertEqualsWithDelta(18_000.0, (float) $costo->costo_recurso_humano, 0.01);
    }

    public function test_la_configuracion_rechaza_minutos_efectivos_fuera_de_rango(): void
    {
        $hospital = Hospital::factory()->create();
        $admin = User::factory()->create(['hospital_id' => $hospital->id]);

        foreach ([0, 61] as $invalido) {
            $this->actingAs($admin)->put('/parametros/hospital', [
                'horas_dia' => 12,
                'dias_mes' => 26,
                'minutos_efectivos_hora' => $invalido,
                'factor_indirecto' => 0,
            ])->assertSessionHasErrors('minutos_efectivos_hora');
        }

        $this->assertSame(60, $hospital->fresh()->minutos_efectivos_hora);
    }

    public function test_la_configuracion_acepta_40_minutos_efectivos(): void
    {
        $hospital = Hospital::factory()->create();
        $admin = User::factory()->create(['hospital_id' => $hospital->id]);

        $this->actingAs($admin)->put('/parametros/hospital', [
            'horas_dia' => 12,
            'dias_mes' => 26,
            'minutos_efectivos_hora' => 40,
            'factor_indirecto' => 0,
        ])->assertRedirect('/parametros/hospital');

        $this->assertSame(40, $hospital->fresh()->minutos_efectivos_hora);
        $this->assertSame(12_480, $hospital->fresh()->minutosDisponiblesMes());
    }

    /** Cirugía registrada y costeada en un hospital con esa capacidad efectiva. */
    private function costearCirugiaCon(int $minutosEfectivos): CostoCirugia
    {
        $hospital = Hospital::factory()->create([
            'horas_dia' => 12,
            'dias_mes' => 26,
            'minutos_efectivos_hora' => $minutosEfectivos,
        ]);
        HospitalContext::set($hospital->id);

        $costo = app(TdabcCostingService::class)->calcular($this->registrar($hospital));

        HospitalContext::clear();

        return $costo;
    }

    /**
     * Igual que el anterior pero leído por el indicador, y con el snapshot en
     * null a propósito: así el SQL cae en el `coalesce` que calcula la
     * capacidad desde `hospitales`, que es el camino donde vivía el `* 60`.
     */
    private function costoPropioDelIndicadorCon(int $minutosEfectivos): float
    {
        $hospital = Hospital::factory()->create([
            'horas_dia' => 12,
            'dias_mes' => 26,
            'minutos_efectivos_hora' => $minutosEfectivos,
        ]);
        HospitalContext::set($hospital->id);

        $cirugia = $this->registrar($hospital);
        $cirugia->forceFill(['minutos_disponibles_mes_registrado' => null])->save();

        CostoCirugia::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $hospital->id,
        ]);

        $total = (float) (new PersonalCosteoService)->totales()['costo_propio_total'];

        HospitalContext::clear();

        return $total;
    }

    /** Cirugía realizada con un solo cirujano, vía el servicio de registro. */
    private function registrar(Hospital $hospital): Cirugia
    {
        $cirujano = RecursoHumano::factory()->create([
            'hospital_id' => $hospital->id,
            'rol' => RolQuirurgico::Cirujano->value,
            'salario_mensual' => self::COSTO_MENSUAL,
            'prestaciones_mensuales' => 0,
            'costos_indirectos_mensuales' => 0,
        ]);

        $paciente = Paciente::factory()->create(['hospital_id' => $hospital->id]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $hospital->id]);

        $cirugia = app(RegistrarCirugia::class)->ejecutar([
            'hospital_id' => $hospital->id,
            'paciente_id' => $paciente->id,
            // Sin sala ni insumos: el costo de RH queda aislado y comparable.
            'sala_operatoria_id' => null,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => '2026-06-10 10:00:00',
            'tipo' => 'programada',
            'estado' => EstadoCirugia::Realizada->value,
            'procedimientos' => [['id' => $procedimiento->id, 'es_principal' => true]],
            'equipo' => [[
                'recurso_humano_id' => $cirujano->id,
                'rol' => RolQuirurgico::Cirujano->value,
                'fase' => FaseCiclo::Quirurgica->value,
                'minutos_participacion' => self::MINUTOS_PARTICIPACION,
            ]],
        ]);

        // El servicio de registro no crea participaciones huérfanas; esta
        // aserción protege el supuesto de los cálculos de arriba.
        $this->assertSame(1, MiembroEquipoQuirurgico::where('cirugia_id', $cirugia->id)->count());

        return $cirugia;
    }
}
