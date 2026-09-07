<?php

namespace Tests\Feature\Parametros;

use App\Models\ConceptoCostoIndirecto;
use App\Models\Scopes\HospitalScope;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

class HospitalConfiguracionTest extends ParametrosTestCase
{
    public function test_muestra_la_configuracion_del_hospital_propio(): void
    {
        $this->actingAs($this->adminA)
            ->get('/parametros/hospital')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parametros/hospital')
                ->where('configuracion.id', $this->hospitalA->id)
                ->where('minutosDisponiblesMes', $this->hospitalA->minutosDisponiblesMes()));
    }

    /**
     * Con bolsas activas el factor indirecto no se aplica. La pantalla que lo
     * edita tiene que poder decirlo, o el usuario seguirá creyendo que ese
     * campo manda.
     */
    public function test_avisa_cuantas_bolsas_activas_dejan_sin_efecto_el_factor(): void
    {
        ConceptoCostoIndirecto::factory()->create(['hospital_id' => $this->hospitalA->id]);
        ConceptoCostoIndirecto::withoutGlobalScope(HospitalScope::class)->update(['activo' => true]);

        $this->actingAs($this->adminA)
            ->get('/parametros/hospital')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('bolsasActivas', 1));
    }

    public function test_actualiza_horas_dias_y_factor_indirecto(): void
    {
        $this->actingAs($this->adminA)->put('/parametros/hospital', [
            'horas_dia' => 10,
            'dias_mes' => 24,
            'factor_indirecto' => 0.15,
        ])->assertRedirect('/parametros/hospital');

        $hospital = $this->hospitalA->fresh();
        $this->assertSame(10, (int) $hospital->horas_dia);
        $this->assertSame(24, (int) $hospital->dias_mes);
        $this->assertEqualsWithDelta(0.15, (float) $hospital->factor_indirecto, 0.0001);
        $this->assertSame(10 * 24 * 60, $hospital->minutosDisponiblesMes());
    }

    public function test_valida_los_rangos_de_la_configuracion(): void
    {
        $this->actingAs($this->adminA)->put('/parametros/hospital', [
            'horas_dia' => 30,
            'dias_mes' => 40,
            'factor_indirecto' => 2,
        ])->assertSessionHasErrors(['horas_dia', 'dias_mes', 'factor_indirecto']);
    }

    public function test_super_admin_sin_hospital_activo_no_puede_ver_la_configuracion(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get('/parametros/hospital')
            ->assertRedirect('/parametros');
    }

    public function test_super_admin_con_hospital_activo_edita_ese_hospital(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->withSession(['hospital_activo_id' => $this->hospitalB->id])
            ->put('/parametros/hospital', [
                'horas_dia' => 8,
                'dias_mes' => 20,
                'factor_indirecto' => 0.05,
            ])->assertRedirect('/parametros/hospital');

        $this->assertSame(8, (int) $this->hospitalB->fresh()->horas_dia);
    }
}
