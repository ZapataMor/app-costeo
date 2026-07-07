<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidacionesTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create();
        $this->usuario = User::factory()->create(['hospital_id' => $this->hospital->id]);
        $this->actingAs($this->usuario);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_rechaza_cirugia_que_termina_antes_de_empezar(): void
    {
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->postJson('/api/v1/cirugias', [
            'paciente_id' => $paciente->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 10:00:00',
            'hora_fin' => '2026-06-10 08:00:00', // inconsistente
            'tipo' => 'programada',
            'procedimientos' => [['id' => $procedimiento->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('hora_fin');
    }

    public function test_rechaza_diagnostico_cie10_invalido(): void
    {
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->postJson('/api/v1/cirugias', [
            'paciente_id' => $paciente->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'tipo' => 'programada',
            'diagnostico_cie10' => 'no-es-cie10',
            'procedimientos' => [['id' => $procedimiento->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('diagnostico_cie10');
    }

    public function test_rechaza_referencias_a_datos_de_otro_hospital(): void
    {
        $otroHospital = Hospital::factory()->create();
        HospitalContext::set($otroHospital->id);
        $pacienteAjeno = Paciente::factory()->create(['hospital_id' => $otroHospital->id]);
        HospitalContext::clear();

        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->postJson('/api/v1/cirugias', [
            'paciente_id' => $pacienteAjeno->id, // integridad referencial cruzada
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'tipo' => 'programada',
            'procedimientos' => [['id' => $procedimiento->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('paciente_id');
    }

    public function test_rechaza_medicamento_sin_codigo_atc(): void
    {
        $this->postJson('/api/v1/insumos', [
            'codigo' => 'MED-999',
            'nombre' => 'Medicamento sin ATC',
            'categoria' => 'medicamento',
            'unidad' => 'ampolla',
            'costo_unitario' => 5000,
        ])->assertUnprocessable()->assertJsonValidationErrors('codigo_atc');
    }

    public function test_rechaza_codigo_cups_invalido(): void
    {
        $this->postJson('/api/v1/procedimientos', [
            'codigo_cups' => 'ABC12', // debe ser 6 dígitos
            'nombre' => 'Procedimiento X',
            'especialidad' => 'Cirugía general',
            'complejidad' => 'media',
            'duracion_estimada_minutos' => 90,
        ])->assertUnprocessable()->assertJsonValidationErrors('codigo_cups');
    }

    public function test_rechaza_paciente_duplicado_por_documento(): void
    {
        $this->postJson('/api/v1/pacientes', $datos = [
            'tipo_documento' => 'CC',
            'documento' => '1234567890',
            'nombres' => 'Ana',
            'apellidos' => 'García',
            'regimen' => 'subsidiado',
            'zona' => 'urbana',
        ])->assertCreated();

        // El mismo documento en el mismo hospital se rechaza (comparado por hash)
        $this->postJson('/api/v1/pacientes', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documento');
    }

    public function test_registra_cirugia_completa_y_calcula_su_costo(): void
    {
        HospitalContext::set($this->hospital->id);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
        $sala = SalaOperatoria::factory()->create(['hospital_id' => $this->hospital->id, 'costo_hora' => 60_000]);
        $recurso = RecursoHumano::factory()->create(['hospital_id' => $this->hospital->id, 'rol' => 'cirujano']);
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospital->id, 'costo_unitario' => 10_000]);
        HospitalContext::clear();

        $respuesta = $this->postJson('/api/v1/cirugias', [
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $sala->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => '2026-06-10 09:00:00',
            'tipo' => 'programada',
            'diagnostico_cie10' => 'K35.8',
            'procedimientos' => [['id' => $procedimiento->id, 'es_principal' => true]],
            'equipo' => [
                ['recurso_humano_id' => $recurso->id, 'rol' => 'cirujano', 'minutos_participacion' => 60],
            ],
            'consumos' => [
                ['insumo_id' => $insumo->id, 'cantidad' => 3],
            ],
        ])->assertCreated();

        $cirugiaId = $respuesta->json('id');

        // El consumo registra el snapshot del precio: 3 × 10.000 = 30.000
        $this->assertEqualsWithDelta(30000.0, (float) $respuesta->json('consumos.0.costo_total'), 0.01);

        $costo = $this->postJson("/api/v1/cirugias/{$cirugiaId}/calcular-costo")
            ->assertOk()
            ->json();

        $this->assertEqualsWithDelta(60000.0, (float) $costo['costo_sala'], 0.01); // 60.000/h × 1 h
        $this->assertEqualsWithDelta(30000.0, (float) $costo['costo_insumos'], 0.01);
        $this->assertGreaterThan(0, (float) $costo['costo_recurso_humano']);
    }
}
