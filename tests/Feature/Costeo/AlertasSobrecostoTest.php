<?php

namespace Tests\Feature\Costeo;

use App\Enums\CausaSobrecosto;
use App\Enums\EstadoAlerta;
use App\Models\AlertaSobrecosto;
use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlertasSobrecostoTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospitalA;

    protected Hospital $hospitalB;

    protected User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospitalA = Hospital::factory()->create();
        $this->hospitalB = Hospital::factory()->create();
        $this->adminA = User::factory()->create(['hospital_id' => $this->hospitalA->id]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_la_bandeja_lista_las_alertas_pendientes_del_hospital(): void
    {
        $alerta = $this->crearAlerta($this->hospitalA, ['exceso' => 2_000_000]);
        $this->crearAlerta($this->hospitalB);

        $this->actingAs($this->adminA)
            ->get('/costeo/alertas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/alertas')
                ->has('alertas.data', 1)
                ->where('alertas.data.0.id', $alerta->id)
                ->where('resumen.pendientes', 1));
    }

    public function test_las_alertas_se_ordenan_por_exceso_para_priorizar_la_revision(): void
    {
        $pequena = $this->crearAlerta($this->hospitalA, ['exceso' => 100_000]);
        $grande = $this->crearAlerta($this->hospitalA, ['exceso' => 9_000_000]);

        $this->actingAs($this->adminA)
            ->get('/costeo/alertas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alertas.data.0.id', $grande->id)
                ->where('alertas.data.1.id', $pequena->id));
    }

    public function test_revisar_exige_una_causa(): void
    {
        $alerta = $this->crearAlerta($this->hospitalA);

        // Cerrar el caso sin causa vaciaría la bandeja perdiendo justo el dato
        // por el que existe.
        $this->actingAs($this->adminA)
            ->patch("/costeo/alertas/{$alerta->id}", ['estado' => 'revisada'])
            ->assertSessionHasErrors('causa');

        $this->assertSame(EstadoAlerta::Pendiente, $alerta->fresh()->estado);
    }

    public function test_revisar_registra_la_causa_y_quien_la_atribuyo(): void
    {
        $alerta = $this->crearAlerta($this->hospitalA);

        $this->actingAs($this->adminA)
            ->patch("/costeo/alertas/{$alerta->id}", [
                'estado' => 'revisada',
                'causa' => CausaSobrecosto::ConsumoExcesivoInsumos->value,
                'causa_detalle' => 'Se abrieron dos sets de laparoscopia.',
            ])
            ->assertRedirect();

        $alerta->refresh();

        $this->assertSame(EstadoAlerta::Revisada, $alerta->estado);
        $this->assertSame(CausaSobrecosto::ConsumoExcesivoInsumos, $alerta->causa);
        $this->assertSame($this->adminA->id, $alerta->revisado_por);
        $this->assertNotNull($alerta->revisado_en);

        // La atribución de causa es el único acto humano del ciclo: queda en
        // la bitácora aunque la creación automática de la alerta no lo esté.
        $this->assertDatabaseHas('registros_actividad', [
            'user_id' => $this->adminA->id,
            'accion' => 'revisó',
            'auditable_id' => $alerta->id,
        ]);
    }

    public function test_la_causa_otra_exige_explicacion(): void
    {
        $alerta = $this->crearAlerta($this->hospitalA);

        $this->actingAs($this->adminA)
            ->patch("/costeo/alertas/{$alerta->id}", [
                'estado' => 'revisada',
                'causa' => CausaSobrecosto::Otra->value,
            ])
            ->assertSessionHasErrors('causa_detalle');
    }

    public function test_solo_el_sobrecosto_evitable_suma_a_la_perdida_recuperable(): void
    {
        $evitable = $this->crearAlerta($this->hospitalA, ['exceso' => 3_000_000]);
        $noEvitable = $this->crearAlerta($this->hospitalA, ['exceso' => 5_000_000]);

        $this->actingAs($this->adminA)->patch("/costeo/alertas/{$evitable->id}", [
            'estado' => 'revisada',
            'causa' => CausaSobrecosto::ConsumoExcesivoInsumos->value,
        ]);

        // Una complicación clínica no es una falla de gestión: cuenta en el
        // exceso total pero no en lo que el hospital podía haber ahorrado.
        $this->actingAs($this->adminA)->patch("/costeo/alertas/{$noEvitable->id}", [
            'estado' => 'revisada',
            'causa' => CausaSobrecosto::ComplicacionClinica->value,
        ]);

        $this->actingAs($this->adminA)
            ->get('/costeo/alertas?estado=todas')
            ->assertInertia(fn (Assert $page) => $page
                ->where('resumen.exceso_total', 8_000_000)
                ->where('resumen.exceso_evitable', 3_000_000)
                ->where('resumen.pendientes', 0)
                ->where('resumen.revisadas', 2));
    }

    public function test_descartar_no_exige_causa_y_no_suma_a_la_perdida(): void
    {
        $alerta = $this->crearAlerta($this->hospitalA, ['exceso' => 4_000_000]);

        $this->actingAs($this->adminA)
            ->patch("/costeo/alertas/{$alerta->id}", ['estado' => 'descartada'])
            ->assertRedirect();

        $alerta->refresh();

        $this->assertSame(EstadoAlerta::Descartada, $alerta->estado);
        $this->assertNull($alerta->causa);

        $this->actingAs($this->adminA)
            ->get('/costeo/alertas?estado=todas')
            ->assertInertia(fn (Assert $page) => $page->where('resumen.exceso_evitable', 0));
    }

    public function test_no_se_puede_revisar_una_alerta_de_otro_hospital(): void
    {
        $ajena = $this->crearAlerta($this->hospitalB);

        $this->actingAs($this->adminA)
            ->patch("/costeo/alertas/{$ajena->id}", [
                'estado' => 'revisada',
                'causa' => CausaSobrecosto::ConsumoExcesivoInsumos->value,
            ])
            ->assertNotFound();
    }

    public function test_el_digitador_no_entra_a_la_bandeja(): void
    {
        $digitador = User::factory()->digitador()
            ->create(['hospital_id' => $this->hospitalA->id]);

        $this->actingAs($digitador)->get('/costeo/alertas')->assertForbidden();
    }

    public function test_el_contador_de_pendientes_viaja_en_las_props_compartidas(): void
    {
        // Es lo que hace visible la alerta desde cualquier pantalla: sin él
        // solo la vería quien ya hubiera decidido ir a buscarla.
        $this->crearAlerta($this->hospitalA);
        $this->crearAlerta($this->hospitalA);
        $this->crearAlerta($this->hospitalB);

        $this->actingAs($this->adminA)
            ->get('/parametros')
            ->assertInertia(fn (Assert $page) => $page->where('alertasPendientes', 2));
    }

    /** @param  array<string, mixed>  $atributos */
    private function crearAlerta(Hospital $hospital, array $atributos = []): AlertaSobrecosto
    {
        HospitalContext::set($hospital->id);

        $procedimiento = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $hospital->id]);

        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $hospital->id])->id,
            'sala_operatoria_id' => null,
        ]);

        $alerta = AlertaSobrecosto::factory()->create([
            'hospital_id' => $hospital->id,
            'cirugia_id' => $cirugia->id,
            'procedimiento_quirurgico_id' => $procedimiento->id,
            ...$atributos,
        ]);

        HospitalContext::clear();

        return $alerta;
    }
}
