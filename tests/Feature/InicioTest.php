<?php

namespace Tests\Feature;

use App\Models\AlertaSobrecosto;
use App\Models\Cirugia;
use App\Models\Facturacion;
use App\Models\Paciente;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Conocimiento\ConocimientoTestCase;

/**
 * Portada del administrador (#30): los indicadores del último mes con
 * cirugías frente al anterior y la lista corta de lo que pide acción.
 */
class InicioTest extends ConocimientoTestCase
{
    public function test_compara_el_ultimo_mes_con_cirugias_contra_el_anterior(): void
    {
        $this->cirugia(120, 1, '2026-05-15');
        $junio = $this->cirugia(150, 2, '2026-06-10');
        $this->facturar($junio->cirugia_id, 2_000_000);

        $this->actingAs($this->admin)
            ->get('/inicio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inicio')
                ->where('indicadores.mes', '2026-06')
                ->where('indicadores.mes_anterior', '2026-05')
                ->where('indicadores.costo_promedio.n', 1)
                ->where('indicadores.resultado.actual.n', 1)
                ->where('indicadores.resultado.actual.facturado', fn ($v) => abs((float) $v - 2_000_000) < 0.01)
                ->where('indicadores.resultado.actual.margen', fn ($v) => abs((float) $v - (2_000_000 - (float) $junio->costo_total)) < 0.01)
                ->where('indicadores.resultado.anterior.n', 0));
    }

    public function test_lo_que_necesita_atencion_incluye_alertas_perdidas_y_pendientes(): void
    {
        $costo = $this->cirugia(120, 1, '2026-06-10');
        // Facturada por debajo de su costo: el procedimiento pierde.
        $this->facturar($costo->cirugia_id, 100_000);

        AlertaSobrecosto::factory()->create([
            'hospital_id' => $this->hospital->id,
            'cirugia_id' => $costo->cirugia_id,
            'procedimiento_quirurgico_id' => $this->procedimiento->id,
        ]);

        // Una cirugía sin salida de sala queda pendiente de completar.
        Cirugia::create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $this->hospital->id])->id,
            'sala_operatoria_id' => $this->sala->id,
            'fecha' => '2026-06-11',
            'hora_inicio' => '2026-06-11 08:00:00',
            'tipo' => 'programada',
            'estado' => 'en_proceso',
        ]);

        $this->actingAs($this->admin)
            ->get('/inicio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('atencion.0.clave', 'alertas')
                ->where('atencion.0.casos', 1)
                ->where('atencion.1.clave', 'perdida')
                ->where('atencion.1.procedimiento.id', $this->procedimiento->id)
                ->where('atencion.2.clave', 'pendientes')
                ->where('atencion.2.n', 1));
    }

    public function test_sin_nada_pendiente_la_lista_queda_vacia(): void
    {
        $costo = $this->cirugia(120, 1, '2026-06-10');
        $this->facturar($costo->cirugia_id, 5_000_000);

        $this->actingAs($this->admin)
            ->get('/inicio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('atencion', []));
    }

    public function test_el_admin_llega_a_su_portada_y_el_digitador_no_entra(): void
    {
        $this->actingAs($this->admin)->get('/')->assertRedirect('/inicio');

        $digitador = User::factory()->digitador()->create(['hospital_id' => $this->hospital->id]);
        $this->actingAs($digitador)->get('/inicio')->assertForbidden();
    }

    private function facturar(int $cirugiaId, float $valor): void
    {
        Facturacion::factory()->create([
            'hospital_id' => $this->hospital->id,
            'cirugia_id' => $cirugiaId,
            'valor_facturado' => $valor,
            'valor_glosado' => 0,
            'valor_recaudado' => $valor,
        ]);
    }
}
