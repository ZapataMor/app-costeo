<?php

namespace Tests\Feature\Costeo;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProcedimientosCosteoTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_el_catalogo_lista_los_procedimientos_del_hospital_con_estadisticas(): void
    {
        $procedimientoA = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospitalA->id]);
        ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospitalB->id]);

        $this->crearCirugiaCosteada($this->hospitalA, $procedimientoA, 100_000);
        $this->crearCirugiaCosteada($this->hospitalA, $procedimientoA, 200_000);

        $this->actingAs($this->adminA)
            ->get('/costeo/procedimientos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/procedimientos/index')
                ->has('procedimientos.data', 1)
                ->where('procedimientos.data.0.id', $procedimientoA->id)
                ->where('procedimientos.data.0.n_realizadas', 2)
                ->where('procedimientos.data.0.n_costeadas', 2)
                ->where(
                    'procedimientos.data.0.costo_promedio',
                    fn ($valor) => abs((float) $valor - 150_000.0) < 0.01,
                ));
    }

    public function test_el_catalogo_filtra_por_nombre_y_especialidad(): void
    {
        ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'nombre' => 'Apendicectomía por laparotomía',
            'especialidad' => 'Cirugía general',
        ]);
        ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $this->hospitalA->id,
            'nombre' => 'Colecistectomía laparoscópica',
            'especialidad' => 'Cirugía hepatobiliar',
        ]);

        $this->actingAs($this->adminA)
            ->get('/costeo/procedimientos?q=Apendi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('procedimientos.data', 1)
                ->where('procedimientos.data.0.nombre', 'Apendicectomía por laparotomía'));

        $this->actingAs($this->adminA)
            ->get('/costeo/procedimientos?especialidad='.urlencode('Cirugía hepatobiliar'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('procedimientos.data', 1)
                ->where('procedimientos.data.0.nombre', 'Colecistectomía laparoscópica'));
    }

    public function test_el_detalle_del_procedimiento_lista_solo_sus_cirugias(): void
    {
        $procedimientoA1 = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospitalA->id]);
        $procedimientoA2 = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospitalA->id]);

        $cirugia = $this->crearCirugiaCosteada($this->hospitalA, $procedimientoA1, 180_000);
        $this->crearCirugiaCosteada($this->hospitalA, $procedimientoA2, 90_000);

        $this->actingAs($this->adminA)
            ->get("/costeo/procedimientos/{$procedimientoA1->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/procedimientos/show')
                ->where('procedimiento.id', $procedimientoA1->id)
                ->has('cirugias.data', 1)
                ->where('cirugias.data.0.id', $cirugia->id)
                ->where('estadisticas.n_realizadas', 1)
                ->where('estadisticas.n_costeadas', 1)
                ->where(
                    'estadisticas.costo_promedio',
                    fn ($valor) => abs((float) $valor - 180_000.0) < 0.01,
                ));
    }

    public function test_el_detalle_del_procedimiento_filtra_por_fecha_y_estado(): void
    {
        $procedimiento = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospitalA->id]);

        $this->crearCirugiaCosteada($this->hospitalA, $procedimiento, 100_000, ['fecha' => '2026-01-15']);
        $reciente = $this->crearCirugiaCosteada($this->hospitalA, $procedimiento, 120_000, ['fecha' => '2026-03-10']);

        $this->actingAs($this->adminA)
            ->get("/costeo/procedimientos/{$procedimiento->id}?desde=2026-02-01")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cirugias.data', 1)
                ->where('cirugias.data.0.id', $reciente->id));

        $this->actingAs($this->adminA)
            ->get("/costeo/procedimientos/{$procedimiento->id}?estado=cancelada")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cirugias.data', 0));
    }

    public function test_la_vista_de_la_cirugia_muestra_su_informacion_y_costo(): void
    {
        $procedimiento = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospitalA->id]);
        $cirugia = $this->crearCirugiaCosteada($this->hospitalA, $procedimiento, 250_000);

        $this->actingAs($this->adminA)
            ->get("/costeo/procedimientos/{$procedimiento->id}/cirugias/{$cirugia->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/procedimientos/cirugia')
                ->where('procedimiento.id', $procedimiento->id)
                ->where('cirugia.id', $cirugia->id)
                ->has('cirugia.equipo')
                ->has('cirugia.consumos')
                ->has('cirugia.equipos_medicos')
                ->where(
                    'costo.costo_total',
                    fn ($valor) => abs((float) $valor - 250_000.0) < 0.01,
                ));
    }

    public function test_responde_404_si_la_cirugia_no_pertenece_al_procedimiento(): void
    {
        $procedimientoA1 = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospitalA->id]);
        $procedimientoA2 = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospitalA->id]);
        $cirugiaDeA2 = $this->crearCirugiaCosteada($this->hospitalA, $procedimientoA2, 80_000);

        $this->actingAs($this->adminA)
            ->get("/costeo/procedimientos/{$procedimientoA1->id}/cirugias/{$cirugiaDeA2->id}")
            ->assertNotFound();
    }

    public function test_no_accede_a_procedimientos_de_otro_hospital(): void
    {
        $procedimientoB = ProcedimientoQuirurgico::factory()
            ->create(['hospital_id' => $this->hospitalB->id]);

        $this->actingAs($this->adminA)
            ->get("/costeo/procedimientos/{$procedimientoB->id}")
            ->assertNotFound();
    }

    public function test_el_digitador_no_accede_al_explorador(): void
    {
        $digitador = User::factory()->digitador()
            ->create(['hospital_id' => $this->hospitalA->id]);

        $this->actingAs($digitador)->get('/costeo/procedimientos')->assertForbidden();
    }

    /**
     * Crea una cirugía realizada del hospital indicado, con el
     * procedimiento como principal y un costo TDABC ya calculado.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function crearCirugiaCosteada(
        Hospital $hospital,
        ProcedimientoQuirurgico $procedimiento,
        float $costoTotal,
        array $atributos = [],
    ): Cirugia {
        HospitalContext::set($hospital->id);

        $cirugia = Cirugia::factory()->create(array_merge([
            'hospital_id' => $hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $hospital->id])->id,
            'sala_operatoria_id' => null,
        ], $atributos));

        $cirugia->procedimientos()->attach($procedimiento->id, ['es_principal' => true]);

        CostoCirugia::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $hospital->id,
            'costo_total' => $costoTotal,
        ]);

        HospitalContext::clear();

        return $cirugia;
    }
}
