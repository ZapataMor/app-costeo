<?php

namespace Tests\Feature\Costeo;

use App\Enums\EstadoCirugia;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ventana temporal de los indicadores y filtros de los listados: sin ellos
 * los KPIs solo respondían «desde siempre» y los catálogos había que
 * recorrerlos página por página.
 */
class PeriodoYFiltrosTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create();
        $this->admin = User::factory()->create([
            'hospital_id' => $this->hospital->id,
            'role' => 'admin_hospital',
            'activo' => true,
        ]);

        HospitalContext::set($this->hospital->id);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    protected function cirugiaCosteada(string $fecha, float $costoTotal): Cirugia
    {
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);

        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'estado' => EstadoCirugia::Realizada->value,
            'fecha' => $fecha,
            'hora_inicio' => "{$fecha} 08:00:00",
            'hora_fin' => "{$fecha} 09:00:00",
        ]);

        $cirugia->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

        CostoCirugia::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'costo_total' => $costoTotal,
        ]);

        return $cirugia;
    }

    public function test_el_periodo_acota_el_costo_promedio(): void
    {
        $this->cirugiaCosteada('2026-01-15', 100000);
        $this->cirugiaCosteada('2026-06-15', 500000);

        // Sin periodo: las dos.
        $this->actingAs($this->admin)
            ->getJson('/api/v1/kpis/costos')
            ->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 2)
            ->assertJsonPath('global.costo_promedio', 300000);

        // Acotado al primer semestre temprano: solo la de enero.
        $this->actingAs($this->admin)
            ->getJson('/api/v1/kpis/costos?desde=2026-01-01&hasta=2026-03-31')
            ->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 1)
            ->assertJsonPath('global.costo_promedio', 100000);
    }

    public function test_el_periodo_acota_glosas_y_recaudo(): void
    {
        $enero = $this->cirugiaCosteada('2026-01-15', 100000);
        $junio = $this->cirugiaCosteada('2026-06-15', 100000);

        foreach ([$enero, $junio] as $cirugia) {
            Facturacion::factory()->create([
                'cirugia_id' => $cirugia->id,
                'hospital_id' => $this->hospital->id,
                'valor_facturado' => 200000,
                'valor_glosado' => 0,
                'valor_recaudado' => 200000,
            ]);
        }

        $this->actingAs($this->admin)
            ->getJson('/api/v1/kpis/glosas-recaudo?desde=2026-06-01')
            ->assertOk()
            ->assertJsonPath('n_facturas', 1)
            ->assertJsonPath('valor_facturado', 200000);
    }

    public function test_el_periodo_acota_la_completitud(): void
    {
        $this->cirugiaCosteada('2026-01-15', 100000);
        $this->cirugiaCosteada('2026-06-15', 100000);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/kpis/completitud?hasta=2026-03-31')
            ->assertOk()
            ->assertJsonPath('total_cirugias_realizadas', 1);
    }

    public function test_un_periodo_invalido_se_ignora_en_vez_de_reventar(): void
    {
        $this->cirugiaCosteada('2026-01-15', 100000);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/kpis/costos?desde=no-es-una-fecha')
            ->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 1);
    }

    public function test_el_buscador_de_insumos_filtra_por_nombre_y_codigo(): void
    {
        Insumo::factory()->create([
            'hospital_id' => $this->hospital->id,
            'codigo' => 'MED-001',
            'nombre' => 'Oxitocina 10 UI',
        ]);
        Insumo::factory()->create([
            'hospital_id' => $this->hospital->id,
            'codigo' => 'INS-500',
            'nombre' => 'Gasa estéril',
        ]);

        $this->actingAs($this->admin)
            ->get('/parametros/insumos?q=oxitocina')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('insumos.data', 1)
                ->where('insumos.data.0.codigo', 'MED-001'));

        $this->actingAs($this->admin)
            ->get('/parametros/insumos?q=INS-500')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('insumos.data', 1)
                ->where('insumos.data.0.nombre', 'Gasa estéril'));
    }

    public function test_los_insumos_se_filtran_por_estado_activo(): void
    {
        Insumo::factory()->count(2)->create(['hospital_id' => $this->hospital->id, 'activo' => true]);
        Insumo::factory()->create(['hospital_id' => $this->hospital->id, 'activo' => false]);

        $this->actingAs($this->admin)
            ->get('/parametros/insumos?activo=0')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('insumos.data', 1));
    }

    public function test_la_bandeja_de_pendientes_solo_trae_lo_no_contabilizable(): void
    {
        $this->cirugiaCosteada('2026-06-15', 100000);

        Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'estado' => EstadoCirugia::EnProceso->value,
            'hora_fin' => null,
        ]);

        $this->actingAs($this->admin)
            ->get('/cirugias?pendientes=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('cirugias.data', 1)
                ->where('totalPendientes', 1));
    }

    public function test_el_listado_de_cirugias_busca_por_paciente(): void
    {
        $paciente = Paciente::factory()->create([
            'hospital_id' => $this->hospital->id,
            'nombres' => 'Ana María',
            'apellidos' => 'Epieyú',
        ]);

        Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => $paciente->id,
        ]);
        Cirugia::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->actingAs($this->admin)
            ->get('/cirugias?q=Epieyú')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('cirugias.data', 1));
    }

    public function test_la_exportacion_devuelve_un_csv_con_los_filtros_aplicados(): void
    {
        $this->cirugiaCosteada('2026-01-15', 100000);
        $this->cirugiaCosteada('2026-06-15', 500000);

        $respuesta = $this->actingAs($this->admin)
            ->get('/exportar/cirugias?desde=2026-06-01')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $respuesta->streamedContent();

        // BOM para que Excel respete las tildes.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Costo total', $csv);
        $this->assertStringContainsString('500000', $csv);
        $this->assertStringNotContainsString('2026-01-15', $csv);
    }

    public function test_el_digitador_no_puede_exportar(): void
    {
        $digitador = User::factory()->create([
            'hospital_id' => $this->hospital->id,
            'role' => 'digitador',
            'activo' => true,
        ]);

        $this->actingAs($digitador)->get('/exportar/cirugias')->assertForbidden();
        $this->actingAs($digitador)->get('/exportar/indicadores')->assertForbidden();
    }
}
