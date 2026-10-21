<?php

namespace Tests\Feature\Conocimiento;

use App\Models\ResultadoClinico;
use App\Services\Conocimiento\AnalizadorMejoresPracticas;
use Inertia\Testing\AssertableInertia as Assert;

class MejoresPracticasYTableroTest extends ConocimientoTestCase
{
    /**
     * Doce cesáreas: las de 1 paquete de insumos y sin complicaciones son
     * el grupo de mejor desempeño; las de 3 paquetes, el resto.
     */
    protected function sembrar(): void
    {
        $otroCirujano = $this->recursos['cirujano']->replicate();
        $otroCirujano->save();

        for ($i = 0; $i < 12; $i++) {
            $costo = $this->cirugia(
                insumos: $i < 4 ? 1 : 3,
                fecha: now()->parse('2026-06-01')->addDays($i)->toDateString(),
                cirujano: $i % 2 === 0 ? $this->recursos['cirujano'] : $otroCirujano,
            );

            ResultadoClinico::create([
                'cirugia_id' => $costo->cirugia_id,
                'hospital_id' => $this->hospital->id,
                'complicacion_intraoperatoria' => $i === 11,
                'dias_estancia' => 2,
            ]);
        }
    }

    public function test_el_analisis_separa_el_mejor_desempeno_y_anonimiza_profesionales(): void
    {
        $this->sembrar();

        $analisis = app(AnalizadorMejoresPracticas::class)->analizar($this->procedimiento, $this->recursos['cirujano']->id);

        $this->assertTrue($analisis['suficiente']);
        $this->assertSame(4, $analisis['grupos']['mejores']['n']);
        $this->assertSame(0.0, $analisis['grupos']['mejores']['tasa_complicaciones']);
        $this->assertSame($this->insumo->id, $analisis['insumos'][0]['insumo_id']);
        $this->assertEqualsWithDelta(300000.0, $analisis['insumos'][0]['diferencia_por_cirugia'], 0.01);

        $profesionales = $analisis['profesionales'];
        $this->assertCount(2, $profesionales);
        $this->assertSame(['Profesional A', 'Profesional B'], array_column($profesionales, 'etiqueta'));
        $this->assertSame(1, count(array_filter(array_column($profesionales, 'es_propio'))));
        $this->assertArrayNotHasKey('recurso_humano_id', $profesionales[0]);
    }

    public function test_con_pocos_casos_no_hay_analisis(): void
    {
        $this->cirugia();

        $analisis = app(AnalizadorMejoresPracticas::class)->analizar($this->procedimiento);

        $this->assertFalse($analisis['suficiente']);
    }

    public function test_el_profesional_ve_la_pagina_sin_costos(): void
    {
        $this->sembrar();

        $this->actingAs($this->profesional())->get("/conocimiento/mejores-practicas/{$this->procedimiento->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('conocimiento/mejores-practicas/show')
                ->where('puedeVerCostos', false)
                ->where('analisis.suficiente', true));
    }

    public function test_el_tablero_resume_la_capa_4(): void
    {
        $this->sembrar();

        $this->actingAs($this->admin)->get('/conocimiento')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('conocimiento/index')
                ->where('resumen.lecciones.meta_mensual', 10)
                ->has('resumen.lecciones.por_mes', 6)
                ->has('resumen.desviacion')
                ->has('resumen.recomendaciones', 4)
                ->where('resumen.alertas.pendientes', fn ($n) => $n > 0));
    }
}
