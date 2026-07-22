<?php

namespace Tests\Feature\Costeo;

use App\Enums\RolQuirurgico;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonalCosteoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Minutos disponibles/mes congelados en cada cirugía de prueba. Con este
     * denominador y un costo mensual de 1.000.000 el costo/minuto es de 100
     * pesos exactos, así que los montos esperados se pueden verificar a mano.
     */
    private const MINUTOS_DISPONIBLES = 10_000;

    protected Hospital $hospitalA;

    protected Hospital $hospitalB;

    protected User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospitalA = Hospital::factory()->create();
        $this->hospitalB = Hospital::factory()->create();
        $this->adminA = User::factory()->create(['hospital_id' => $this->hospitalA->id]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_el_ranking_calcula_el_costo_propio_con_la_formula_tdabc(): void
    {
        $procedimiento = $this->procedimiento($this->hospitalA);
        $cirujano = $this->persona($this->hospitalA, 'Ana Cirujana');

        // 100 min y 150 min a 100 $/min → 10.000 + 15.000 = 25.000.
        $this->cirugiaCon($procedimiento, [[$cirujano, RolQuirurgico::Cirujano, 100]], 500_000);
        $this->cirugiaCon($procedimiento, [[$cirujano, RolQuirurgico::Cirujano, 150]], 700_000);

        $this->actingAs($this->adminA)
            ->get('/costeo/personal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/personal/index')
                ->has('personal', 1)
                ->where('personal.0.id', $cirujano->id)
                ->where('personal.0.n_cirugias', 2)
                ->where('personal.0.minutos_total', 250)
                ->where('personal.0.minutos_promedio', 125)
                ->where('personal.0.costo_propio_total', fn ($v) => abs((float) $v - 25_000.0) < 0.01)
                ->where('personal.0.costo_propio_promedio', fn ($v) => abs((float) $v - 12_500.0) < 0.01)
                ->where('totales.costo_propio_total', fn ($v) => abs((float) $v - 25_000.0) < 0.01)
                ->where('totales.n_personas_con_actividad', 1));
    }

    public function test_el_costo_inducido_solo_cuenta_las_cirugias_que_encabeza_como_cirujano(): void
    {
        $procedimiento = $this->procedimiento($this->hospitalA);
        $cirujano = $this->persona($this->hospitalA, 'Ana Cirujana');
        $instrumentador = $this->persona($this->hospitalA, 'Beto Instrumentador');

        $this->cirugiaCon($procedimiento, [
            [$cirujano, RolQuirurgico::Cirujano, 100],
            [$instrumentador, RolQuirurgico::Instrumentador, 100],
        ], 500_000);

        $this->actingAs($this->adminA)
            ->get('/costeo/personal')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($cirujano, $instrumentador) {
                $personal = collect($page->toArray()['props']['personal'])->keyBy('id');

                // El cirujano moviliza la cirugía completa; el instrumentador
                // participa en ella pero no la encabeza.
                $this->assertSame(1, $personal[$cirujano->id]['n_como_cirujano']);
                $this->assertEqualsWithDelta(500_000.0, $personal[$cirujano->id]['costo_inducido_total'], 0.01);
                $this->assertSame(0, $personal[$instrumentador->id]['n_como_cirujano']);
                $this->assertNull($personal[$instrumentador->id]['costo_inducido_total']);

                // El costo propio, en cambio, es el de sus minutos en ambos casos.
                $this->assertEqualsWithDelta(10_000.0, $personal[$cirujano->id]['costo_propio_total'], 0.01);
                $this->assertEqualsWithDelta(10_000.0, $personal[$instrumentador->id]['costo_propio_total'], 0.01);
            });
    }

    public function test_el_indice_compara_contra_el_promedio_del_mismo_procedimiento(): void
    {
        $procedimiento = $this->procedimiento($this->hospitalA);
        $caro = $this->persona($this->hospitalA, 'Ana Cara');
        $barato = $this->persona($this->hospitalA, 'Beto Barato');

        // Promedio del procedimiento: (200.000 + 100.000) / 2 = 150.000.
        $this->cirugiaCon($procedimiento, [[$caro, RolQuirurgico::Cirujano, 60]], 200_000);
        $this->cirugiaCon($procedimiento, [[$barato, RolQuirurgico::Cirujano, 60]], 100_000);

        $this->actingAs($this->adminA)
            ->get('/costeo/personal')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($caro, $barato) {
                $personal = collect($page->toArray()['props']['personal'])->keyBy('id');

                $this->assertEqualsWithDelta(1.333, $personal[$caro->id]['indice_costo'], 0.001);
                $this->assertEqualsWithDelta(0.667, $personal[$barato->id]['indice_costo'], 0.001);
                $this->assertSame(1, $personal[$caro->id]['n_comparables']);
            });
    }

    public function test_no_calcula_indice_cuando_el_procedimiento_no_tiene_con_que_compararse(): void
    {
        $procedimiento = $this->procedimiento($this->hospitalA);
        $cirujano = $this->persona($this->hospitalA, 'Ana Cirujana');

        // Una sola cirugía: el promedio sería ella misma y el índice, 1,00 por
        // construcción. Preferimos no dar un número que no significa nada.
        $this->cirugiaCon($procedimiento, [[$cirujano, RolQuirurgico::Cirujano, 60]], 300_000);

        $this->actingAs($this->adminA)
            ->get('/costeo/personal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('personal.0.indice_costo', null)
                ->where('personal.0.n_comparables', 0)
                ->where('personal.0.n_como_cirujano', 1));
    }

    public function test_la_ficha_muestra_el_historico_y_los_desgloses(): void
    {
        $procedimiento = $this->procedimiento($this->hospitalA);
        $persona = $this->persona($this->hospitalA, 'Ana Cirujana');

        $this->cirugiaCon($procedimiento, [
            [$persona, RolQuirurgico::Cirujano, 100],
            [$persona, RolQuirurgico::Ayudante, 20],
        ], 400_000);

        $this->actingAs($this->adminA)
            ->get("/costeo/personal/{$persona->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/personal/show')
                ->where('persona.id', $persona->id)
                ->where('persona.n_cirugias', 1)
                ->where('persona.n_participaciones', 2)
                ->where('persona.minutos_total', 120)
                ->where('persona.costo_propio_total', fn ($v) => abs((float) $v - 12_000.0) < 0.01)
                ->has('por_rol', 2)
                ->has('por_fase', 1)
                ->has('historial.data', 2)
                ->where('historial.data.0.costo_total_cirugia', fn ($v) => abs((float) $v - 400_000.0) < 0.01)
                ->has('porProcedimiento', 1));
    }

    public function test_solo_cuenta_cirugias_realizadas_y_terminadas(): void
    {
        $procedimiento = $this->procedimiento($this->hospitalA);
        $persona = $this->persona($this->hospitalA, 'Ana Cirujana');

        $this->cirugiaCon($procedimiento, [[$persona, RolQuirurgico::Cirujano, 100]], 500_000, [
            'estado' => 'cancelada',
        ]);

        $this->actingAs($this->adminA)
            ->get('/costeo/personal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('personal.0.n_cirugias', 0)
                ->where('personal.0.costo_propio_total', fn ($v) => (float) $v === 0.0)
                ->where('totales.n_personas_con_actividad', 0));
    }

    public function test_el_periodo_acota_el_calculo(): void
    {
        $procedimiento = $this->procedimiento($this->hospitalA);
        $persona = $this->persona($this->hospitalA, 'Ana Cirujana');

        $this->cirugiaCon($procedimiento, [[$persona, RolQuirurgico::Cirujano, 100]], 500_000, [
            'fecha' => '2026-01-15',
        ]);
        $this->cirugiaCon($procedimiento, [[$persona, RolQuirurgico::Cirujano, 100]], 500_000, [
            'fecha' => '2026-03-10',
        ]);

        $this->actingAs($this->adminA)
            ->get('/costeo/personal?desde=2026-02-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('personal.0.n_cirugias', 1)
                ->where('personal.0.costo_propio_total', fn ($v) => abs((float) $v - 10_000.0) < 0.01));
    }

    public function test_no_muestra_personal_de_otro_hospital(): void
    {
        $this->persona($this->hospitalA, 'Ana del A');
        $personaB = $this->persona($this->hospitalB, 'Beto del B');

        $this->actingAs($this->adminA)
            ->get('/costeo/personal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('personal', 1)
                ->where('personal.0.nombre', 'Ana del A'));

        $this->actingAs($this->adminA)
            ->get("/costeo/personal/{$personaB->id}")
            ->assertNotFound();
    }

    public function test_el_digitador_no_accede_al_costeo_por_persona(): void
    {
        $digitador = User::factory()->digitador()
            ->create(['hospital_id' => $this->hospitalA->id]);

        $this->actingAs($digitador)->get('/costeo/personal')->assertForbidden();
    }

    private function persona(Hospital $hospital, string $nombre): RecursoHumano
    {
        return RecursoHumano::withoutGlobalScopes()->create([
            'hospital_id' => $hospital->id,
            'nombre' => $nombre,
            'rol' => RolQuirurgico::Cirujano->value,
            'especialidad' => 'Cirugía general',
            'salario_mensual' => 1_000_000,
            'prestaciones_mensuales' => 0,
            'costos_indirectos_mensuales' => 0,
            'activo' => true,
        ]);
    }

    private function procedimiento(Hospital $hospital): ProcedimientoQuirurgico
    {
        return ProcedimientoQuirurgico::factory()->create(['hospital_id' => $hospital->id]);
    }

    /**
     * Cirugía costeada con su equipo quirúrgico.
     *
     * @param  list<array{0: RecursoHumano, 1: RolQuirurgico, 2: int}>  $equipo
     * @param  array<string, mixed>  $atributos
     */
    private function cirugiaCon(
        ProcedimientoQuirurgico $procedimiento,
        array $equipo,
        float $costoTotal,
        array $atributos = [],
    ): Cirugia {
        $hospital = $procedimiento->hospital_id === $this->hospitalA->id
            ? $this->hospitalA
            : $this->hospitalB;

        HospitalContext::set($hospital->id);

        $cirugia = Cirugia::factory()->create(array_merge([
            'hospital_id' => $hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $hospital->id])->id,
            'sala_operatoria_id' => null,
            'minutos_disponibles_mes_registrado' => self::MINUTOS_DISPONIBLES,
        ], $atributos));

        $cirugia->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

        foreach ($equipo as [$persona, $rol, $minutos]) {
            MiembroEquipoQuirurgico::create([
                'cirugia_id' => $cirugia->id,
                'recurso_humano_id' => $persona->id,
                'rol' => $rol->value,
                'minutos_participacion' => $minutos,
                'costo_mensual_registrado' => 1_000_000,
            ]);
        }

        CostoCirugia::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $hospital->id,
            'costo_total' => $costoTotal,
        ]);

        HospitalContext::clear();

        return $cirugia;
    }
}
