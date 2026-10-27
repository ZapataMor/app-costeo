<?php

namespace Tests\Feature\Costeo;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Models\Cirugia;
use App\Models\CirugiaConceptoIndirecto;
use App\Models\ConceptoCostoIndirecto;
use App\Models\CostoCirugia;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\User;
use App\Support\HospitalContext;
use App\Support\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Resumen de Costeo quirúrgico: periodo por defecto, comparación contra el
 * periodo anterior a igual mezcla, desviación frente al estándar, resultado
 * mensual y capacidad no utilizada.
 */
class ResumenCosteoTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected User $admin;

    protected ProcedimientoQuirurgico $apendicectomia;

    protected ProcedimientoQuirurgico $colecistectomia;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-27 10:00:00');

        $this->hospital = Hospital::factory()->create();
        $this->admin = User::factory()->create(['hospital_id' => $this->hospital->id]);
        $this->apendicectomia = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
        $this->colecistectomia = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_por_defecto_muestra_los_ultimos_tres_meses_con_cirugias(): void
    {
        $this->cirugia($this->apendicectomia, '2026-03-10', 100_000);
        $this->cirugia($this->apendicectomia, '2026-09-15', 100_000);

        $this->actingAs($this->admin)
            ->get('/costeo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/index')
                // Anclado a la última cirugía (septiembre), no a hoy.
                ->where('periodo.desde', '2026-07-01')
                ->where('periodo.hasta', '2026-09-30')
                ->where('costos.global.n_cirugias_costeadas', 1)
                ->whereNot('anterior', null));

        $this->actingAs($this->admin)
            ->get('/costeo?todo=1')
            ->assertInertia(fn (Assert $page) => $page
                ->where('periodo.desde', null)
                ->where('costos.global.n_cirugias_costeadas', 2)
                ->where('periodoEtiqueta', 'Toda la historia')
                ->where('anterior', null));
    }

    public function test_la_variacion_del_costo_se_mide_a_igual_mezcla_de_procedimientos(): void
    {
        // Periodo anterior (jul–ago): mucha apendicectomía barata.
        foreach (['2026-07-05', '2026-07-12', '2026-08-02'] as $fecha) {
            $this->cirugia($this->apendicectomia, $fecha, 100_000);
        }
        $this->cirugia($this->colecistectomia, '2026-08-20', 300_000);

        // Periodo actual (sept–oct): mucha colecistectomía cara y ambos
        // procedimientos un 10 % más caros.
        $this->cirugia($this->apendicectomia, '2026-09-03', 110_000);
        foreach (['2026-09-10', '2026-10-01', '2026-10-15'] as $fecha) {
            $this->cirugia($this->colecistectomia, $fecha, 330_000);
        }

        $this->actingAs($this->admin)
            ->get('/costeo?desde=2026-09-01&hasta=2026-10-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // El promedio simple pasa de 150.000 a 275.000 (+83 %) por la
                // mezcla; a igual mezcla el costo subió un 10 %.
                ->where('costos.global.costo_promedio', fn ($v) => abs((float) $v - 275_000) < 0.01)
                ->where('anterior.costos.costo_promedio', fn ($v) => abs((float) $v - 150_000) < 0.01)
                ->where('anterior.mezcla.variacion', fn ($v) => abs((float) $v - 0.10) < 0.0001)
                ->where('anterior.mezcla.cirugias_comparadas', 4)
                ->where('periodoAnteriorEtiqueta', '01/07/2026 a 31/08/2026'));
    }

    public function test_la_desviacion_frente_al_estandar_es_agregada_y_por_procedimiento(): void
    {
        $this->cirugia($this->apendicectomia, '2026-09-03', 120_000, [
            'costo_estandar' => 100_000, 'desviacion' => 20_000, 'desviacion_pct' => 0.2,
            'clasificacion_desviacion' => 'sobrecosto_leve',
        ]);
        $this->cirugia($this->apendicectomia, '2026-09-04', 90_000, [
            'costo_estandar' => 100_000, 'desviacion' => -10_000, 'desviacion_pct' => -0.1,
            'clasificacion_desviacion' => 'subejecucion',
        ]);
        // Sin protocolo: no entra al estándar, sí al conteo de costeadas.
        $this->cirugia($this->colecistectomia, '2026-09-05', 500_000);

        $this->actingAs($this->admin)
            ->get('/costeo?desde=2026-09-01&hasta=2026-09-30')
            ->assertInertia(fn (Assert $page) => $page
                ->where('desviacion.n', 2)
                ->where('desviacion.n_costeadas', 3)
                // (120.000 + 90.000) ÷ 200.000 − 1 = 5 %
                ->where('desviacion.desviacion_pct', fn ($v) => abs((float) $v - 0.05) < 0.0001)
                ->where('desviacion.por_clasificacion.sobrecosto_leve', 1)
                ->where('desviacion.por_clasificacion.subejecucion', 1)
                ->where('desviacion.por_clasificacion.en_rango', 0));

        $this->actingAs($this->admin)
            ->get('/costeo/procedimientos?desde=2026-09-01&hasta=2026-09-30')
            ->assertInertia(fn (Assert $page) => $page
                ->where('procedimientos', fn ($filas) => collect($filas)
                    ->firstWhere('id', $this->apendicectomia->id)['indicadores']['desviacion_pct'] == 0.05)
                ->where('procedimientos', fn ($filas) => collect($filas)
                    ->firstWhere('id', $this->colecistectomia->id)['indicadores']['desviacion_pct'] === null));
    }

    public function test_la_tendencia_trae_el_resultado_mensual_solo_de_lo_facturado(): void
    {
        $facturada = $this->cirugia($this->apendicectomia, '2026-09-03', 150_000);
        $this->cirugia($this->apendicectomia, '2026-09-04', 150_000);

        Facturacion::factory()->create([
            'cirugia_id' => $facturada->id,
            'hospital_id' => $this->hospital->id,
            'valor_facturado' => 200_000,
            'valor_glosado' => 0,
            'valor_recaudado' => 200_000,
        ]);

        $this->actingAs($this->admin)
            ->get('/costeo?desde=2026-09-01&hasta=2026-09-30')
            ->assertInertia(fn (Assert $page) => $page
                ->where('resultado.margen', fn ($v) => abs((float) $v - 50_000) < 0.01)
                ->where('tendencia.meses.0.n', 2)
                ->where('tendencia.meses.0.n_facturadas', 1)
                ->where('tendencia.meses.0.costo_facturadas', fn ($v) => abs((float) $v - 150_000) < 0.01)
                ->where('tendencia.meses.0.margen', fn ($v) => abs((float) $v - 50_000) < 0.01)
                ->where('tendencia.meses.0.margen_pct', fn ($v) => abs((float) $v - 0.25) < 0.0001));
    }

    public function test_la_capacidad_no_utilizada_resta_lo_absorbido_a_las_bolsas(): void
    {
        $concepto = ConceptoCostoIndirecto::factory()
            ->categoria(CategoriaCif::PersonalIndirecto)
            ->create([
                'hospital_id' => $this->hospital->id,
                'monto_mensual' => 3_000_000,
                'vigente_desde' => '2026-01-01',
            ]);
        // En producción solo ActivarCategoriaCif la enciende; aquí basta el dato.
        ConceptoCostoIndirecto::query()->whereKey($concepto->id)->update(['activo' => true]);

        // Una bolsa por porcentaje no tiene monto fijo: no cuenta.
        ConceptoCostoIndirecto::factory()->porcentajeDirecto()->create(['hospital_id' => $this->hospital->id]);

        $cirugia = $this->cirugia($this->apendicectomia, '2026-09-10', 1_200_000);

        HospitalContext::set($this->hospital->id);
        CirugiaConceptoIndirecto::query()->create([
            'cirugia_id' => $cirugia->id,
            'concepto_costo_indirecto_id' => $concepto->id,
            'hospital_id' => $this->hospital->id,
            'nombre_registrado' => $concepto->nombre,
            'categoria_registrada' => CategoriaCif::PersonalIndirecto->value,
            'base_asignacion_registrada' => BaseAsignacionCif::MinutoPersonal->value,
            'monto_mensual_registrado' => 3_000_000,
            'denominador_registrado' => 18_720,
            'tasa_registrada' => 160.26,
            'unidades_aplicadas' => 6_240,
            'monto_asignado' => 1_000_000,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->admin)
            ->get('/costeo?desde=2026-09-01&hasta=2026-09-30')
            ->assertInertia(fn (Assert $page) => $page
                ->where('capacidad.bolsas.monto', fn ($v) => abs((float) $v - 3_000_000) < 0.01)
                ->where('capacidad.bolsas.absorbido', fn ($v) => abs((float) $v - 1_000_000) < 0.01)
                ->where('capacidad.bolsas.no_absorbido', fn ($v) => abs((float) $v - 2_000_000) < 0.01)
                ->where('capacidad.bolsas.absorcion_pct', fn ($v) => abs((float) $v - 0.3333) < 0.0001));

        // Media quincena: la bolsa pesa por los días de la ventana.
        $this->actingAs($this->admin)
            ->get('/costeo?desde=2026-09-01&hasta=2026-09-15')
            ->assertInertia(fn (Assert $page) => $page
                ->where('capacidad.bolsas.monto', fn ($v) => abs((float) $v - 1_500_000) < 0.01));
    }

    public function test_el_periodo_anterior_respeta_meses_naturales_o_dias(): void
    {
        $trimestre = new Periodo(Carbon::parse('2026-08-01'), Carbon::parse('2026-10-31')->endOfDay());
        $this->assertSame(
            ['desde' => '2026-05-01', 'hasta' => '2026-07-31'],
            $trimestre->anterior()?->aArray(),
        );

        $diez = new Periodo(Carbon::parse('2026-10-11'), Carbon::parse('2026-10-20')->endOfDay());
        $this->assertSame(
            ['desde' => '2026-10-01', 'hasta' => '2026-10-10'],
            $diez->anterior()?->aArray(),
        );

        $this->assertNull((new Periodo)->anterior());
        $this->assertNull((new Periodo(Carbon::parse('2026-10-01')))->anterior());
    }

    /**
     * @param  array<string, mixed>  $costo
     */
    private function cirugia(ProcedimientoQuirurgico $procedimiento, string $fecha, float $costoTotal, array $costo = []): Cirugia
    {
        HospitalContext::set($this->hospital->id);

        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $this->hospital->id])->id,
            'sala_operatoria_id' => null,
            'fecha' => $fecha,
        ]);
        $cirugia->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

        CostoCirugia::factory()->create(array_merge([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'costo_total' => $costoTotal,
        ], $costo));

        HospitalContext::clear();

        return $cirugia;
    }
}
