<?php

namespace Tests\Unit;

use App\Enums\ComponenteCosto;
use App\Enums\EstadoAlerta;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Services\Costing\DetectorSobrecostos;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectorSobrecostosTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected ProcedimientoQuirurgico $procedimiento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create();
        HospitalContext::set($this->hospital->id);
        $this->procedimiento = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospital->id]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_sin_suficientes_comparables_no_se_genera_alerta(): void
    {
        // Cuatro cirugías previas: una menos del mínimo. Con un baseline así
        // de pobre cualquier caso parece atípico, y llenar la bandeja de ruido
        // al arrancar es la forma más rápida de que nadie vuelva a mirarla.
        $this->crearCosteadas(array_fill(0, DetectorSobrecostos::MINIMO_BASELINE - 1, 1_000_000));

        $costo = $this->crearCosteada(50_000_000);

        $this->assertNull(app(DetectorSobrecostos::class)->evaluar($costo));
        $this->assertDatabaseCount('alertas_sobrecosto', 0);
    }

    public function test_un_costo_dentro_de_rango_no_genera_alerta(): void
    {
        $this->crearCosteadas([1_000_000, 1_050_000, 950_000, 1_020_000, 980_000, 1_010_000]);

        $costo = $this->crearCosteada(1_030_000);

        $this->assertNull(app(DetectorSobrecostos::class)->evaluar($costo));
        $this->assertDatabaseCount('alertas_sobrecosto', 0);
    }

    public function test_un_costo_muy_por_encima_genera_alerta_con_el_exceso_calculado(): void
    {
        $this->crearCosteadas([1_000_000, 1_050_000, 950_000, 1_020_000, 980_000, 1_010_000]);

        $costo = $this->crearCosteada(5_000_000);

        $alerta = app(DetectorSobrecostos::class)->evaluar($costo);

        $this->assertNotNull($alerta);
        $this->assertSame(EstadoAlerta::Pendiente, $alerta->estado);
        $this->assertSame(6, $alerta->n_baseline);
        // Mediana del baseline = 1.005.000 → exceso = 3.995.000
        $this->assertEqualsWithDelta(1_005_000.0, (float) $alerta->costo_esperado, 0.01);
        $this->assertEqualsWithDelta(3_995_000.0, (float) $alerta->exceso, 0.01);
        $this->assertContains('iqr', $alerta->criterios);
    }

    public function test_un_exceso_pequeno_no_genera_alerta_aunque_la_estadistica_lo_marque(): void
    {
        // Baseline idéntico: el IQR vale cero y la desviación también, así que
        // por criterio estadístico puro *cualquier* peso de más queda «fuera
        // de rango». En un procedimiento muy estandarizado eso llenaría la
        // bandeja de excesos triviales y la volvería inservible.
        $this->crearCosteadas(array_fill(0, 6, 1_000_000));

        $this->assertNull(
            app(DetectorSobrecostos::class)->evaluar($this->crearCosteada(1_030_000)),
        );

        // El mismo baseline degenerado sí alerta cuando el exceso es real.
        $this->assertNotNull(
            app(DetectorSobrecostos::class)->evaluar($this->crearCosteada(3_000_000)),
        );
    }

    public function test_un_costo_anormalmente_bajo_no_genera_alerta(): void
    {
        // Por debajo casi siempre es captura incompleta, no ahorro: mezclarlo
        // aquí llenaría la bandeja de casos que no son sobrecostos.
        $this->crearCosteadas([1_000_000, 1_050_000, 950_000, 1_020_000, 980_000, 1_010_000]);

        $costo = $this->crearCosteada(10_000);

        $this->assertNull(app(DetectorSobrecostos::class)->evaluar($costo));
    }

    public function test_el_exceso_se_atribuye_al_componente_que_lo_causo(): void
    {
        // Baseline homogéneo: 300k de personal, 100k de sala, 600k de insumos.
        foreach (range(1, 6) as $i) {
            $this->crearCosteada(1_000_000, [
                'costo_recurso_humano' => 300_000,
                'costo_sala' => 100_000,
                'costo_equipos' => 0,
                'costo_insumos' => 600_000,
            ]);
        }

        // El caso atípico se desvía solo en insumos.
        $costo = $this->crearCosteada(5_000_000, [
            'costo_recurso_humano' => 300_000,
            'costo_sala' => 100_000,
            'costo_equipos' => 0,
            'costo_insumos' => 4_600_000,
        ]);

        $alerta = app(DetectorSobrecostos::class)->evaluar($costo);

        $this->assertNotNull($alerta);
        $this->assertSame(ComponenteCosto::Insumos, $alerta->componente_dominante);

        $insumos = collect($alerta->atribucion)->firstWhere('componente', 'insumos');
        $this->assertEqualsWithDelta(4_000_000.0, $insumos['exceso'], 0.01);
        // Todo el exceso viene de un solo componente: aporta el 100 %.
        $this->assertEqualsWithDelta(1.0, $insumos['aporte_pct'], 0.001);

        $sala = collect($alerta->atribucion)->firstWhere('componente', 'sala');
        $this->assertEqualsWithDelta(0.0, $sala['exceso'], 0.01);
    }

    public function test_recostear_dentro_de_rango_retira_la_alerta_pendiente(): void
    {
        $this->crearCosteadas([1_000_000, 1_050_000, 950_000, 1_020_000, 980_000, 1_010_000]);

        $costo = $this->crearCosteada(5_000_000);
        $detector = app(DetectorSobrecostos::class);

        $this->assertNotNull($detector->evaluar($costo));

        // Se corrige el registro y el costo vuelve a lo normal: la alerta ya
        // no describe nada real y mandaría a revisar un exceso inexistente.
        $costo->update(['costo_total' => 1_000_000]);

        $this->assertNull($detector->evaluar($costo->fresh()));
        $this->assertDatabaseCount('alertas_sobrecosto', 0);
    }

    public function test_recostear_sin_cambios_no_borra_una_revision_ya_hecha(): void
    {
        $this->crearCosteadas([1_000_000, 1_050_000, 950_000, 1_020_000, 980_000, 1_010_000]);

        $costo = $this->crearCosteada(5_000_000);
        $detector = app(DetectorSobrecostos::class);
        $alerta = $detector->evaluar($costo);

        $alerta->update([
            'estado' => EstadoAlerta::Revisada,
            'causa' => 'consumo_excesivo_insumos',
        ]);

        // Recostear no debe tirar a la basura la causa que alguien averiguó.
        $detector->evaluar($costo->fresh());

        $alerta->refresh();
        $this->assertSame(EstadoAlerta::Revisada, $alerta->estado);
        $this->assertSame('consumo_excesivo_insumos', $alerta->causa->value);
    }

    public function test_el_baseline_no_cruza_hospitales(): void
    {
        $otro = Hospital::factory()->create();
        $procedimientoOtro = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $otro->id]);

        HospitalContext::set($otro->id);
        foreach (range(1, 6) as $i) {
            $this->crearCosteada(1_000_000, [], $otro, $procedimientoOtro);
        }
        HospitalContext::set($this->hospital->id);

        // En su propio hospital esta cirugía es la primera: sin comparables
        // propios no hay alerta, aunque el otro hospital tenga historia.
        $costo = $this->crearCosteada(5_000_000);

        $this->assertNull(app(DetectorSobrecostos::class)->evaluar($costo));
    }

    /** @param  list<int|float>  $totales */
    private function crearCosteadas(array $totales): void
    {
        foreach ($totales as $total) {
            $this->crearCosteada($total);
        }
    }

    /** @param  array<string, float|int>  $componentes */
    private function crearCosteada(
        float|int $costoTotal,
        array $componentes = [],
        ?Hospital $hospital = null,
        ?ProcedimientoQuirurgico $procedimiento = null,
    ): CostoCirugia {
        $hospital ??= $this->hospital;
        $procedimiento ??= $this->procedimiento;

        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $hospital->id])->id,
            'sala_operatoria_id' => null,
        ]);

        $cirugia->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

        $costo = CostoCirugia::factory()->create([
            ...$componentes,
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $hospital->id,
            'costo_total' => $costoTotal,
        ]);

        $costo->setRelation('cirugia', $cirugia);

        return $costo;
    }
}
