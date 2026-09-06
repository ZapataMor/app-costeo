<?php

namespace Tests\Feature\Costeo;

use App\Enums\CategoriaCif;
use App\Enums\EstadoCirugia;
use App\Enums\OrigenComponente;
use App\Exceptions\SolapeDeCostoIndirectoException;
use App\Models\Cirugia;
use App\Models\ConceptoCostoIndirecto;
use App\Models\EquipoMedico;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Services\Costing\ActivarCategoriaCif;
use App\Services\Costing\DesactivarCategoriaCif;
use App\Services\Costing\TdabcCostingService;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El mecanismo anti-doble-conteo: una bolsa CIF y el componente directo que
 * reemplaza no pueden estar activos a la vez, y el paso entre un estado y
 * otro es atómico.
 */
class ActivarCategoriaCifTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_un_hospital_nuevo_tiene_todos_los_componentes_digitados(): void
    {
        $hospital = Hospital::factory()->create();

        $this->assertSame(OrigenComponente::Digitado, $hospital->origen_infraestructura);
        $this->assertSame(OrigenComponente::Digitado, $hospital->origen_depreciacion_equipos);
        $this->assertSame(OrigenComponente::Digitado, $hospital->origen_personal_indirecto);
    }

    public function test_bloquea_la_activacion_si_el_componente_directo_sigue_digitado(): void
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);

        SalaOperatoria::factory()->create([
            'hospital_id' => $hospital->id,
            'nombre' => 'Sala 1',
            'costo_hora' => 180_000,
            'activa' => true,
        ]);
        ConceptoCostoIndirecto::factory()->create(['hospital_id' => $hospital->id]);

        try {
            app(ActivarCategoriaCif::class)->ejecutar($hospital, CategoriaCif::Infraestructura);
            $this->fail('Se esperaba SolapeDeCostoIndirectoException.');
        } catch (SolapeDeCostoIndirectoException $e) {
            // El mensaje tiene que nombrar los registros en conflicto, no
            // decir «hay un conflicto»: el usuario debe saber qué tocar.
            $this->assertStringContainsString('Sala 1', $e->getMessage());
            $this->assertStringContainsString('180.000', $e->getMessage());
            $this->assertStringContainsString('costo/hora de las salas', $e->getMessage());
            $this->assertCount(1, $e->conflictos);
        }

        // Nada cambió: ni el concepto ni el origen.
        $this->assertFalse(ConceptoCostoIndirecto::first()->activo);
        $this->assertSame(
            OrigenComponente::Digitado,
            $hospital->fresh()->origen_infraestructura,
        );
    }

    public function test_activa_cuando_el_usuario_confirma_el_cambio_de_origen(): void
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);

        $sala = SalaOperatoria::factory()->create([
            'hospital_id' => $hospital->id,
            'costo_hora' => 180_000,
        ]);
        $concepto = ConceptoCostoIndirecto::factory()->create(['hospital_id' => $hospital->id]);

        $activados = app(ActivarCategoriaCif::class)
            ->ejecutar($hospital, CategoriaCif::Infraestructura, confirmado: true);

        $this->assertSame(1, $activados);
        $this->assertTrue($concepto->fresh()->activo);
        $this->assertSame(
            OrigenComponente::DerivadoDeCif,
            $hospital->fresh()->origen_infraestructura,
        );

        // El valor digitado NO se destruye: es lo que permite comparar el
        // método anterior con el de bolsas y volver atrás sin re-digitar.
        $this->assertEqualsWithDelta(180_000, (float) $sala->fresh()->costo_hora, 0.01);
    }

    public function test_un_componente_en_cero_no_bloquea_la_activacion(): void
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);

        // Cero legítimo: sala prestada, sin costo propio que duplicar.
        SalaOperatoria::factory()->create([
            'hospital_id' => $hospital->id,
            'costo_hora' => 0,
        ]);
        ConceptoCostoIndirecto::factory()->create(['hospital_id' => $hospital->id]);

        app(ActivarCategoriaCif::class)->ejecutar($hospital, CategoriaCif::Infraestructura);

        $this->assertSame(
            OrigenComponente::DerivadoDeCif,
            $hospital->fresh()->origen_infraestructura,
        );
    }

    public function test_un_componente_inactivo_no_bloquea_la_activacion(): void
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);

        SalaOperatoria::factory()->create([
            'hospital_id' => $hospital->id,
            'costo_hora' => 180_000,
            'activa' => false,
        ]);
        ConceptoCostoIndirecto::factory()->create(['hospital_id' => $hospital->id]);

        app(ActivarCategoriaCif::class)->ejecutar($hospital, CategoriaCif::Infraestructura);

        $this->assertSame(
            OrigenComponente::DerivadoDeCif,
            $hospital->fresh()->origen_infraestructura,
        );
    }

    public function test_una_categoria_sin_equivalente_directo_se_activa_sin_confirmacion(): void
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);

        // Administración no duplica ningún campo del costo directo.
        ConceptoCostoIndirecto::factory()
            ->categoria(CategoriaCif::Administracion)
            ->create(['hospital_id' => $hospital->id]);

        $activados = app(ActivarCategoriaCif::class)
            ->ejecutar($hospital, CategoriaCif::Administracion);

        $this->assertSame(1, $activados);
        // Y no toca ninguna columna de origen.
        $this->assertSame(OrigenComponente::Digitado, $hospital->fresh()->origen_infraestructura);
    }

    public function test_detecta_el_solape_en_equipos_y_en_personal(): void
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);

        EquipoMedico::factory()->create([
            'hospital_id' => $hospital->id,
            'nombre' => 'Electrobisturí',
            'costo_hora' => 15_000,
        ]);
        RecursoHumano::factory()->create([
            'hospital_id' => $hospital->id,
            'nombre' => 'Dra. Carmen',
            'costos_indirectos_mensuales' => 1_000_000,
        ]);

        $servicio = app(ActivarCategoriaCif::class);

        $this->assertCount(1, $servicio->componentesDigitados($hospital, CategoriaCif::DepreciacionEquipos));
        $this->assertCount(1, $servicio->componentesDigitados($hospital, CategoriaCif::PersonalIndirecto));
        $this->assertSame([], $servicio->componentesDigitados($hospital, CategoriaCif::Administracion));
    }

    public function test_desactivar_devuelve_el_componente_a_digitado(): void
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);

        $concepto = ConceptoCostoIndirecto::factory()->create(['hospital_id' => $hospital->id]);

        app(ActivarCategoriaCif::class)
            ->ejecutar($hospital, CategoriaCif::Infraestructura, confirmado: true);
        app(DesactivarCategoriaCif::class)
            ->ejecutar($hospital->fresh(), CategoriaCif::Infraestructura);

        $this->assertFalse($concepto->fresh()->activo);
        $this->assertSame(
            OrigenComponente::Digitado,
            $hospital->fresh()->origen_infraestructura,
        );
    }

    public function test_la_activacion_no_cruza_hospitales(): void
    {
        $hospitalA = Hospital::factory()->create();
        $hospitalB = Hospital::factory()->create();

        $conceptoA = ConceptoCostoIndirecto::factory()->create(['hospital_id' => $hospitalA->id]);
        $conceptoB = ConceptoCostoIndirecto::factory()->create(['hospital_id' => $hospitalB->id]);

        app(ActivarCategoriaCif::class)
            ->ejecutar($hospitalA, CategoriaCif::Infraestructura, confirmado: true);

        $this->assertTrue($conceptoA->fresh()->activo);
        $this->assertFalse($conceptoB->fresh()->activo);
        $this->assertSame(
            OrigenComponente::Digitado,
            $hospitalB->fresh()->origen_infraestructura,
        );
    }

    /**
     * La garantía del corte: mientras el motor de asignación no exista, tener
     * conceptos —activos o no— no puede mover ninguna cifra.
     */
    public function test_el_catalogo_no_altera_el_costeo(): void
    {
        $hospital = Hospital::factory()->create(['factor_indirecto' => 0.10]);
        HospitalContext::set($hospital->id);

        $cirugia = $this->cirugiaCosteable($hospital);
        $antes = app(TdabcCostingService::class)->calcular($cirugia);
        $directoAntes = (float) $antes->costo_directo;
        $indirectoAntes = (float) $antes->costo_indirecto;

        ConceptoCostoIndirecto::factory()->create(['hospital_id' => $hospital->id]);
        app(ActivarCategoriaCif::class)
            ->ejecutar($hospital, CategoriaCif::Infraestructura, confirmado: true);

        $despues = app(TdabcCostingService::class)->calcular($cirugia->fresh());

        $this->assertEqualsWithDelta($directoAntes, (float) $despues->costo_directo, 0.01);
        $this->assertEqualsWithDelta($indirectoAntes, (float) $despues->costo_indirecto, 0.01);
        $this->assertEqualsWithDelta(
            $directoAntes * 0.10,
            (float) $despues->costo_indirecto,
            0.01,
        );
    }

    private function cirugiaCosteable(Hospital $hospital): Cirugia
    {
        $sala = SalaOperatoria::factory()->create([
            'hospital_id' => $hospital->id,
            'costo_hora' => 40_000,
        ]);

        return Cirugia::factory()->create([
            'hospital_id' => $hospital->id,
            'paciente_id' => Paciente::factory()->create(['hospital_id' => $hospital->id])->id,
            'sala_operatoria_id' => $sala->id,
            'estado' => EstadoCirugia::Realizada->value,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => '2026-06-10 10:00:00',
            'minutos_disponibles_mes_registrado' => 18_720,
            'costo_hora_sala_registrado' => 40_000,
            'factor_indirecto_registrado' => 0.10,
        ]);
    }
}
