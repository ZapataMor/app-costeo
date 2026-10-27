<?php

namespace Tests\Feature;

use App\Models\AlertaConocimiento;
use App\Models\AlertaSobrecosto;
use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\User;
use App\Services\Alertas\BandejaAlertas;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Bandeja única de alertas (#32): sobrecostos y reglas agrupados por
 * procedimiento, con un solo contador en el menú.
 */
class BandejaAlertasTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected Hospital $otro;

    protected User $admin;

    protected ProcedimientoQuirurgico $cesarea;

    protected ProcedimientoQuirurgico $hernia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create();
        $this->otro = Hospital::factory()->create();
        $this->admin = User::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->cesarea = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id, 'nombre' => 'Cesárea']);
        $this->hernia = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id, 'nombre' => 'Herniorrafia']);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_agrupa_las_dos_fuentes_por_procedimiento_sin_sumar_sus_montos(): void
    {
        $this->sobrecosto($this->cesarea, 1_000_000, '2026-05-10');
        $this->sobrecosto($this->cesarea, 500_000, '2026-06-02');
        $this->regla($this->cesarea, 'critica', 300_000, '2026-06');
        $this->regla($this->hernia, 'advertencia', 80_000, '2026-06');
        $this->regla(null, 'informativa', 0, '2026-06');

        $grupos = collect(app(BandejaAlertas::class)->grupos($this->hospital->id))->keyBy('clave');

        $this->assertCount(3, $grupos);

        $cesarea = $grupos[(string) $this->cesarea->id];
        $this->assertSame(2, $cesarea['sobrecostos']['pendientes']);
        $this->assertEqualsWithDelta(1_500_000, $cesarea['sobrecostos']['exceso'], 0.01);
        $this->assertSame(1, $cesarea['reglas']['pendientes']);
        $this->assertSame(1, $cesarea['reglas']['criticas']);
        $this->assertEqualsWithDelta(300_000, $cesarea['reglas']['impacto'], 0.01);
        $this->assertSame(['2026-05', '2026-06'], $cesarea['meses']);
        $this->assertTrue($cesarea['prioritario']);

        // Solo advertencias: no es prioritario.
        $this->assertFalse($grupos[(string) $this->hernia->id]['prioritario']);

        // Las alertas de sala o del mes del hospital van juntas.
        $this->assertNull($grupos[BandejaAlertas::SIN_PROCEDIMIENTO]['procedimiento']);
    }

    public function test_los_casos_prioritarios_van_primero(): void
    {
        $this->regla($this->hernia, 'advertencia', 9_000_000, '2026-06');
        $this->sobrecosto($this->cesarea, 100_000, '2026-06-02');

        $grupos = app(BandejaAlertas::class)->grupos($this->hospital->id);

        $this->assertSame((string) $this->cesarea->id, $grupos[0]['clave']);
    }

    public function test_filtra_por_mes(): void
    {
        $this->sobrecosto($this->cesarea, 1_000_000, '2026-05-10');
        $this->regla($this->hernia, 'critica', 50_000, '2026-06');

        $grupos = app(BandejaAlertas::class)->grupos($this->hospital->id, '2026-06');

        $this->assertCount(1, $grupos);
        $this->assertSame((string) $this->hernia->id, $grupos[0]['clave']);
        $this->assertSame(['2026-06', '2026-05'], app(BandejaAlertas::class)->meses($this->hospital->id));
    }

    public function test_la_pagina_muestra_solo_el_hospital_activo(): void
    {
        $this->sobrecosto($this->cesarea, 1_000_000, '2026-05-10');

        $ajeno = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->otro->id]);
        $this->sobrecosto($ajeno, 7_000_000, '2026-05-10', $this->otro);

        $this->actingAs($this->admin)
            ->get('/alertas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('alertas/index')
                ->has('grupos', 1)
                ->where('grupos.0.procedimiento.id', $this->cesarea->id)
                ->where('resumen.sobrecostos', 1)
                ->where('resumen.prioritarios', 1));
    }

    public function test_el_menu_cuenta_casos_prioritarios_y_no_alertas_sueltas(): void
    {
        $this->sobrecosto($this->cesarea, 1_000_000, '2026-05-10');
        $this->sobrecosto($this->cesarea, 2_000_000, '2026-05-11');
        $this->regla($this->cesarea, 'critica', 1, '2026-05');
        $this->regla(null, 'critica', 1, '2026-05');
        // Una advertencia sola no enciende el contador.
        $this->regla($this->hernia, 'advertencia', 1, '2026-05');

        $this->actingAs($this->admin)
            ->get('/parametros')
            ->assertInertia(fn (Assert $page) => $page->where('alertasPorRevisar', 2));
    }

    public function test_cada_caso_abre_la_bandeja_de_su_fuente_filtrada(): void
    {
        $this->sobrecosto($this->cesarea, 1_000_000, '2026-05-10');
        $this->sobrecosto($this->hernia, 1_000_000, '2026-05-10');
        $this->sobrecosto($this->cesarea, 1_000_000, '2026-06-10');

        $this->actingAs($this->admin)
            ->get("/costeo/alertas?procedimiento={$this->cesarea->id}&mes=2026-05")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('alertas.data', 1)
                ->where('caso.procedimiento.id', $this->cesarea->id)
                ->where('caso.mes', '2026-05'));

        $this->regla(null, 'critica', 1, '2026-05');
        $this->regla($this->hernia, 'critica', 1, '2026-05');

        $this->actingAs($this->admin)
            ->get('/conocimiento/alertas?procedimiento='.BandejaAlertas::SIN_PROCEDIMIENTO)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('alertas.data', 1)
                ->where('alertas.data.0.procedimiento', null)
                ->where('caso.sin_procedimiento', true));
    }

    public function test_el_digitador_no_entra_a_la_bandeja(): void
    {
        $digitador = User::factory()->digitador()->create(['hospital_id' => $this->hospital->id]);

        $this->actingAs($digitador)->get('/alertas')->assertForbidden();
    }

    private function sobrecosto(ProcedimientoQuirurgico $procedimiento, float $exceso, string $fecha, ?Hospital $hospital = null): AlertaSobrecosto
    {
        $hospital ??= $this->hospital;
        HospitalContext::set($hospital->id);

        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $hospital->id])->id,
            'sala_operatoria_id' => null,
            'fecha' => $fecha,
        ]);

        $alerta = AlertaSobrecosto::factory()->create([
            'hospital_id' => $hospital->id,
            'cirugia_id' => $cirugia->id,
            'procedimiento_quirurgico_id' => $procedimiento->id,
            'exceso' => $exceso,
        ]);

        HospitalContext::clear();

        return $alerta;
    }

    private function regla(?ProcedimientoQuirurgico $procedimiento, string $severidad, float $impacto, string $periodo): AlertaConocimiento
    {
        HospitalContext::set($this->hospital->id);

        $alerta = AlertaConocimiento::create([
            'hospital_id' => $this->hospital->id,
            'tipo' => 'margen_negativo',
            'severidad' => $severidad,
            'titulo' => 'Hallazgo de prueba',
            'descripcion' => 'Descripción',
            'procedimiento_quirurgico_id' => $procedimiento?->id,
            'periodo' => $periodo,
            'impacto_cop' => $impacto,
            'clave' => uniqid('prueba-', true),
            'estado' => 'pendiente',
        ]);

        HospitalContext::clear();

        return $alerta;
    }
}
