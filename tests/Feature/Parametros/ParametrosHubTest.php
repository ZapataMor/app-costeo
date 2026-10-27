<?php

namespace Tests\Feature\Parametros;

use App\Models\Insumo;
use App\Models\RecursoHumano;
use Inertia\Testing\AssertableInertia as Assert;

class ParametrosHubTest extends ParametrosTestCase
{
    /**
     * El hub solo muestra cuántos registros tiene cada catálogo del hospital
     * propio; el contenido se ve al entrar a cada card.
     */
    public function test_el_hub_entrega_solo_los_conteos_del_hospital_propio(): void
    {
        Insumo::factory()->count(2)->create(['hospital_id' => $this->hospitalA->id]);
        Insumo::factory()->create(['hospital_id' => $this->hospitalB->id]);
        RecursoHumano::factory()->create(['hospital_id' => $this->hospitalA->id]);

        $this->actingAs($this->adminA)
            ->get('/parametros')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parametros/index')
                ->where('modulos.insumos', 2)
                ->where('modulos.recursosHumanos', 1)
                ->where('modulos.equiposMedicos', 0)
                ->where('hospitalActivo.nombre', $this->hospitalA->nombre));
    }
}
