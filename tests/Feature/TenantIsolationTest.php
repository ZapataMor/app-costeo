<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_un_usuario_solo_ve_los_datos_de_su_hospital(): void
    {
        [$hospitalA, $cirugiaA] = $this->crearHospitalConCirugia(costoTotal: 100_000);
        [$hospitalB] = $this->crearHospitalConCirugia(costoTotal: 900_000);

        $usuarioA = User::factory()->create(['hospital_id' => $hospitalA->id]);
        $this->actingAs($usuarioA);
        HospitalContext::clear(); // el contexto pasa a resolverse por el usuario autenticado

        $this->assertSame(1, Cirugia::count());
        $this->assertSame($cirugiaA->id, Cirugia::first()->id);
        $this->assertSame(1, CostoCirugia::count());
        $this->assertEqualsWithDelta(100_000.0, (float) CostoCirugia::first()->costo_total, 0.01);
    }

    public function test_los_kpis_solo_agregan_datos_del_hospital_del_usuario(): void
    {
        [$hospitalA] = $this->crearHospitalConCirugia(costoTotal: 100_000);
        $this->crearHospitalConCirugia(costoTotal: 900_000);

        $usuarioA = User::factory()->create(['hospital_id' => $hospitalA->id]);
        HospitalContext::clear();

        $respuesta = $this->actingAs($usuarioA)->getJson('/api/v1/kpis/costos');

        $respuesta->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 1)
            ->assertJsonPath('global.costo_promedio', 100000);
    }

    public function test_una_cirugia_de_otro_hospital_responde_404(): void
    {
        [$hospitalA] = $this->crearHospitalConCirugia(costoTotal: 100_000);
        [, $cirugiaB] = $this->crearHospitalConCirugia(costoTotal: 900_000);

        $usuarioA = User::factory()->create(['hospital_id' => $hospitalA->id]);
        HospitalContext::clear();

        $this->actingAs($usuarioA)
            ->postJson("/api/v1/cirugias/{$cirugiaB->id}/calcular-costo")
            ->assertNotFound();
    }

    public function test_un_admin_no_puede_forzar_el_hospital_id_de_otro_hospital_al_crear(): void
    {
        [$hospitalA] = $this->crearHospitalConCirugia(costoTotal: 100_000);
        [$hospitalB] = $this->crearHospitalConCirugia(costoTotal: 900_000);

        $usuarioA = User::factory()->create(['hospital_id' => $hospitalA->id]);
        HospitalContext::clear();

        $respuesta = $this->actingAs($usuarioA)->postJson('/api/v1/insumos', [
            'hospital_id' => $hospitalB->id, // intento de escribir en otro hospital
            'codigo' => 'MAT-999',
            'nombre' => 'Gasa estéril',
            'categoria' => 'material',
            'unidad' => 'paquete',
            'costo_unitario' => 1500,
        ]);

        $respuesta->assertCreated();

        $insumo = Insumo::withoutGlobalScopes()->where('codigo', 'MAT-999')->firstOrFail();
        $this->assertSame($hospitalA->id, $insumo->hospital_id);
    }

    public function test_un_admin_sin_hospital_asignado_es_rechazado_en_rutas_de_dominio(): void
    {
        $this->crearHospitalConCirugia(costoTotal: 100_000);

        $sinHospital = User::factory()->create(['hospital_id' => null]);

        $this->actingAs($sinHospital)->get('/costeo')->assertForbidden();
        $this->actingAs($sinHospital)->getJson('/api/v1/kpis/costos')->assertForbidden();
    }

    public function test_un_admin_no_puede_usar_el_switcher_de_hospital(): void
    {
        [$hospitalA] = $this->crearHospitalConCirugia(costoTotal: 100_000);
        [$hospitalB] = $this->crearHospitalConCirugia(costoTotal: 900_000);

        $usuarioA = User::factory()->create(['hospital_id' => $hospitalA->id]);
        HospitalContext::clear();

        $this->actingAs($usuarioA)
            ->postJson('/hospital-activo', ['hospital_id' => $hospitalB->id])
            ->assertForbidden();

        // Y aunque tuviera algo en sesión, el middleware siempre re-fija su hospital.
        $this->actingAs($usuarioA)
            ->withSession(['hospital_activo_id' => $hospitalB->id])
            ->getJson('/api/v1/kpis/costos')
            ->assertOk()
            ->assertJsonPath('global.costo_promedio', 100000);
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
