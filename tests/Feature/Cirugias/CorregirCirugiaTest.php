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

    public function test_un_procedimiento_abierto_se_cierra_y_se_costea(): void
    {
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'estado' => EstadoCirugia::EnProceso->value,
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15 08:00:00',
            'hora_fin' => null,
        ]);

        $this->actingAs($this->admin)
            ->patch("/cirugias/{$cirugia->id}/cerrar", ['hora_fin' => '2026-07-15T09:30'])
            ->assertRedirect();

        $cirugia->refresh();

        $this->assertSame(EstadoCirugia::Realizada->value, $cirugia->estado);
        $this->assertSame(90, $cirugia->duracionMinutos());
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
            'consumos' => [['insumo_id' => $insumo->id, 'cantidad' => 2]],
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $cirugia = Cirugia::query()->latest('id')->firstOrFail();

        // El precio del insumo sube DESPUÉS del registro.
        $insumo->update(['costo_unitario' => 5000]);

        // La corrección solo cambia la cantidad.
        $datos['consumos'] = [['insumo_id' => $insumo->id, 'cantidad' => 3]];

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
                'minutos_participacion' => 60,
            ]],
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $cirugia = Cirugia::query()->latest('id')->firstOrFail();

        $datos['equipo'] = [[
            'recurso_humano_id' => $dos->id,
            'rol' => 'anestesiologo',
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

    public function test_el_digitador_puede_corregir_y_cerrar_pero_no_eliminar(): void
    {
        $digitador = User::factory()->create([
            'hospital_id' => $this->hospital->id,
            'role' => 'digitador',
            'activo' => true,
        ]);

        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'estado' => EstadoCirugia::EnProceso->value,
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15 08:00:00',
            'hora_fin' => null,
        ]);

        $this->actingAs($digitador)->get("/cirugias/{$cirugia->id}/edit")->assertOk();

        $this->actingAs($digitador)
            ->patch("/cirugias/{$cirugia->id}/cerrar", ['hora_fin' => '2026-07-15T09:00'])
            ->assertRedirect();

        $this->actingAs($digitador)->delete("/cirugias/{$cirugia->id}")->assertForbidden();

        $this->assertDatabaseHas('cirugias', ['id' => $cirugia->id]);
    }

    public function test_eliminar_borra_el_procedimiento_y_su_costo(): void
    {
        $datos = $this->datosBase([
            'estado' => EstadoCirugia::Realizada->value,
            'hora_fin' => '2026-07-15T09:00',
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $cirugia = Cirugia::query()->latest('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete("/cirugias/{$cirugia->id}")
            ->assertRedirect(route('cirugias.index'));

        $this->assertDatabaseMissing('cirugias', ['id' => $cirugia->id]);
        $this->assertDatabaseMissing('costos_cirugia', ['cirugia_id' => $cirugia->id]);
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
