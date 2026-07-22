<?php

namespace Tests\Feature\Parametros;

use App\Models\EquipoMedico;
use App\Models\Insumo;
use App\Models\PlantillaEquipo;
use App\Models\PlantillaInsumo;
use App\Models\PlantillaPersonal;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Support\HospitalContext;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Plantilla del procedimiento: lo que se usa siempre en él.
 *
 * Lo que se protege aquí es que la plantilla sea del hospital dueño del
 * procedimiento —una plantilla que apunte a insumos de otro hospital sería
 * una fuga de datos y un costeo imposible— y que guardarla la reemplace
 * completa, que es la única forma de que quitar una línea signifique
 * quitarla.
 */
class PlantillaProcedimientoTest extends ParametrosTestCase
{
    protected function procedimiento(): ProcedimientoQuirurgico
    {
        return ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'nombre' => 'Colecistectomía laparoscópica',
        ]);
    }

    public function test_muestra_la_plantilla_actual_con_los_catalogos_del_hospital(): void
    {
        $procedimiento = $this->procedimiento();
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospitalA->id]);
        Insumo::factory()->create(['hospital_id' => $this->hospitalB->id]);

        PlantillaInsumo::create([
            'procedimiento_quirurgico_id' => $procedimiento->id,
            'insumo_id' => $insumo->id,
            'fase' => 'quirurgica',
            'cantidad' => 3,
        ]);

        $this->actingAs($this->adminA)
            ->get("/parametros/procedimientos/{$procedimiento->id}/plantilla")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parametros/procedimientos/plantilla')
                ->has('plantilla.insumos', 1)
                ->where('plantilla.insumos.0.insumo_id', (string) $insumo->id)
                ->where('plantilla.insumos.0.cantidad', '3')
                // El catálogo del selector no puede traer insumos ajenos.
                ->has('insumos', 1));
    }

    public function test_guardar_reemplaza_la_plantilla_completa(): void
    {
        $procedimiento = $this->procedimiento();
        $viejo = Insumo::factory()->create(['hospital_id' => $this->hospitalA->id]);
        $nuevo = Insumo::factory()->create(['hospital_id' => $this->hospitalA->id]);
        $equipo = EquipoMedico::factory()->create(['hospital_id' => $this->hospitalA->id]);
        $anestesiologo = RecursoHumano::factory()->create(['hospital_id' => $this->hospitalA->id]);

        PlantillaInsumo::create([
            'procedimiento_quirurgico_id' => $procedimiento->id,
            'insumo_id' => $viejo->id,
            'fase' => 'quirurgica',
            'cantidad' => 1,
        ]);

        $this->actingAs($this->adminA)
            ->from("/parametros/procedimientos/{$procedimiento->id}/plantilla")
            ->put("/parametros/procedimientos/{$procedimiento->id}/plantilla", [
                'insumos' => [
                    ['insumo_id' => $nuevo->id, 'fase' => 'pre', 'cantidad' => 2, 'opcional' => false],
                    ['insumo_id' => $nuevo->id, 'fase' => 'quirurgica', 'cantidad' => 4, 'opcional' => true],
                ],
                'personal' => [
                    ['rol' => 'cirujano', 'fase' => 'quirurgica', 'cantidad' => 1, 'recurso_humano_id' => null, 'minutos' => null],
                    ['rol' => 'anestesiologo', 'fase' => 'quirurgica', 'cantidad' => 1, 'recurso_humano_id' => $anestesiologo->id, 'minutos' => 90],
                ],
                'equipos' => [
                    ['equipo_medico_id' => $equipo->id, 'minutos_uso' => null, 'opcional' => false],
                ],
            ])
            ->assertRedirect("/parametros/procedimientos/{$procedimiento->id}/plantilla")
            ->assertSessionHasNoErrors();

        $this->assertSame(0, PlantillaInsumo::where('insumo_id', $viejo->id)->count());
        $this->assertSame(2, $procedimiento->plantillaInsumos()->count());
        $this->assertSame(2, $procedimiento->plantillaPersonal()->count());
        $this->assertSame(1, $procedimiento->plantillaEquipos()->count());

        $fijo = PlantillaPersonal::where('rol', 'anestesiologo')->firstOrFail();
        $this->assertSame($anestesiologo->id, $fijo->recurso_humano_id);
        $this->assertSame(90, $fijo->minutos);

        // Un equipo sin minutos significa «todo el tiempo de sala».
        $this->assertNull(PlantillaEquipo::firstOrFail()->minutos_uso);
    }

    public function test_guardar_listas_vacias_borra_la_plantilla(): void
    {
        $procedimiento = $this->procedimiento();
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospitalA->id]);

        PlantillaInsumo::create([
            'procedimiento_quirurgico_id' => $procedimiento->id,
            'insumo_id' => $insumo->id,
            'fase' => 'quirurgica',
            'cantidad' => 1,
        ]);

        $this->actingAs($this->adminA)
            ->from("/parametros/procedimientos/{$procedimiento->id}/plantilla")
            ->put("/parametros/procedimientos/{$procedimiento->id}/plantilla", [
                'insumos' => [],
                'personal' => [],
                'equipos' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $procedimiento->plantillaInsumos()->count());
    }

    public function test_rechaza_insumos_de_otro_hospital(): void
    {
        $procedimiento = $this->procedimiento();
        $ajeno = Insumo::factory()->create(['hospital_id' => $this->hospitalB->id]);

        $this->actingAs($this->adminA)
            ->from("/parametros/procedimientos/{$procedimiento->id}/plantilla")
            ->put("/parametros/procedimientos/{$procedimiento->id}/plantilla", [
                'insumos' => [
                    ['insumo_id' => $ajeno->id, 'fase' => 'quirurgica', 'cantidad' => 1],
                ],
                'personal' => [],
                'equipos' => [],
            ])
            ->assertSessionHasErrors('insumos.0.insumo_id');
    }

    public function test_rechaza_el_mismo_insumo_dos_veces_en_la_misma_fase(): void
    {
        $procedimiento = $this->procedimiento();
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospitalA->id]);

        $this->actingAs($this->adminA)
            ->from("/parametros/procedimientos/{$procedimiento->id}/plantilla")
            ->put("/parametros/procedimientos/{$procedimiento->id}/plantilla", [
                'insumos' => [
                    ['insumo_id' => $insumo->id, 'fase' => 'quirurgica', 'cantidad' => 1],
                    ['insumo_id' => $insumo->id, 'fase' => 'quirurgica', 'cantidad' => 2],
                ],
                'personal' => [],
                'equipos' => [],
            ])
            ->assertSessionHasErrors('insumos.1.fase');
    }

    public function test_la_plantilla_de_otro_hospital_no_es_accesible(): void
    {
        $ajeno = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospitalB->id]);

        HospitalContext::clear();

        $this->actingAs($this->adminA)
            ->get("/parametros/procedimientos/{$ajeno->id}/plantilla")
            ->assertNotFound();
    }

    public function test_borrar_el_procedimiento_se_lleva_su_plantilla(): void
    {
        $procedimiento = $this->procedimiento();
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospitalA->id]);

        PlantillaInsumo::create([
            'procedimiento_quirurgico_id' => $procedimiento->id,
            'insumo_id' => $insumo->id,
            'fase' => 'quirurgica',
            'cantidad' => 1,
        ]);

        $this->actingAs($this->adminA)
            ->delete("/parametros/procedimientos/{$procedimiento->id}");

        $this->assertSame(0, PlantillaInsumo::count());
    }
}
