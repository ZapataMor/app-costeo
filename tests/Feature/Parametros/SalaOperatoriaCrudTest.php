<?php

namespace Tests\Feature\Parametros;

use App\Models\SalaOperatoria;
use App\Support\HospitalContext;
use Inertia\Testing\AssertableInertia as Assert;

class SalaOperatoriaCrudTest extends ParametrosTestCase
{
    public function test_el_listado_solo_muestra_salas_del_hospital_propio(): void
    {
        SalaOperatoria::factory()->create(['hospital_id' => $this->hospitalA->id, 'nombre' => 'Sala A1']);
        SalaOperatoria::factory()->create(['hospital_id' => $this->hospitalB->id, 'nombre' => 'Sala B1']);

        $this->actingAs($this->adminA)
            ->get('/parametros/salas-operatorias')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parametros/salas-operatorias/index')
                ->has('salas.data', 1)
                ->where('salas.data.0.nombre', 'Sala A1'));
    }

    public function test_crear_sala_con_equipamiento_y_trazabilidad(): void
    {
        $this->actingAs($this->adminA)->post('/parametros/salas-operatorias', [
            'nombre' => 'Sala 3',
            'ubicacion' => 'Piso 2',
            'costo_hora' => 40_000,
            'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica'],
            'fuente' => 'Estimación jefe de central 2026-07',
            'nivel_confiabilidad' => 'estimado',
        ])->assertRedirect('/parametros/salas-operatorias');

        $sala = SalaOperatoria::withoutGlobalScopes()->where('nombre', 'Sala 3')->firstOrFail();
        $this->assertSame($this->hospitalA->id, $sala->hospital_id);
        $this->assertSame(['lámpara cielítica', 'mesa quirúrgica'], $sala->equipamiento);
        $this->assertSame('estimado', $sala->nivel_confiabilidad->value);
    }

    public function test_rechaza_nombre_duplicado_en_el_mismo_hospital(): void
    {
        SalaOperatoria::factory()->create(['hospital_id' => $this->hospitalA->id, 'nombre' => 'Sala 1']);

        $this->actingAs($this->adminA)->post('/parametros/salas-operatorias', [
            'nombre' => 'Sala 1',
            'costo_hora' => 40_000,
        ])->assertSessionHasErrors('nombre');
    }

    public function test_permite_el_mismo_nombre_en_otro_hospital(): void
    {
        SalaOperatoria::factory()->create(['hospital_id' => $this->hospitalB->id, 'nombre' => 'Sala 1']);

        $this->actingAs($this->adminA)->post('/parametros/salas-operatorias', [
            'nombre' => 'Sala 1',
            'costo_hora' => 40_000,
        ])->assertRedirect('/parametros/salas-operatorias');

        $this->assertSame(
            2,
            SalaOperatoria::withoutGlobalScopes()->where('nombre', 'Sala 1')->count(),
        );
    }

    public function test_actualiza_una_sala_conservando_su_nombre(): void
    {
        $sala = SalaOperatoria::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'nombre' => 'Sala 1',
            'costo_hora' => 40_000,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)->put("/parametros/salas-operatorias/{$sala->id}", [
            'nombre' => 'Sala 1',
            'costo_hora' => 45_000,
            'nivel_confiabilidad' => 'medido',
        ])->assertRedirect('/parametros/salas-operatorias');

        $this->assertSame('45000.00', $sala->fresh()->costo_hora);
    }
}
