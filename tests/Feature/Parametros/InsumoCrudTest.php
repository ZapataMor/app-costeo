<?php

namespace Tests\Feature\Parametros;

use App\Models\Cirugia;
use App\Models\ConsumoInsumo;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\User;
use App\Support\HospitalContext;
use Inertia\Testing\AssertableInertia as Assert;

class InsumoCrudTest extends ParametrosTestCase
{
    public function test_el_listado_solo_muestra_insumos_del_hospital_propio(): void
    {
        Insumo::factory()->create(['hospital_id' => $this->hospitalA->id, 'nombre' => 'Gasa A']);
        Insumo::factory()->create(['hospital_id' => $this->hospitalB->id, 'nombre' => 'Gasa B']);

        $this->actingAs($this->adminA)
            ->get('/parametros/insumos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parametros/insumos/index')
                ->has('insumos.data', 1)
                ->where('insumos.data.0.nombre', 'Gasa A'));
    }

    public function test_crear_insumo_asigna_hospital_y_guarda_trazabilidad(): void
    {
        $respuesta = $this->actingAs($this->adminA)->post('/parametros/insumos', [
            'codigo' => 'MAT-100',
            'nombre' => 'Compresa estéril',
            'categoria' => 'material',
            'unidad' => 'unidad',
            'costo_unitario' => 2500,
            'fuente' => 'Factura proveedor 2026-07',
            'nivel_confiabilidad' => 'medido',
        ]);

        $respuesta->assertRedirect('/parametros/insumos');

        $insumo = Insumo::withoutGlobalScopes()->where('codigo', 'MAT-100')->firstOrFail();
        $this->assertSame($this->hospitalA->id, $insumo->hospital_id);
        $this->assertSame('Factura proveedor 2026-07', $insumo->fuente);
        $this->assertSame('medido', $insumo->nivel_confiabilidad->value);
    }

    public function test_rechaza_nivel_de_confiabilidad_invalido(): void
    {
        $this->actingAs($this->adminA)->post('/parametros/insumos', [
            'codigo' => 'MAT-101',
            'nombre' => 'Compresa',
            'categoria' => 'material',
            'unidad' => 'unidad',
            'costo_unitario' => 2500,
            'nivel_confiabilidad' => 'inventado',
        ])->assertSessionHasErrors('nivel_confiabilidad');
    }

    public function test_rechaza_codigo_duplicado_en_el_mismo_hospital(): void
    {
        Insumo::factory()->create(['hospital_id' => $this->hospitalA->id, 'codigo' => 'MAT-1']);

        $this->actingAs($this->adminA)->post('/parametros/insumos', [
            'codigo' => 'MAT-1',
            'nombre' => 'Otro insumo',
            'categoria' => 'material',
            'unidad' => 'unidad',
            'costo_unitario' => 100,
        ])->assertSessionHasErrors('codigo');
    }

    public function test_actualizar_insumo_respeta_su_propio_codigo(): void
    {
        $insumo = Insumo::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'codigo' => 'MAT-1',
            'costo_unitario' => 100,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)->put("/parametros/insumos/{$insumo->id}", [
            'codigo' => 'MAT-1',
            'nombre' => 'Insumo actualizado',
            'categoria' => 'material',
            'unidad' => 'unidad',
            'costo_unitario' => 999,
            'nivel_confiabilidad' => 'estimado',
        ])->assertRedirect('/parametros/insumos');

        $this->assertSame('999.00', $insumo->fresh()->costo_unitario);
    }

    public function test_no_puede_editar_un_insumo_de_otro_hospital(): void
    {
        $ajeno = Insumo::factory()->create(['hospital_id' => $this->hospitalB->id]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)
            ->get("/parametros/insumos/{$ajeno->id}/edit")
            ->assertNotFound();

        $this->actingAs($this->adminA)->put("/parametros/insumos/{$ajeno->id}", [
            'codigo' => 'HACK-1',
            'nombre' => 'Hackeado',
            'categoria' => 'material',
            'unidad' => 'unidad',
            'costo_unitario' => 1,
        ])->assertNotFound();
    }

    public function test_no_elimina_un_insumo_con_consumos_registrados(): void
    {
        HospitalContext::set($this->hospitalA->id);
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospitalA->id]);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospitalA->id]);
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => null,
        ]);
        ConsumoInsumo::factory()->create([
            'cirugia_id' => $cirugia->id,
            'insumo_id' => $insumo->id,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)->delete("/parametros/insumos/{$insumo->id}");

        $this->assertNotNull(Insumo::withoutGlobalScopes()->find($insumo->id));
    }

    public function test_elimina_un_insumo_sin_dependencias(): void
    {
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospitalA->id]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)
            ->delete("/parametros/insumos/{$insumo->id}")
            ->assertRedirect('/parametros/insumos');

        $this->assertNull(Insumo::withoutGlobalScopes()->find($insumo->id));
    }

    public function test_super_admin_sin_hospital_activo_no_puede_crear(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post('/parametros/insumos', [
            'codigo' => 'MAT-200',
            'nombre' => 'Insumo global',
            'categoria' => 'material',
            'unidad' => 'unidad',
            'costo_unitario' => 100,
        ])->assertForbidden();
    }

    public function test_super_admin_con_hospital_activo_si_puede_crear(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->withSession(['hospital_activo_id' => $this->hospitalB->id])
            ->post('/parametros/insumos', [
                'codigo' => 'MAT-300',
                'nombre' => 'Insumo del hospital B',
                'categoria' => 'material',
                'unidad' => 'unidad',
                'costo_unitario' => 100,
            ])->assertRedirect('/parametros/insumos');

        $insumo = Insumo::withoutGlobalScopes()->where('codigo', 'MAT-300')->firstOrFail();
        $this->assertSame($this->hospitalB->id, $insumo->hospital_id);
    }
}
