<?php

namespace Tests\Feature\Cirugias;

use App\Enums\EstadoCirugia;
use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cierre del ciclo de datos: alta de pacientes, facturación y resultado
 * clínico, que son el origen de los KPIs de margen, glosas, recaudo y
 * completitud de la Capa 3.
 */
class CapturaCicloCompletoTest extends TestCase
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

    /** @return array<string, mixed> */
    protected function datosPaciente(array $sobrescribir = []): array
    {
        return [
            'tipo_documento' => 'CC',
            'documento' => '1122334455',
            'nombres' => 'Ana María',
            'apellidos' => 'Epieyú',
            'regimen' => 'subsidiado',
            'zona' => 'rural',
            ...$sobrescribir,
        ];
    }

    public function test_el_alta_rapida_devuelve_el_id_para_autoseleccionar(): void
    {
        $this->actingAs($this->admin)
            ->from('/cirugias/create')
            ->post('/cirugias/pacientes', $this->datosPaciente())
            ->assertRedirect('/cirugias/create');

        $paciente = Paciente::query()->firstOrFail();

        $this->assertSame('Ana María', $paciente->nombres);
        $this->assertSame($this->hospital->id, $paciente->hospital_id);
        // El documento nunca se guarda en claro.
        $this->assertNotSame('1122334455', $paciente->getRawOriginal('documento'));
    }

    public function test_el_padron_busca_por_documento_exacto_y_por_nombre(): void
    {
        Paciente::factory()->create([
            'hospital_id' => $this->hospital->id,
            'documento' => '9998887',
            'nombres' => 'Ana María',
            'apellidos' => 'Epieyú',
        ]);
        Paciente::factory()->create([
            'hospital_id' => $this->hospital->id,
            'nombres' => 'Carlos',
            'apellidos' => 'Uriana',
        ]);

        $this->actingAs($this->admin)
            ->get('/pacientes?q=9998887')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pacientes.data', 1)
                ->where('pacientes.data.0.nombres', 'Ana María'));

        $this->actingAs($this->admin)
            ->get('/pacientes?q=Uriana')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pacientes.data', 1)
                ->where('pacientes.data.0.nombres', 'Carlos'));
    }

    public function test_no_se_elimina_un_paciente_con_procedimientos(): void
    {
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => $paciente->id,
        ]);

        $this->actingAs($this->admin)
            ->from('/pacientes')
            ->delete("/pacientes/{$paciente->id}")
            ->assertRedirect('/pacientes');

        $this->assertDatabaseHas('pacientes', ['id' => $paciente->id]);
    }

    public function test_se_registra_la_facturacion_de_un_procedimiento(): void
    {
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'estado' => EstadoCirugia::Realizada->value,
        ]);

        $this->actingAs($this->admin)
            ->from("/cirugias/{$cirugia->id}")
            ->post("/cirugias/{$cirugia->id}/facturacion", [
                'valor_facturado' => 800000,
                'valor_glosado' => 50000,
                'valor_recaudado' => 700000,
                'fecha_facturacion' => '2026-07-16',
            ])
            ->assertRedirect("/cirugias/{$cirugia->id}");

        $this->assertDatabaseHas('facturaciones', [
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'valor_facturado' => 800000,
        ]);
    }

    public function test_lo_glosado_no_puede_superar_lo_facturado(): void
    {
        $cirugia = Cirugia::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->actingAs($this->admin)
            ->post("/cirugias/{$cirugia->id}/facturacion", [
                'valor_facturado' => 100000,
                'valor_glosado' => 150000,
                'valor_recaudado' => 0,
            ])
            ->assertSessionHasErrors('valor_glosado');

        $this->assertDatabaseCount('facturaciones', 0);
    }

    public function test_registrar_la_facturacion_dos_veces_la_actualiza(): void
    {
        $cirugia = Cirugia::factory()->create(['hospital_id' => $this->hospital->id]);

        $enviar = fn (int $valor) => $this->actingAs($this->admin)
            ->post("/cirugias/{$cirugia->id}/facturacion", [
                'valor_facturado' => $valor,
                'valor_glosado' => 0,
                'valor_recaudado' => 0,
            ]);

        $enviar(500000)->assertRedirect();
        $enviar(650000)->assertRedirect();

        $this->assertDatabaseCount('facturaciones', 1);
        $this->assertDatabaseHas('facturaciones', [
            'cirugia_id' => $cirugia->id,
            'valor_facturado' => 650000,
        ]);
    }

    public function test_una_complicacion_marcada_exige_su_descripcion(): void
    {
        $cirugia = Cirugia::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->actingAs($this->admin)
            ->post("/cirugias/{$cirugia->id}/resultado-clinico", [
                'complicacion_intraoperatoria' => true,
                'complicacion_postoperatoria' => false,
                'dias_estancia' => 2,
                'reingreso_30_dias' => false,
                'mortalidad' => false,
            ])
            ->assertSessionHasErrors('descripcion_complicacion_intra');
    }

    public function test_se_registra_el_resultado_clinico(): void
    {
        $cirugia = Cirugia::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->actingAs($this->admin)
            ->post("/cirugias/{$cirugia->id}/resultado-clinico", [
                'complicacion_intraoperatoria' => false,
                'complicacion_postoperatoria' => true,
                'descripcion_complicacion_post' => 'Infección del sitio operatorio.',
                'dias_estancia' => 4,
                'reingreso_30_dias' => true,
                'mortalidad' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('resultados_clinicos', [
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'dias_estancia' => 4,
            'reingreso_30_dias' => true,
        ]);
    }

    public function test_el_digitador_no_entra_al_padron_ni_a_la_facturacion(): void
    {
        $digitador = User::factory()->create([
            'hospital_id' => $this->hospital->id,
            'role' => 'digitador',
            'activo' => true,
        ]);
        $cirugia = Cirugia::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->actingAs($digitador)->get('/pacientes')->assertForbidden();
        $this->actingAs($digitador)
            ->post("/cirugias/{$cirugia->id}/facturacion", [
                'valor_facturado' => 1000,
                'valor_glosado' => 0,
                'valor_recaudado' => 0,
            ])
            ->assertForbidden();
    }
}
