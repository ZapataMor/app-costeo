<?php

namespace Tests\Feature\Parametros;

use App\Models\ProcedimientoQuirurgico;
use App\Support\HospitalContext;
use Inertia\Testing\AssertableInertia as Assert;

class ProcedimientoCrudTest extends ParametrosTestCase
{
    public function test_el_listado_solo_muestra_procedimientos_del_hospital_propio(): void
    {
        ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospitalA->id, 'nombre' => 'Cesárea A']);
        ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospitalB->id, 'nombre' => 'Cesárea B']);

        $this->actingAs($this->adminA)
            ->get('/parametros/procedimientos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parametros/procedimientos/index')
                ->has('procedimientos.data', 1)
                ->where('procedimientos.data.0.nombre', 'Cesárea A'));
    }

    public function test_crear_procedimiento_con_trazabilidad(): void
    {
        $this->actingAs($this->adminA)->post('/parametros/procedimientos', [
            'codigo_cups' => '740001',
            'nombre' => 'Cesárea segmentaria',
            'especialidad' => 'Ginecobstetricia',
            'complejidad' => 'media',
            'duracion_estimada_minutos' => 120,
            'tarifa_soat' => 850_000,
            'fuente' => 'Protocolo institucional 2026',
            'nivel_confiabilidad' => 'supuesto',
        ])->assertRedirect('/parametros/procedimientos');

        $proc = ProcedimientoQuirurgico::withoutGlobalScopes()->where('codigo_cups', '740001')->firstOrFail();
        $this->assertSame($this->hospitalA->id, $proc->hospital_id);
        $this->assertSame('supuesto', $proc->nivel_confiabilidad->value);
    }

    public function test_rechaza_cups_duplicado_en_el_mismo_hospital(): void
    {
        ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'codigo_cups' => '740001',
        ]);

        $this->actingAs($this->adminA)->post('/parametros/procedimientos', [
            'codigo_cups' => '740001',
            'nombre' => 'Duplicado',
            'especialidad' => 'Cirugía general',
            'complejidad' => 'media',
            'duracion_estimada_minutos' => 60,
        ])->assertSessionHasErrors('codigo_cups');
    }

    public function test_actualiza_un_procedimiento_conservando_su_cups(): void
    {
        $proc = ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'codigo_cups' => '740001',
            'duracion_estimada_minutos' => 120,
        ]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)->put("/parametros/procedimientos/{$proc->id}", [
            'codigo_cups' => '740001',
            'nombre' => $proc->nombre,
            'especialidad' => $proc->especialidad,
            'complejidad' => 'alta',
            'duracion_estimada_minutos' => 150,
            'nivel_confiabilidad' => 'medido',
        ])->assertRedirect('/parametros/procedimientos');

        $this->assertSame(150, $proc->fresh()->duracion_estimada_minutos);
    }

    public function test_no_puede_editar_un_procedimiento_de_otro_hospital(): void
    {
        $ajeno = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospitalB->id]);
        HospitalContext::clear();

        $this->actingAs($this->adminA)
            ->get("/parametros/procedimientos/{$ajeno->id}/edit")
            ->assertNotFound();
    }
}
