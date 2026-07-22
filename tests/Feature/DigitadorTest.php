<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
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

class DigitadorTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected User $admin;

    protected User $digitador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create([
            'horas_dia' => 12,
            'dias_mes' => 26,
            'factor_indirecto' => 0,
        ]);
        $this->admin = User::factory()->create(['hospital_id' => $this->hospital->id]);
        $this->digitador = User::factory()->digitador()->create(['hospital_id' => $this->hospital->id]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_el_digitador_puede_ver_y_registrar_procedimientos(): void
    {
        $this->actingAs($this->digitador)->get('/cirugias')->assertOk();
        $this->actingAs($this->digitador)->get('/cirugias/create')->assertOk();
    }

    public function test_el_digitador_solo_ve_sus_propios_registros(): void
    {
        HospitalContext::set($this->hospital->id);

        $suyo = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'registrado_por' => $this->digitador->id,
        ]);
        // De otro digitador y del administrador: no debe verlos.
        $otroDigitador = User::factory()->digitador()->create(['hospital_id' => $this->hospital->id]);
        Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'registrado_por' => $otroDigitador->id,
        ]);
        Cirugia::factory()->count(2)->create(['hospital_id' => $this->hospital->id]);

        HospitalContext::clear();

        $this->actingAs($this->digitador)
            ->get('/cirugias')
            ->assertInertia(fn (Assert $page) => $page
                ->component('cirugias/inicio')
                // Nunca recibe el listado paginado del hospital ni los costos.
                ->missing('cirugias')
                ->has('mios', 1)
                ->where('mios.0.id', $suyo->id));
    }

    public function test_el_digitador_corrige_y_cierra_lo_suyo_pero_no_lo_ajeno(): void
    {
        HospitalContext::set($this->hospital->id);

        $suyo = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'registrado_por' => $this->digitador->id,
            'estado' => 'en_proceso',
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15 08:00:00',
            'hora_fin' => null,
        ]);
        $ajeno = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'estado' => 'en_proceso',
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15 08:00:00',
            'hora_fin' => null,
        ]);

        HospitalContext::clear();

        $this->actingAs($this->digitador)->get("/cirugias/{$suyo->id}/edit")->assertOk();
        $this->actingAs($this->digitador)
            ->patch("/cirugias/{$suyo->id}/cerrar", ['hora_fin' => '2026-07-15T09:30'])
            ->assertRedirect();
        $this->assertSame('en_recuperacion', $suyo->refresh()->estado);

        // El digitador también cierra el ciclo cuando el paciente egresa.
        $this->actingAs($this->digitador)
            ->patch("/cirugias/{$suyo->id}/cerrar", ['hora_salida_recuperacion' => '2026-07-15T11:00'])
            ->assertRedirect();
        $this->assertSame('realizada', $suyo->refresh()->estado);

        // Lo capturado por otra persona queda fuera de su alcance.
        $this->actingAs($this->digitador)->get("/cirugias/{$ajeno->id}/edit")->assertForbidden();
        $this->actingAs($this->digitador)
            ->patch("/cirugias/{$ajeno->id}/cerrar", ['hora_fin' => '2026-07-15T09:30'])
            ->assertForbidden();
        $this->assertSame('en_proceso', $ajeno->refresh()->estado);
    }

    public function test_el_digitador_nunca_elimina_ni_ve_el_detalle_costeado(): void
    {
        HospitalContext::set($this->hospital->id);
        $suyo = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'registrado_por' => $this->digitador->id,
        ]);
        HospitalContext::clear();

        // Ni siquiera sobre lo propio: eliminar y analizar son del admin.
        $this->actingAs($this->digitador)->delete("/cirugias/{$suyo->id}")->assertForbidden();
        $this->actingAs($this->digitador)->get("/cirugias/{$suyo->id}")->assertForbidden();
    }

    public function test_el_registro_guarda_quien_lo_capturo(): void
    {
        HospitalContext::set($this->hospital->id);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
        HospitalContext::clear();

        $this->actingAs($this->digitador)->post('/cirugias', [
            'paciente_id' => $paciente->id,
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15 08:00:00',
            'tipo' => 'programada',
            'procedimientos' => [['id' => $procedimiento->id, 'es_principal' => true]],
        ])->assertRedirect('/cirugias');

        $this->assertDatabaseHas('cirugias', [
            'paciente_id' => $paciente->id,
            'registrado_por' => $this->digitador->id,
        ]);
    }

    public function test_el_digitador_no_accede_al_resto_del_aplicativo(): void
    {
        $rutas = ['/dashboard', '/parametros', '/costeo', '/historial', '/digitadores'];

        foreach ($rutas as $ruta) {
            $this->actingAs($this->digitador)->get($ruta)->assertForbidden();
        }
    }

    public function test_el_digitador_no_ve_el_detalle_ni_costea(): void
    {
        HospitalContext::set($this->hospital->id);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => null,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->digitador)->get("/cirugias/{$cirugia->id}")->assertForbidden();
        $this->actingAs($this->digitador)->post("/cirugias/{$cirugia->id}/calcular-costo")->assertForbidden();
    }

    public function test_el_registro_del_digitador_calcula_el_costo_automaticamente_y_queda_en_el_historial(): void
    {
        HospitalContext::set($this->hospital->id);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $sala = SalaOperatoria::factory()->create([
            'hospital_id' => $this->hospital->id,
            'costo_hora' => 40_000,
            'activa' => true,
        ]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
        $cirujano = RecursoHumano::factory()->create([
            'hospital_id' => $this->hospital->id,
            'rol' => 'cirujano',
            'salario_mensual' => 10_000_000,
            'prestaciones_mensuales' => 4_600_000,
            'costos_indirectos_mensuales' => 1_000_000,
        ]);
        $insumo = Insumo::factory()->create([
            'hospital_id' => $this->hospital->id,
            'costo_unitario' => 10_000,
            'activo' => true,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->digitador)->post('/cirugias', [
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $sala->id,
            'fecha' => '2026-07-01',
            'hora_inicio' => '2026-07-01 08:00:00',
            'hora_fin' => '2026-07-01 10:00:00',
            'hora_salida_recuperacion' => '2026-07-01 12:00:00',
            'tipo' => 'programada',
            'estado' => 'realizada',
            'procedimientos' => [['id' => $procedimiento->id, 'es_principal' => true]],
            'equipo' => [
                ['recurso_humano_id' => $cirujano->id, 'rol' => 'cirujano', 'fase' => 'quirurgica', 'minutos_participacion' => 90],
            ],
            'consumos' => [
                ['insumo_id' => $insumo->id, 'fase' => 'quirurgica', 'cantidad' => 3],
            ],
        ])->assertRedirect('/cirugias');

        $cirugia = Cirugia::withoutGlobalScopes()->latest('id')->firstOrFail();

        // Costeo automático: el administrador lo verá sin acción manual.
        $this->assertNotNull($cirugia->fresh()->costo);

        // La acción del digitador queda auditada.
        $this->assertDatabaseHas('registros_actividad', [
            'user_id' => $this->digitador->id,
            'hospital_id' => $this->hospital->id,
            'auditable_type' => $cirugia->getMorphClass(),
            'auditable_id' => $cirugia->id,
        ]);
    }

    public function test_el_administrador_crea_un_digitador_para_su_hospital(): void
    {
        $this->actingAs($this->admin)->post('/digitadores', [
            'name' => 'Nuevo Digitador',
            'email' => 'nuevo.digitador@hospital.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ])->assertRedirect('/digitadores');

        $creado = User::where('email', 'nuevo.digitador@hospital.test')->firstOrFail();
        $this->assertSame(RolUsuario::Digitador, $creado->role);
        $this->assertSame($this->hospital->id, $creado->hospital_id);
        $this->assertTrue($creado->activo);

        $this->assertDatabaseHas('registros_actividad', [
            'user_id' => $this->admin->id,
            'accion' => 'creó digitador',
            'auditable_id' => $creado->id,
        ]);
    }

    public function test_el_listado_de_digitadores_solo_muestra_los_del_hospital_propio(): void
    {
        $otroHospital = Hospital::factory()->create();
        User::factory()->digitador()->create([
            'hospital_id' => $otroHospital->id,
            'name' => 'Digitador Ajeno',
        ]);

        $this->actingAs($this->admin)
            ->get('/digitadores')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('digitadores/index')
                ->has('digitadores', 1)
                ->where('digitadores.0.id', $this->digitador->id));
    }

    public function test_el_digitador_no_puede_gestionar_digitadores(): void
    {
        $this->actingAs($this->digitador)->post('/digitadores', [
            'name' => 'Otro',
            'email' => 'otro@hospital.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ])->assertForbidden();
    }

    public function test_el_administrador_puede_desactivar_y_un_usuario_inactivo_es_bloqueado(): void
    {
        $this->actingAs($this->admin)
            ->patch("/digitadores/{$this->digitador->id}/activo")
            ->assertRedirect('/digitadores');

        $this->assertFalse($this->digitador->fresh()->activo);

        // El digitador desactivado ya no puede operar.
        $this->actingAs($this->digitador->fresh())->get('/cirugias')->assertForbidden();
    }

    public function test_la_pagina_de_inicio_redirige_al_digitador_a_su_modulo(): void
    {
        $this->actingAs($this->digitador)->get('/')->assertRedirect('/cirugias');
        $this->actingAs($this->admin)->get('/')->assertRedirect('/parametros');
    }
}
