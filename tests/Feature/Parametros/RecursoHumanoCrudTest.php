<?php

namespace Tests\Feature\Parametros;

use App\Models\RecursoHumano;
use App\Support\HospitalContext;
use Inertia\Testing\AssertableInertia as Assert;

class RecursoHumanoCrudTest extends ParametrosTestCase
{
    public function test_el_listado_solo_muestra_recursos_del_hospital_propio(): void
    {
        RecursoHumano::factory()->create(['hospital_id' => $this->hospitalA->id, 'nombre' => 'Dra. A']);
        RecursoHumano::factory()->create(['hospital_id' => $this->hospitalB->id, 'nombre' => 'Dr. B']);

        $this->actingAs($this->adminA)
            ->get('/parametros/recursos-humanos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parametros/recursos-humanos/index')
                ->has('recursos.data', 1)
                ->where('recursos.data.0.nombre', 'Dra. A'));
    }

    public function test_crear_recurso_con_trazabilidad(): void
    {
        $this->actingAs($this->adminA)->from('/parametros/recursos-humanos')->post('/parametros/recursos-humanos', [
            'nombre' => 'Dra. Carmen Epiayú',
            'rol' => 'cirujano',
            'especialidad' => 'Ginecobstetricia',
            'salario_mensual' => 10_000_000,
            'prestaciones_mensuales' => 4_600_000,
            'costos_indirectos_mensuales' => 1_000_000,
            'fuente' => 'Nómina 2026-06',
            'nivel_confiabilidad' => 'medido',
        ])->assertRedirect('/parametros/recursos-humanos');

        $recurso = RecursoHumano::withoutGlobalScopes()->where('nombre', 'Dra. Carmen Epiayú')->firstOrFail();
        $this->assertSame($this->hospitalA->id, $recurso->hospital_id);
        $this->assertSame('medido', $recurso->nivel_confiabilidad->value);
        $this->assertSame('Nómina 2026-06', $recurso->fuente);
    }

    public function test_rechaza_rol_quirurgico_invalido(): void
    {
        $this->actingAs($this->adminA)->from('/parametros/recursos-humanos')->post('/parametros/recursos-humanos', [
            'nombre' => 'Dr. X',
            'rol' => 'gerente',
            'salario_mensual' => 1_000_000,
            'prestaciones_mensuales' => 0,
            'costos_indirectos_mensuales' => 0,
        ])->assertSessionHasErrors('rol');
    }

    public function test_actualiza_un_recurso_propio(): void
    {
        $recurso = RecursoHumano::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'salario_mensual' => 1_000_000,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)->put("/parametros/recursos-humanos/{$recurso->id}", [
            'nombre' => $recurso->nombre,
            'rol' => $recurso->rol,
            'salario_mensual' => 2_000_000,
            'prestaciones_mensuales' => 0,
            'costos_indirectos_mensuales' => 0,
            'nivel_confiabilidad' => 'estimado',
        ])->assertRedirect('/parametros/recursos-humanos');

        $this->assertSame('2000000.00', $recurso->fresh()->salario_mensual);
    }

    public function test_no_puede_actualizar_un_recurso_de_otro_hospital(): void
    {
        $ajeno = RecursoHumano::factory()->create(['hospital_id' => $this->hospitalB->id]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)->put("/parametros/recursos-humanos/{$ajeno->id}", [
            'nombre' => 'Hackeado',
            'rol' => 'cirujano',
            'salario_mensual' => 1,
            'prestaciones_mensuales' => 0,
            'costos_indirectos_mensuales' => 0,
        ])->assertNotFound();
    }

    public function test_elimina_un_recurso_sin_participaciones(): void
    {
        $recurso = RecursoHumano::factory()->create(['hospital_id' => $this->hospitalA->id]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)
            ->delete("/parametros/recursos-humanos/{$recurso->id}")
            ->assertRedirect('/parametros/recursos-humanos');

        $this->assertNull(RecursoHumano::withoutGlobalScopes()->find($recurso->id));
    }
}
