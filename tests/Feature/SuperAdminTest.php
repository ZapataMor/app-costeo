<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_sin_hospital_seleccionado_ve_el_consolidado_de_todos_los_hospitales(): void
    {
        $this->crearHospitalConCirugia(costoTotal: 100_000);
        $this->crearHospitalConCirugia(costoTotal: 900_000);

        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->getJson('/api/v1/kpis/costos')
            ->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 2)
            ->assertJsonPath('global.costo_promedio', 500000);
    }

    public function test_puede_entrar_a_un_hospital_con_el_switcher_y_volver_al_consolidado(): void
    {
        $this->crearHospitalConCirugia(costoTotal: 100_000);
        [$hospitalB] = $this->crearHospitalConCirugia(costoTotal: 900_000);

        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        // Entra al hospital B
        $this->post('/hospital-activo', ['hospital_id' => $hospitalB->id])->assertRedirect();

        $this->getJson('/api/v1/kpis/costos')
            ->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 1)
            ->assertJsonPath('global.costo_promedio', 900000);

        // Vuelve al consolidado
        $this->post('/hospital-activo', ['hospital_id' => null])->assertRedirect();

        $this->getJson('/api/v1/kpis/costos')
            ->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 2);
    }

    public function test_el_switcher_rechaza_un_hospital_inexistente(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->postJson('/hospital-activo', ['hospital_id' => 9999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hospital_id');
    }

    public function test_su_hospital_id_personal_no_activa_el_scope(): void
    {
        [$hospitalA] = $this->crearHospitalConCirugia(costoTotal: 100_000);
        $this->crearHospitalConCirugia(costoTotal: 900_000);

        // Un super_admin con hospital_id asignado (caso atípico) sigue viendo todo
        // mientras no seleccione un hospital con el switcher.
        $superAdmin = User::factory()->superAdmin()->create(['hospital_id' => $hospitalA->id]);

        $this->actingAs($superAdmin)
            ->getJson('/api/v1/kpis/costos')
            ->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 2);
    }

    public function test_un_usuario_registrado_por_fortify_queda_bloqueado_hasta_tener_hospital(): void
    {
        $this->crearHospitalConCirugia(costoTotal: 100_000);

        $respuesta = $this->post('/register', [
            'name' => 'Usuario Nuevo',
            'email' => 'nuevo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $nuevo = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertTrue($nuevo->isAdminHospital());
        $this->assertNull($nuevo->hospital_id);

        $this->actingAs($nuevo)->getJson('/api/v1/kpis/costos')->assertForbidden();
        $this->actingAs($nuevo)->get('/costeo')->assertForbidden();
    }

    /**
     * @return array{0: Hospital, 1: Cirugia}
     */
    protected function crearHospitalConCirugia(int $costoTotal): array
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);

        $paciente = Paciente::factory()->create(['hospital_id' => $hospital->id]);
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $hospital->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => null,
        ]);
        CostoCirugia::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $hospital->id,
            'costo_total' => $costoTotal,
        ]);

        HospitalContext::clear();

        return [$hospital, $cirugia];
    }
}
