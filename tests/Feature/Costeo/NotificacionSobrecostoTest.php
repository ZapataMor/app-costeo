<?php

namespace Tests\Feature\Costeo;

use App\Enums\EstadoCirugia;
use App\Models\AlertaSobrecosto;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * El aviso en el momento del costeo: sin él, la alerta esperaría a que
 * alguien abriera la bandeja, y para entonces nadie recuerda qué pasó en ese
 * quirófano —que es cuando la causa todavía se puede averiguar—.
 */
class NotificacionSobrecostoTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected User $admin;

    protected ProcedimientoQuirurgico $procedimiento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create(['factor_indirecto' => 0]);
        $this->admin = User::factory()->create(['hospital_id' => $this->hospital->id]);
        HospitalContext::set($this->hospital->id);
        $this->procedimiento = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospital->id]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_costear_una_cirugia_atipica_crea_la_alerta_y_avisa(): void
    {
        // Seis cirugías previas homogéneas: baseline suficiente y estrecho.
        foreach (range(1, 6) as $i) {
            $this->costeadaPrevia(100_000);
        }

        // La séptima consume diez veces más insumos.
        $cirugia = $this->cirugiaConInsumos(1_000_000);

        $respuesta = $this->actingAs($this->admin)
            ->post("/cirugias/{$cirugia->id}/calcular-costo");

        $respuesta->assertRedirect();

        $alerta = AlertaSobrecosto::withoutGlobalScopes()->firstWhere('cirugia_id', $cirugia->id);
        $this->assertNotNull($alerta);

        // El aviso desplaza al «listo»: enterrar la alerta bajo un mensaje de
        // éxito sería, en la práctica, no avisar.
        $toast = $this->toastDeLaSesion();
        $this->assertSame('warning', $toast['type']);
        $this->assertStringContainsString('Sobrecosto detectado', $toast['message']);
    }

    public function test_una_cirugia_normal_no_dispara_ninguna_alerta(): void
    {
        foreach (range(1, 6) as $i) {
            $this->costeadaPrevia(100_000);
        }

        $cirugia = $this->cirugiaConInsumos(105_000);

        $this->actingAs($this->admin)
            ->post("/cirugias/{$cirugia->id}/calcular-costo")
            ->assertRedirect();

        $this->assertDatabaseCount('alertas_sobrecosto', 0);
        $this->assertSame('success', $this->toastDeLaSesion()['type']);
    }

    public function test_devolver_la_cirugia_a_en_proceso_retira_su_alerta(): void
    {
        foreach (range(1, 6) as $i) {
            $this->costeadaPrevia(100_000);
        }

        $cirugia = $this->cirugiaConInsumos(1_000_000);
        $this->actingAs($this->admin)->post("/cirugias/{$cirugia->id}/calcular-costo");

        $this->assertDatabaseCount('alertas_sobrecosto', 1);

        // Sin costo no hay sobrecosto: dejar la alerta viva mandaría a revisar
        // un exceso que ya no existe en ningún indicador.
        $cirugia->update(['estado' => EstadoCirugia::EnProceso->value, 'hora_fin' => null]);
        CostoCirugia::withoutGlobalScopes()->where('cirugia_id', $cirugia->id)->delete();
        AlertaSobrecosto::withoutGlobalScopes()->where('cirugia_id', $cirugia->id)->delete();

        $this->assertDatabaseCount('alertas_sobrecosto', 0);
    }

    /** @return array{type: string, message: string} */
    private function toastDeLaSesion(): array
    {
        $flash = session(SessionKey::FLASH_DATA, []);

        return $flash['toast'] ?? ['type' => 'ninguno', 'message' => ''];
    }

    private function costeadaPrevia(float $costoInsumos): void
    {
        $cirugia = $this->cirugiaConInsumos($costoInsumos);

        CostoCirugia::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'costo_recurso_humano' => 0,
            'costo_sala' => 0,
            'costo_equipos' => 0,
            'costo_insumos' => $costoInsumos,
            'costo_directo' => $costoInsumos,
            'costo_indirecto' => 0,
            'costo_total' => $costoInsumos,
        ]);
    }

    private function cirugiaConInsumos(float $costoInsumos): Cirugia
    {
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $this->hospital->id])->id,
            'sala_operatoria_id' => null,
            'estado' => EstadoCirugia::Realizada->value,
        ]);

        $cirugia->procedimientos()->attach($this->procedimiento->id, ['es_principal' => true]);

        $insumo = Insumo::factory()->create([
            'hospital_id' => $this->hospital->id,
            'costo_unitario' => $costoInsumos,
        ]);

        $cirugia->consumos()->create([
            'hospital_id' => $this->hospital->id,
            'insumo_id' => $insumo->id,
            'cantidad' => 1,
            'costo_unitario_registrado' => $costoInsumos,
            'costo_total' => $costoInsumos,
        ]);

        return $cirugia;
    }
}
