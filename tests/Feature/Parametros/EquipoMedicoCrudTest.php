<?php

namespace Tests\Feature\Parametros;

use App\Models\EquipoMedico;
use App\Support\HospitalContext;
use Inertia\Testing\AssertableInertia as Assert;

class EquipoMedicoCrudTest extends ParametrosTestCase
{
    public function test_el_listado_solo_muestra_equipos_del_hospital_propio(): void
    {
        EquipoMedico::factory()->create(['hospital_id' => $this->hospitalA->id, 'nombre' => 'Electrobisturí A']);
        EquipoMedico::factory()->create(['hospital_id' => $this->hospitalB->id, 'nombre' => 'Electrobisturí B']);

        $this->actingAs($this->adminA)
            ->get('/parametros/equipos-medicos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parametros/equipos-medicos/index')
                ->has('equipos.data', 1)
                ->where('equipos.data.0.nombre', 'Electrobisturí A'));
    }

    public function test_crear_equipo_con_trazabilidad(): void
    {
        $this->actingAs($this->adminA)->post('/parametros/equipos-medicos', [
            'nombre' => 'Torre de laparoscopia',
            'codigo' => 'EQ-100',
            'valor_adquisicion' => 280_000_000,
            'vida_util_anios' => 10,
            'costo_hora' => 60_000,
            'fuente' => 'Inventario de activos 2026',
            'nivel_confiabilidad' => 'estimado',
        ])->assertRedirect('/parametros/equipos-medicos');

        $equipo = EquipoMedico::withoutGlobalScopes()->where('codigo', 'EQ-100')->firstOrFail();
        $this->assertSame($this->hospitalA->id, $equipo->hospital_id);
        $this->assertSame('estimado', $equipo->nivel_confiabilidad->value);
    }

    public function test_rechaza_costo_hora_no_positivo(): void
    {
        $this->actingAs($this->adminA)->post('/parametros/equipos-medicos', [
            'nombre' => 'Monitor',
            'costo_hora' => 0,
        ])->assertSessionHasErrors('costo_hora');
    }

    public function test_actualiza_y_elimina_un_equipo_propio(): void
    {
        $equipo = EquipoMedico::factory()->create(['hospital_id' => $this->hospitalA->id, 'costo_hora' => 10_000]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)->put("/parametros/equipos-medicos/{$equipo->id}", [
            'nombre' => $equipo->nombre,
            'costo_hora' => 15_000,
            'nivel_confiabilidad' => 'medido',
        ])->assertRedirect('/parametros/equipos-medicos');

        $this->assertSame('15000.00', $equipo->fresh()->costo_hora);

        $this->actingAs($this->adminA)
            ->delete("/parametros/equipos-medicos/{$equipo->id}")
            ->assertRedirect('/parametros/equipos-medicos');

        $this->assertNull(EquipoMedico::withoutGlobalScopes()->find($equipo->id));
    }

    public function test_no_puede_eliminar_un_equipo_de_otro_hospital(): void
    {
        $ajeno = EquipoMedico::factory()->create(['hospital_id' => $this->hospitalB->id]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)
            ->delete("/parametros/equipos-medicos/{$ajeno->id}")
            ->assertNotFound();
    }
}
