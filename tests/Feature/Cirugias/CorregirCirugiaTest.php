<?php

namespace Tests\Feature\Cirugias;

use App\Enums\EstadoCirugia;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Models\Scopes\HospitalScope;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorregirCirugiaTest extends TestCase
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
    protected function datosBase(array $sobrescribir = []): array
    {
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $sala = SalaOperatoria::factory()->create(['hospital_id' => $this->hospital->id]);

        return [
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $sala->id,
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15T08:00',
            'hora_fin' => null,
            'tipo' => 'programada',
            'estado' => EstadoCirugia::EnProceso->value,
            'procedimientos' => [['id' => $procedimiento->id, 'es_principal' => true]],
            ...$sobrescribir,
        ];
    }

    /**
     * El cierre sigue el ciclo real en dos pasos. Costear al salir de sala
     * daría por terminado un procedimiento cuyo paciente sigue internado, así
     * que el costo solo se calcula cuando se registra el egreso.
     */
    public function test_un_procedimiento_abierto_se_cierra_en_dos_pasos_y_se_costea(): void
    {
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'estado' => EstadoCirugia::EnProceso->value,
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15 08:00:00',
            'hora_fin' => null,
            'hora_salida_recuperacion' => null,
        ]);

        // Paso 1: sale de sala. Queda en recuperación, todavía sin costear.
        $this->actingAs($this->admin)
            ->patch("/cirugias/{$cirugia->id}/cerrar", ['hora_fin' => '2026-07-15T09:30'])
            ->assertRedirect();

        $cirugia->refresh();

        $this->assertSame(EstadoCirugia::EnRecuperacion->value, $cirugia->estado);
        $this->assertSame(90, $cirugia->duracionMinutos());
        $this->assertNull($cirugia->costo);

        // Paso 2: egresa de recuperación. Ahora sí se cierra y se costea.
        $this->actingAs($this->admin)
            ->patch("/cirugias/{$cirugia->id}/cerrar", ['hora_salida_recuperacion' => '2026-07-15T11:00'])
            ->assertRedirect();

        $cirugia->refresh();

        $this->assertSame(EstadoCirugia::Realizada->value, $cirugia->estado);
        $this->assertSame(90, $cirugia->minutosRecuperacion());
        $this->assertNotNull($cirugia->costo);
    }

    public function test_el_cierre_rechaza_una_hora_fin_anterior_al_inicio(): void
    {
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'estado' => EstadoCirugia::EnProceso->value,
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15 08:00:00',
            'hora_fin' => null,
        ]);

        $this->actingAs($this->admin)
            ->patch("/cirugias/{$cirugia->id}/cerrar", ['hora_fin' => '2026-07-15T07:00'])
            ->assertSessionHasErrors('hora_fin');

        $this->assertSame(EstadoCirugia::EnProceso->value, $cirugia->refresh()->estado);
    }

    public function test_corregir_conserva_la_tarifa_congelada_de_lo_que_ya_estaba(): void
    {
        $insumo = Insumo::factory()->create([
            'hospital_id' => $this->hospital->id,
            'costo_unitario' => 1000,
            'activo' => true,
        ]);

        $datos = $this->datosBase([
            'estado' => EstadoCirugia::Realizada->value,
            'hora_fin' => '2026-07-15T09:00',
            'hora_salida_recuperacion' => '2026-07-15T11:00',
            'consumos' => [['insumo_id' => $insumo->id, 'fase' => 'quirurgica', 'cantidad' => 2]],
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $cirugia = Cirugia::query()->latest('id')->firstOrFail();

        // El precio del insumo sube DESPUÉS del registro.
        $insumo->update(['costo_unitario' => 5000]);

        // La corrección solo cambia la cantidad.
        $datos['consumos'] = [['insumo_id' => $insumo->id, 'fase' => 'quirurgica', 'cantidad' => 3]];

        $this->actingAs($this->admin)
            ->put("/cirugias/{$cirugia->id}", $datos)
            ->assertRedirect();

        $consumo = $cirugia->refresh()->consumos->firstOrFail();

        $this->assertEquals(1000, $consumo->costo_unitario_registrado);
        $this->assertEquals(3000, $consumo->costo_total);
    }

    public function test_al_dejar_de_estar_realizada_se_borra_el_costo(): void
    {
        $datos = $this->datosBase([
            'estado' => EstadoCirugia::Realizada->value,
            'hora_fin' => '2026-07-15T09:00',
            'hora_salida_recuperacion' => '2026-07-15T11:00',
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $cirugia = Cirugia::query()->latest('id')->firstOrFail();
        $this->assertNotNull($cirugia->costo);

        $this->actingAs($this->admin)
            ->put("/cirugias/{$cirugia->id}", [...$datos, 'estado' => EstadoCirugia::Cancelada->value])
            ->assertRedirect();

        $this->assertSame(
            0,
            CostoCirugia::withoutGlobalScope(HospitalScope::class)
                ->where('cirugia_id', $cirugia->id)
                ->count(),
        );
    }

    public function test_corregir_reemplaza_el_equipo_quirurgico(): void
    {
        $uno = RecursoHumano::factory()->create(['hospital_id' => $this->hospital->id, 'activo' => true]);
        $dos = RecursoHumano::factory()->create(['hospital_id' => $this->hospital->id, 'activo' => true]);

        $datos = $this->datosBase([
            'equipo' => [[
                'recurso_humano_id' => $uno->id,
                'rol' => 'cirujano',
                'fase' => 'quirurgica',
                'minutos_participacion' => 60,
            ]],
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $cirugia = Cirugia::query()->latest('id')->firstOrFail();

        $datos['equipo'] = [[
            'recurso_humano_id' => $dos->id,
            'rol' => 'anestesiologo',
            'fase' => 'quirurgica',
            'minutos_participacion' => 45,
        ]];

        $this->actingAs($this->admin)
            ->put("/cirugias/{$cirugia->id}", $datos)
            ->assertRedirect();

        $equipo = $cirugia->refresh()->equipoQuirurgico;

        $this->assertCount(1, $equipo);
        $this->assertSame($dos->id, $equipo->first()->recurso_humano_id);
        $this->assertSame(45, $equipo->first()->minutos_participacion);
    }

    public function test_el_administrador_corrige_cualquier_procedimiento_de_su_hospital(): void
    {
        $digitador = User::factory()->create([
            'hospital_id' => $this->hospital->id,
            'role' => 'digitador',
            'activo' => true,
        ]);

        // Capturado por el digitador: el administrador igual lo supervisa.
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'registrado_por' => $digitador->id,
            'estado' => EstadoCirugia::EnProceso->value,
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15 08:00:00',
            'hora_fin' => null,
        ]);

        $this->actingAs($this->admin)->get("/cirugias/{$cirugia->id}/edit")->assertOk();

        // El cierre son dos pasos: salir de sala y, al egreso, cerrar el ciclo.
        $this->actingAs($this->admin)
            ->patch("/cirugias/{$cirugia->id}/cerrar", ['hora_fin' => '2026-07-15T09:00'])
            ->assertRedirect();
        $this->actingAs($this->admin)
            ->patch("/cirugias/{$cirugia->id}/cerrar", ['hora_salida_recuperacion' => '2026-07-15T11:00'])
            ->assertRedirect();

        $this->assertSame(EstadoCirugia::Realizada->value, $cirugia->refresh()->estado);

        // Eliminar sigue siendo exclusivo del administrador.
        $this->actingAs($digitador)->delete("/cirugias/{$cirugia->id}")->assertForbidden();
        $this->assertDatabaseHas('cirugias', ['id' => $cirugia->id]);
    }

    public function test_eliminar_borra_el_procedimiento_y_su_costo(): void
    {
        $datos = $this->datosBase([
            'estado' => EstadoCirugia::Realizada->value,
            'hora_fin' => '2026-07-15T09:00',
            'hora_salida_recuperacion' => '2026-07-15T11:00',
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $cirugia = Cirugia::query()->latest('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete("/cirugias/{$cirugia->id}")
            ->assertRedirect(route('cirugias.index'));

        $this->assertDatabaseMissing('cirugias', ['id' => $cirugia->id]);
        $this->assertDatabaseMissing('costos_cirugia', ['cirugia_id' => $cirugia->id]);
    }

    public function test_los_minutos_del_equipo_se_derivan_de_la_entrada_y_la_salida(): void
    {
        $recurso = RecursoHumano::factory()->create([
            'hospital_id' => $this->hospital->id,
            'activo' => true,
        ]);

        $datos = $this->datosBase([
            'equipo' => [[
                'recurso_humano_id' => $recurso->id,
                'rol' => 'cirujano',
                'fase' => 'quirurgica',
                'hora_inicio' => '2026-07-15T08:15',
                'hora_fin' => '2026-07-15T09:45',
                // Se manda un valor incoherente a propósito: manda el rango.
                'minutos_participacion' => 5,
            ]],
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $miembro = Cirugia::query()->latest('id')->firstOrFail()->equipoQuirurgico->firstOrFail();

        $this->assertSame(90, $miembro->minutos_participacion);
        $this->assertSame('2026-07-15 08:15', $miembro->hora_inicio->format('Y-m-d H:i'));
        $this->assertSame('2026-07-15 09:45', $miembro->hora_fin->format('Y-m-d H:i'));
    }

    public function test_se_pueden_seguir_capturando_los_minutos_sin_horas(): void
    {
        $recurso = RecursoHumano::factory()->create([
            'hospital_id' => $this->hospital->id,
            'activo' => true,
        ]);

        $datos = $this->datosBase([
            'equipo' => [[
                'recurso_humano_id' => $recurso->id,
                'rol' => 'cirujano',
                'fase' => 'quirurgica',
                'minutos_participacion' => 40,
            ]],
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $miembro = Cirugia::query()->latest('id')->firstOrFail()->equipoQuirurgico->firstOrFail();

        $this->assertSame(40, $miembro->minutos_participacion);
        $this->assertNull($miembro->hora_inicio);
    }

    public function test_una_salida_anterior_a_la_entrada_se_rechaza(): void
    {
        $recurso = RecursoHumano::factory()->create([
            'hospital_id' => $this->hospital->id,
            'activo' => true,
        ]);

        $datos = $this->datosBase([
            'equipo' => [[
                'recurso_humano_id' => $recurso->id,
                'rol' => 'cirujano',
                'fase' => 'quirurgica',
                'hora_inicio' => '2026-07-15T09:00',
                'hora_fin' => '2026-07-15T08:00',
                'minutos_participacion' => 60,
            ]],
        ]);

        $this->actingAs($this->admin)
            ->post('/cirugias', $datos)
            ->assertSessionHasErrors('equipo.0.hora_fin');
    }

    public function test_el_formulario_expone_el_documento_para_buscar_al_paciente(): void
    {
        Paciente::factory()->create([
            'hospital_id' => $this->hospital->id,
            'documento' => '1122334455',
            'nombres' => 'Ana María',
        ]);

        $this->actingAs($this->admin)
            ->get('/cirugias/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pacientes.0.documento', '1122334455')
                ->where('pacientes.0.nombres', 'Ana María'));
    }

    public function test_no_se_puede_corregir_una_cirugia_de_otro_hospital(): void
    {
        $otro = Hospital::factory()->create();
        $ajena = Cirugia::factory()->create(['hospital_id' => $otro->id]);

        $this->actingAs($this->admin)
            ->get("/cirugias/{$ajena->id}/edit")
            ->assertNotFound();
    }
}
