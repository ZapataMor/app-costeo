<?php

namespace Tests\Feature\Conocimiento;

use App\Enums\EstadoRecomendacion;
use App\Models\Recomendacion;
use App\Services\Conocimiento\GeneradorRecomendaciones;

class RecomendacionesTest extends ConocimientoTestCase
{
    /**
     * Doce cesáreas con el triple de insumos del protocolo en seis meses:
     * costo de 820.000 frente a una tarifa de referencia de 637.500 (+28,6 %)
     * y dos paquetes de insumo por encima de lo previsto.
     */
    protected function sembrarHistorico(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->cirugia(insumos: 3, fecha: now()->parse('2026-04-06')->addDays($i * 14)->toDateString());
        }
    }

    public function test_deriva_recomendaciones_de_insumo_y_tarifa_con_ahorro(): void
    {
        $this->sembrarHistorico();

        $resultado = app(GeneradorRecomendaciones::class)->generar($this->hospital);

        $insumo = Recomendacion::query()->where('tipo', 'estandarizar_insumo')->firstOrFail();
        $tarifa = Recomendacion::query()->where('tipo', 'renegociar_tarifa')->firstOrFail();

        // Dos paquetes de más × 150.000 × casos al mes.
        $casosMes = (float) ($insumo->evidencia['casos_mes'] ?? 0);
        $this->assertEqualsWithDelta(300000 * $casosMes, (float) $insumo->ahorro_mensual_estimado, 300000 * 0.1);
        $this->assertSame(3.0, (float) ($insumo->evidencia['cantidad_promedio'] ?? 0));
        $this->assertGreaterThan(0, (float) $tarifa->ahorro_mensual_estimado);
        $this->assertGreaterThan(0, $resultado['ahorro_mensual']);
    }

    public function test_regenerar_no_duplica_ni_pisa_una_gestionada(): void
    {
        $this->sembrarHistorico();
        $generador = app(GeneradorRecomendaciones::class);
        $generador->generar($this->hospital);
        $total = Recomendacion::query()->count();

        $recomendacion = Recomendacion::query()->firstOrFail();
        $this->actingAs($this->admin)->patch("/conocimiento/recomendaciones/{$recomendacion->id}", [
            'estado' => 'aceptada',
            'responsable_id' => $this->admin->id,
            'fecha_compromiso' => '2026-12-01',
        ])->assertSessionHasNoErrors();

        $generador->generar($this->hospital);

        $this->assertSame($total, Recomendacion::query()->count());
        $this->assertSame(EstadoRecomendacion::Aceptada, $recomendacion->fresh()?->estado);
    }

    public function test_aceptar_exige_responsable_y_descartar_exige_motivo(): void
    {
        $this->sembrarHistorico();
        app(GeneradorRecomendaciones::class)->generar($this->hospital);
        $recomendacion = Recomendacion::query()->firstOrFail();

        $this->actingAs($this->admin)->patch("/conocimiento/recomendaciones/{$recomendacion->id}", ['estado' => 'aceptada'])
            ->assertSessionHasErrors(['responsable_id', 'fecha_compromiso']);
        $this->actingAs($this->admin)->patch("/conocimiento/recomendaciones/{$recomendacion->id}", ['estado' => 'descartada'])
            ->assertSessionHasErrors('nota');
    }

    public function test_el_profesional_no_gestiona_recomendaciones(): void
    {
        $this->actingAs($this->profesional())->get('/conocimiento/recomendaciones')->assertForbidden();
    }
}
