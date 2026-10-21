<?php

namespace Tests\Feature\Conocimiento;

use App\Enums\EstadoAlerta;
use App\Enums\TipoReglaConocimiento;
use App\Models\AlertaConocimiento;
use App\Models\ReglaConocimiento;
use App\Services\Costing\TdabcCostingService;
use Inertia\Testing\AssertableInertia as Assert;

class ReglasConocimientoTest extends ConocimientoTestCase
{
    public function test_una_cirugia_en_sobrecosto_alto_genera_alerta_con_su_impacto(): void
    {
        // 4 paquetes de insumos en vez de 1: +450.000 sobre 520.000 → +86,5 %.
        $costo = $this->cirugia(insumos: 4);

        $alerta = AlertaConocimiento::query()->where('tipo', TipoReglaConocimiento::DesviacionEstandar->value)->firstOrFail();

        $this->assertSame($costo->cirugia_id, $alerta->cirugia_id);
        $this->assertEqualsWithDelta(450000.0, (float) $alerta->impacto_cop, 0.01);
        $this->assertSame('insumos', $alerta->datos['componentes'][0]['componente'] ?? null);
    }

    public function test_una_cirugia_segun_protocolo_no_genera_alertas_de_cirugia(): void
    {
        $this->cirugia();

        $this->assertSame(0, AlertaConocimiento::query()->count());
    }

    public function test_el_tiempo_prolongado_se_detecta_sobre_la_duracion_estimada(): void
    {
        $this->cirugia(minutosSala: 200);

        $alerta = AlertaConocimiento::query()->where('tipo', TipoReglaConocimiento::TiempoProlongado->value)->firstOrFail();
        $this->assertEqualsWithDelta(200 / 120 - 1, (float) $alerta->valor, 0.001);
    }

    public function test_reevaluar_no_pisa_una_alerta_revisada_y_retira_la_que_ya_no_aplica(): void
    {
        $costo = $this->cirugia(insumos: 4);
        $alerta = AlertaConocimiento::query()->firstOrFail();
        $alerta->update(['estado' => EstadoAlerta::Revisada, 'comentario' => 'Caso complejo']);

        app(TdabcCostingService::class)->calcular($costo->cirugia()->firstOrFail());
        $this->assertSame('Caso complejo', $alerta->fresh()?->comentario);

        // Una segunda cirugía con sobrecosto, cuyo registro se corrige.
        $otra = $this->cirugia(insumos: 4, fecha: '2026-06-11');
        $this->assertSame(2, AlertaConocimiento::query()->where('tipo', 'desviacion_estandar')->count());

        $cirugia = $otra->cirugia()->firstOrFail();
        $cirugia->consumos()->update(['cantidad' => 1, 'costo_total' => 150_000]);
        app(TdabcCostingService::class)->calcular($cirugia->fresh());

        $this->assertSame(1, AlertaConocimiento::query()->where('tipo', 'desviacion_estandar')->count());
    }

    public function test_una_regla_desactivada_no_alerta(): void
    {
        $this->cirugia();
        ReglaConocimiento::query()->where('tipo', 'desviacion_estandar')->update(['activa' => false]);

        $this->cirugia(insumos: 4, fecha: '2026-06-11');

        $this->assertSame(0, AlertaConocimiento::query()->where('tipo', 'desviacion_estandar')->count());
    }

    public function test_el_margen_negativo_se_evalua_por_mes_cerrado(): void
    {
        // Tarifa de referencia 637.500; tres cesáreas de 970.000.
        foreach (['2026-05-04', '2026-05-11', '2026-05-18'] as $fecha) {
            $this->cirugia(insumos: 4, fecha: $fecha);
        }

        $this->actingAs($this->admin)->post('/conocimiento/reglas/evaluar')->assertRedirect();

        $alerta = AlertaConocimiento::query()->where('tipo', 'margen_negativo')->firstOrFail();
        $this->assertSame('2026-05', $alerta->periodo);
        $this->assertEqualsWithDelta((970000 - 637500) * 3, (float) $alerta->impacto_cop, 1);
    }

    public function test_el_administrador_ajusta_una_regla_y_el_profesional_no_puede(): void
    {
        $this->cirugia();
        $regla = ReglaConocimiento::query()->where('tipo', 'tiempo_prolongado')->firstOrFail();

        $this->actingAs($this->admin)->get('/conocimiento/reglas')
            ->assertInertia(fn (Assert $page) => $page->component('conocimiento/reglas')->has('reglas', 8));

        $this->actingAs($this->admin)->put("/conocimiento/reglas/{$regla->id}", [
            'activa' => true,
            'severidad' => 'critica',
            'parametros' => ['exceso_pct' => 30],
        ])->assertSessionHasNoErrors();
        $this->assertSame(30.0, $regla->fresh()?->parametro('exceso_pct'));

        $this->actingAs($this->profesional())->get('/conocimiento/reglas')->assertForbidden();
    }

    public function test_la_bandeja_cierra_alertas_y_exige_motivo_al_descartar(): void
    {
        $this->cirugia(insumos: 4);
        $alerta = AlertaConocimiento::query()->firstOrFail();

        $this->actingAs($this->admin)->patch("/conocimiento/alertas/{$alerta->id}", ['estado' => 'descartada'])
            ->assertSessionHasErrors('comentario');

        $this->actingAs($this->admin)->patch("/conocimiento/alertas/{$alerta->id}", [
            'estado' => 'revisada',
            'causa' => 'consumo_excesivo_insumos',
        ])->assertSessionHasNoErrors();

        $this->assertSame(EstadoAlerta::Revisada, $alerta->fresh()?->estado);
    }
}
