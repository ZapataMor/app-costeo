<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\RegistroActividad;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HistorialTest extends TestCase
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

    public function test_el_inicio_de_sesion_queda_registrado(): void
    {
        $this->post('/login', [
            'email' => $this->adminA->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('registros_actividad', [
            'user_id' => $this->adminA->id,
            'accion' => 'inició sesión',
            'hospital_id' => $this->hospitalA->id,
        ]);
    }

    public function test_crear_un_dato_queda_registrado_con_usuario_y_hospital(): void
    {
        $this->actingAs($this->adminA)->post('/parametros/insumos', [
            'codigo' => 'MAT-900',
            'nombre' => 'Gasa auditada',
            'categoria' => 'material',
            'unidad' => 'unidad',
            'costo_unitario' => 1500,
            'nivel_confiabilidad' => 'medido',
        ]);

        $registro = RegistroActividad::query()->where('accion', 'creó')->first();

        $this->assertNotNull($registro);
        $this->assertSame($this->adminA->id, $registro->user_id);
        $this->assertSame($this->hospitalA->id, $registro->hospital_id);
        $this->assertStringContainsString('Gasa auditada', $registro->descripcion);
    }

    public function test_actualizar_y_eliminar_quedan_registrados(): void
    {
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospitalA->id, 'nombre' => 'Sonda']);

        $this->actingAs($this->adminA);

        $insumo->update(['nombre' => 'Sonda Foley']);
        $insumo->delete();

        $this->assertDatabaseHas('registros_actividad', ['accion' => 'actualizó', 'auditable_id' => $insumo->id]);
        $this->assertDatabaseHas('registros_actividad', ['accion' => 'eliminó', 'auditable_id' => $insumo->id]);
    }

    public function test_admin_hospital_solo_ve_la_actividad_de_su_hospital(): void
    {
        RegistroActividad::query()->create([
            'user_id' => $this->adminA->id,
            'hospital_id' => $this->hospitalA->id,
            'accion' => 'creó',
            'descripcion' => 'Actividad del hospital A',
            'created_at' => now(),
        ]);
        RegistroActividad::query()->create([
            'user_id' => null,
            'hospital_id' => $this->hospitalB->id,
            'accion' => 'creó',
            'descripcion' => 'Actividad del hospital B',
            'created_at' => now(),
        ]);

        $this->actingAs($this->adminA)
            ->get('/historial')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('historial/index')
                ->has('registros.data', 1)
                ->where('registros.data.0.descripcion', 'Actividad del hospital A'));
    }

    public function test_super_admin_ve_toda_la_actividad(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        RegistroActividad::query()->create([
            'user_id' => $this->adminA->id,
            'hospital_id' => $this->hospitalA->id,
            'accion' => 'creó',
            'descripcion' => 'Actividad del hospital A',
            'created_at' => now(),
        ]);
        RegistroActividad::query()->create([
            'user_id' => null,
            'hospital_id' => $this->hospitalB->id,
            'accion' => 'creó',
            'descripcion' => 'Actividad del hospital B',
            'created_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get('/historial')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('historial/index')
                ->has('registros.data', 2));
    }
}
