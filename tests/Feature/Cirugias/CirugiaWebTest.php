<?php

namespace Tests\Feature\Cirugias;

use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CirugiaWebTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospitalA;

    protected Hospital $hospitalB;

    protected User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospitalA = Hospital::factory()->create([
            'horas_dia' => 12,
            'dias_mes' => 26,
            'factor_indirecto' => 0,
        ]);
        $this->hospitalB = Hospital::factory()->create();
        $this->adminA = User::factory()->create(['hospital_id' => $this->hospitalA->id]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_el_formulario_solo_ofrece_catalogos_del_hospital_propio(): void
    {
        Paciente::factory()->create(['hospital_id' => $this->hospitalA->id]);
        Paciente::factory()->create(['hospital_id' => $this->hospitalB->id]);
        SalaOperatoria::factory()->create(['hospital_id' => $this->hospitalA->id, 'activa' => true]);
        SalaOperatoria::factory()->create(['hospital_id' => $this->hospitalB->id, 'activa' => true]);
        Insumo::factory()->create(['hospital_id' => $this->hospitalA->id, 'activo' => true]);
        Insumo::factory()->create(['hospital_id' => $this->hospitalB->id, 'activo' => true]);

        $this->actingAs($this->adminA)
            ->get('/cirugias/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cirugias/create')
                ->has('pacientes', 1)
                ->has('salas', 1)
                ->has('insumos', 1));
    }

    public function test_registra_una_cirugia_completa_y_calcula_su_costo_desde_parametros_capa_1(): void
    {
        HospitalContext::set($this->hospitalA->id);

        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospitalA->id]);
        $sala = SalaOperatoria::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'costo_hora' => 40_000,
            'activa' => true,
        ]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospitalA->id]);
        // 15.600.000/mes ÷ 18.720 min = $833,33/min → $50.000/hora
        $cirujano = RecursoHumano::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'rol' => 'cirujano',
            'salario_mensual' => 10_000_000,
            'prestaciones_mensuales' => 4_600_000,
            'costos_indirectos_mensuales' => 1_000_000,
        ]);
        $insumo = Insumo::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'costo_unitario' => 10_000,
            'activo' => true,
        ]);

        HospitalContext::clear();

        $respuesta = $this->actingAs($this->adminA)->post('/cirugias', [
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $sala->id,
            'fecha' => '2026-07-01',
            'hora_inicio' => '2026-07-01 08:00:00',
            'hora_fin' => '2026-07-01 10:00:00', // 120 min → sala $80.000
            'hora_salida_recuperacion' => '2026-07-01 12:00:00',
            'tipo' => 'programada',
            'estado' => 'realizada',
            'procedimientos' => [['id' => $procedimiento->id, 'es_principal' => true]],
            'equipo' => [
                ['recurso_humano_id' => $cirujano->id, 'rol' => 'cirujano', 'fase' => 'quirurgica', 'minutos_participacion' => 90], // $75.000
            ],
            'consumos' => [
                ['insumo_id' => $insumo->id, 'fase' => 'quirurgica', 'cantidad' => 3], // $30.000
            ],
        ]);

        $cirugia = Cirugia::withoutGlobalScopes()->latest('id')->firstOrFail();
        $respuesta->assertRedirect("/cirugias/{$cirugia->id}");

        $this->assertSame($this->hospitalA->id, $cirugia->hospital_id);
        $this->assertSame(1, $cirugia->procedimientos()->count());
        $this->assertSame(1, $cirugia->equipoQuirurgico()->count());
        $this->assertSame(1, $cirugia->consumos()->count());

        // Calcular el costo por la ruta web
        $this->actingAs($this->adminA)
            ->post("/cirugias/{$cirugia->id}/calcular-costo")
            ->assertRedirect("/cirugias/{$cirugia->id}");

        $costo = $cirugia->fresh()->costo;
        $this->assertNotNull($costo);
        // 75.000 (cirujano) + 80.000 (sala) + 30.000 (insumos) = 185.000
        $this->assertEqualsWithDelta(185_000.0, (float) $costo->costo_total, 0.01);

        // El detalle del show incluye el desglose
        $this->actingAs($this->adminA)
            ->get("/cirugias/{$cirugia->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cirugias/show')
                ->where('costo.costo_total', fn ($valor) => abs((float) $valor - 185_000.0) < 0.01)
                ->has('costo.detalle.recurso_humano', 1)
                ->has('costo.detalle.insumos', 1));
    }

    public function test_recalcular_no_duplica_el_costo(): void
    {
        HospitalContext::set($this->hospitalA->id);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospitalA->id]);
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => null,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)->post("/cirugias/{$cirugia->id}/calcular-costo")->assertRedirect();
        $this->actingAs($this->adminA)->post("/cirugias/{$cirugia->id}/calcular-costo")->assertRedirect();

        $this->assertSame(1, $cirugia->costo()->count());
    }

    public function test_no_puede_ver_ni_costear_una_cirugia_de_otro_hospital(): void
    {
        HospitalContext::set($this->hospitalB->id);
        $pacienteB = Paciente::factory()->create(['hospital_id' => $this->hospitalB->id]);
        $cirugiaB = Cirugia::factory()->create([
            'hospital_id' => $this->hospitalB->id,
            'paciente_id' => $pacienteB->id,
            'sala_operatoria_id' => null,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)->get("/cirugias/{$cirugiaB->id}")->assertNotFound();
        $this->actingAs($this->adminA)->post("/cirugias/{$cirugiaB->id}/calcular-costo")->assertNotFound();
    }

    public function test_el_listado_solo_muestra_cirugias_del_hospital_propio(): void
    {
        HospitalContext::set($this->hospitalA->id);
        $pacienteA = Paciente::factory()->create(['hospital_id' => $this->hospitalA->id]);
        Cirugia::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'paciente_id' => $pacienteA->id,
            'sala_operatoria_id' => null,
        ]);
        HospitalContext::set($this->hospitalB->id);
        $pacienteB = Paciente::factory()->create(['hospital_id' => $this->hospitalB->id]);
        Cirugia::factory()->create([
            'hospital_id' => $this->hospitalB->id,
            'paciente_id' => $pacienteB->id,
            'sala_operatoria_id' => null,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)
            ->get('/cirugias')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cirugias/index')
                ->has('cirugias.data', 1));
    }

    public function test_alta_rapida_de_paciente_desde_el_formulario(): void
    {
        $this->actingAs($this->adminA)->post('/cirugias/pacientes', [
            'tipo_documento' => 'CC',
            'documento' => '1122334455',
            'nombres' => 'Laura',
            'apellidos' => 'Pushaina',
            'regimen' => 'subsidiado',
            'zona' => 'rural',
        ])->assertRedirect();

        $paciente = Paciente::withoutGlobalScopes()->where('nombres', 'Laura')->firstOrFail();
        $this->assertSame($this->hospitalA->id, $paciente->hospital_id);
    }
}
