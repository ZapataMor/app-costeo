<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Services\Indicators\KpiService;
use App\Support\HospitalContext;
use App\Support\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KpiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected User $usuario;

    protected ProcedimientoQuirurgico $procedimiento;

    protected SalaOperatoria $sala;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create(['horas_dia' => 12, 'dias_mes' => 26]);
        $this->usuario = User::factory()->create(['hospital_id' => $this->hospital->id]);

        HospitalContext::set($this->hospital->id);
        $this->procedimiento = ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $this->hospital->id,
            'tarifa_soat' => 1_000_000,
        ]);
        $this->sala = SalaOperatoria::factory()->create(['hospital_id' => $this->hospital->id]);
        HospitalContext::clear();

        $this->actingAs($this->usuario);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_costo_promedio_y_variabilidad(): void
    {
        // Tres cirugías del mismo procedimiento con costos 100k, 200k y 300k
        foreach ([100_000, 200_000, 300_000] as $costo) {
            $this->crearCirugiaCosteada($costo);
        }

        $this->getJson('/api/v1/kpis/costos')
            ->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 3)
            ->assertJsonPath('global.costo_promedio', 200000)
            ->assertJsonPath('por_procedimiento.0.n', 3)
            ->assertJsonPath('por_procedimiento.0.costo_minimo', 100000)
            ->assertJsonPath('por_procedimiento.0.costo_maximo', 300000);

        // Desviación muestral de (100k, 200k, 300k) = 100.000 → CV = 0,5
        $this->getJson('/api/v1/kpis/variabilidad')
            ->assertOk()
            ->assertJsonPath('por_procedimiento.0.media', 200000)
            ->assertJsonPath('por_procedimiento.0.desviacion', 100000)
            ->assertJsonPath('por_procedimiento.0.coeficiente_variacion', 0.5)
            ->assertJsonPath('por_procedimiento.0.nivel_variabilidad', 'alta');
    }

    public function test_margen_contra_tarifa_facturada_y_referencia_soat(): void
    {
        $cirugia = $this->crearCirugiaCosteada(600_000);
        Facturacion::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'valor_facturado' => 750_000,
            'valor_glosado' => 0,
            'valor_recaudado' => 750_000,
        ]);

        $this->getJson('/api/v1/kpis/margen')
            ->assertOk()
            ->assertJsonPath('factor_referencia_soat', 0.75)
            ->assertJsonPath('por_procedimiento.0.costo_promedio', 600000)
            ->assertJsonPath('por_procedimiento.0.facturado_promedio', 750000)
            // referencia = SOAT 1.000.000 × 0,75 = 750.000
            ->assertJsonPath('por_procedimiento.0.tarifa_referencia', 750000)
            ->assertJsonPath('por_procedimiento.0.margen_vs_facturado', 150000)
            ->assertJsonPath('por_procedimiento.0.margen_vs_referencia', 150000)
            ->assertJsonPath('por_procedimiento.0.margen_vs_referencia_pct', 0.2);
    }

    public function test_glosas_y_recaudo(): void
    {
        $cirugia = $this->crearCirugiaCosteada(500_000);
        Facturacion::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'valor_facturado' => 1_000_000,
            'valor_glosado' => 100_000,
            'valor_recaudado' => 800_000,
        ]);

        $this->getJson('/api/v1/kpis/glosas-recaudo')
            ->assertOk()
            ->assertJsonPath('valor_facturado', 1000000)
            ->assertJsonPath('tasa_glosas', 0.1)
            ->assertJsonPath('tasa_recaudo', 0.8);
    }

    public function test_utilizacion_de_salas(): void
    {
        // Dos cirugías de 2 h en junio 2026 → 240 min de 18.720 disponibles
        $this->crearCirugiaCosteada(100_000, inicio: '2026-06-05 08:00:00', duracionMinutos: 120);
        $this->crearCirugiaCosteada(100_000, inicio: '2026-06-12 10:00:00', duracionMinutos: 120);

        $this->getJson('/api/v1/kpis/utilizacion-salas?mes=2026-06')
            ->assertOk()
            ->assertJsonPath('mes', '2026-06')
            ->assertJsonPath('por_sala.0.minutos_usados', 240)
            ->assertJsonPath('por_sala.0.minutos_disponibles', 18720)
            ->assertJsonPath('por_sala.0.utilizacion_pct', 0.0128);
    }

    public function test_utilizacion_de_salas_sigue_el_periodo_seleccionado(): void
    {
        // Una cirugía de 2 h en mayo y otra en junio.
        $this->crearCirugiaCosteada(100_000, inicio: '2026-05-05 08:00:00', duracionMinutos: 120);
        $this->crearCirugiaCosteada(100_000, inicio: '2026-06-12 10:00:00', duracionMinutos: 120);

        HospitalContext::set($this->hospital->id);

        $kpis = (new KpiService)->enPeriodo(
            new Periodo(Carbon::parse('2026-05-01'), Carbon::parse('2026-06-30')->endOfDay()),
        );

        $utilizacion = $kpis->utilizacionSalas();

        // El KPI ignoraba el periodo y se quedaba siempre en un solo mes: el
        // panel decía «toda la historia» y el número medía otra cosa.
        $this->assertSame(240, $utilizacion['global']['minutos_usados']);
        $this->assertSame(2, $utilizacion['global']['n_cirugias']);

        // Dos meses naturales completos: el doble de capacidad, no la de uno.
        $this->assertSame(18720 * 2, $utilizacion['por_sala'][0]['minutos_disponibles']);
        $this->assertSame('2026-05-01', $utilizacion['ventana']['desde']);
        $this->assertSame('2026-06-30', $utilizacion['ventana']['hasta']);
    }

    public function test_tendencia_mensual_calcula_la_variacion_entre_meses(): void
    {
        // Mayo: dos cirugías de 400k y 600k (promedio 500k).
        $this->crearCirugiaCosteada(400_000, inicio: '2026-05-05 08:00:00');
        $this->crearCirugiaCosteada(600_000, inicio: '2026-05-20 08:00:00');
        // Junio: una de 400k → el promedio baja un 20 %.
        $this->crearCirugiaCosteada(400_000, inicio: '2026-06-10 08:00:00');

        HospitalContext::set($this->hospital->id);

        $tendencia = (new KpiService)->tendenciaMensual();

        $this->assertCount(2, $tendencia['meses']);
        $this->assertSame('2026-05', $tendencia['meses'][0]['mes']);
        $this->assertSame(500_000.0, $tendencia['meses'][0]['costo_promedio']);
        $this->assertSame(2, $tendencia['meses'][0]['n']);
        $this->assertSame('2026-06', $tendencia['meses'][1]['mes']);
        $this->assertSame(-0.2, $tendencia['variacion_ultimo_mes']);
    }

    public function test_completitud_de_captura(): void
    {
        // Una cirugía costeada (sin equipo/insumos/resultado/factura) y una sin costear
        $this->crearCirugiaCosteada(100_000);
        $this->crearCirugia();

        $this->getJson('/api/v1/kpis/completitud')
            ->assertOk()
            ->assertJsonPath('total_cirugias_realizadas', 2)
            ->assertJsonPath('chequeos.costo_calculado.registradas', 1)
            ->assertJsonPath('chequeos.costo_calculado.porcentaje', 0.5)
            ->assertJsonPath('completas', 0)
            ->assertJsonPath('completitud_global', 0);
    }

    public function test_outliers_detecta_costos_atipicos(): void
    {
        // Nueve costos normales alrededor de 500k y uno desbordado (5M)
        foreach ([480_000, 490_000, 495_000, 500_000, 505_000, 510_000, 515_000, 520_000, 525_000] as $costo) {
            $this->crearCirugiaCosteada($costo);
        }
        $outlier = $this->crearCirugiaCosteada(5_000_000);

        $respuesta = $this->getJson('/api/v1/kpis/outliers')->assertOk();

        $grupo = $respuesta->json('grupos.0');
        $this->assertSame(10, $grupo['n']);
        $this->assertSame(1, $grupo['total_outliers']);

        $puntoOutlier = collect($grupo['puntos'])->firstWhere('cirugia_id', $outlier->id);
        $this->assertTrue($puntoOutlier['es_outlier']);
        $this->assertContains('iqr', $puntoOutlier['criterios']);
    }

    protected function crearCirugia(?string $inicio = null, int $duracionMinutos = 120): Cirugia
    {
        $inicioCarbon = $inicio !== null
            ? Carbon::parse($inicio)
            : Carbon::parse('2026-06-15 08:00:00');

        HospitalContext::set($this->hospital->id);

        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $this->sala->id,
            'fecha' => $inicioCarbon->toDateString(),
            'hora_inicio' => $inicioCarbon,
            'hora_fin' => $inicioCarbon->copy()->addMinutes($duracionMinutos),
            'estado' => 'realizada',
        ]);
        $cirugia->procedimientos()->attach($this->procedimiento->id, ['es_principal' => true]);

        HospitalContext::clear();

        return $cirugia;
    }

    protected function crearCirugiaCosteada(int $costoTotal, ?string $inicio = null, int $duracionMinutos = 120): Cirugia
    {
        $cirugia = $this->crearCirugia($inicio, $duracionMinutos);

        CostoCirugia::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'costo_total' => $costoTotal,
        ]);

        return $cirugia;
    }
}
