<?php

namespace Tests\Feature\Cirugias;

use App\Enums\EstadoCirugia;
use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las tres fases del ciclo quirúrgico: preparación, acto quirúrgico y
 * recuperación. Lo que se protege aquí es que las marcas de tiempo formen una
 * línea coherente y que «realizada» no se pueda afirmar antes de que el
 * paciente egrese.
 */
class FasesDelCicloTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create();
        $this->admin = User::factory()->create([
            'hospital_id' => $this->hospital->id,
            'role' => 'admin_hospital',
            'activo' => true,
        ]);

        HospitalContext::set($this->hospital->id);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    protected function datosBase(array $sobrescribir = []): array
    {
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $sala = SalaOperatoria::factory()->create(['hospital_id' => $this->hospital->id]);

        return [
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $sala->id,
            'fecha' => '2026-07-15',
            'hora_inicio' => '2026-07-15T08:00',
            'tipo' => 'programada',
            'estado' => EstadoCirugia::EnProceso->value,
            'procedimientos' => [['id' => $procedimiento->id, 'es_principal' => true]],
            ...$sobrescribir,
        ];
    }

    public function test_registra_las_cuatro_marcas_y_calcula_los_minutos_de_cada_fase(): void
    {
        $datos = $this->datosBase([
            'hora_ingreso_paciente' => '2026-07-15T07:15',
            'hora_incision' => '2026-07-15T08:20',
            'hora_cierre' => '2026-07-15T09:35',
            'hora_fin' => '2026-07-15T09:50',
            'hora_salida_recuperacion' => '2026-07-15T11:20',
            'estado' => EstadoCirugia::Realizada->value,
        ]);

        $this->actingAs($this->admin)->post('/cirugias', $datos)->assertRedirect();

        $cirugia = Cirugia::query()->latest('id')->firstOrFail();

        $this->assertSame(45, $cirugia->minutosPrequirurgico());
        // Tiempo de sala: es lo que se costea, y no cambia con esta captura.
        $this->assertSame(110, $cirugia->duracionMinutos());
        $this->assertSame(75, $cirugia->minutosQuirurgicoNeto());
        $this->assertSame(90, $cirugia->minutosRecuperacion());
        $this->assertSame(245, $cirugia->cicloTotalMinutos());
    }

    public function test_no_se_puede_marcar_realizada_sin_la_salida_de_recuperacion(): void
    {
        $datos = $this->datosBase([
            'hora_fin' => '2026-07-15T09:50',
            'estado' => EstadoCirugia::Realizada->value,
        ]);

        $this->actingAs($this->admin)
            ->post('/cirugias', $datos)
            ->assertSessionHasErrors('hora_salida_recuperacion');

        $this->assertDatabaseCount('cirugias', 0);
    }

    public function test_el_estado_en_recuperacion_no_exige_el_egreso_pero_si_la_salida_de_sala(): void
    {
        $this->actingAs($this->admin)
            ->post('/cirugias', $this->datosBase([
                'estado' => EstadoCirugia::EnRecuperacion->value,
            ]))
            ->assertSessionHasErrors('hora_fin');

        $this->actingAs($this->admin)
            ->post('/cirugias', $this->datosBase([
                'hora_fin' => '2026-07-15T09:50',
                'estado' => EstadoCirugia::EnRecuperacion->value,
            ]))
            ->assertRedirect();

        $cirugia = Cirugia::query()->latest('id')->firstOrFail();

        $this->assertNull($cirugia->hora_salida_recuperacion);
        // Sin ciclo cerrado no hay costo: sería un total incompleto.
        $this->assertNull($cirugia->costo);
    }

    public function test_las_marcas_fuera_de_orden_se_rechazan(): void
    {
        // La incisión no puede ocurrir antes de que el paciente entre a sala.
        $this->actingAs($this->admin)
            ->post('/cirugias', $this->datosBase([
                'hora_incision' => '2026-07-15T07:30',
                'hora_cierre' => '2026-07-15T09:00',
                'hora_fin' => '2026-07-15T09:30',
            ]))
            ->assertSessionHasErrors('hora_incision');

        // Ni el paciente ingresar después de estar ya operándose.
        $this->actingAs($this->admin)
            ->post('/cirugias', $this->datosBase([
                'hora_ingreso_paciente' => '2026-07-15T10:00',
            ]))
            ->assertSessionHasErrors('hora_inicio');
    }

    public function test_incision_y_cierre_se_exigen_en_pareja(): void
    {
        $this->actingAs($this->admin)
            ->post('/cirugias', $this->datosBase([
                'hora_incision' => '2026-07-15T08:20',
            ]))
            ->assertSessionHasErrors('hora_cierre');

        $this->actingAs($this->admin)
            ->post('/cirugias', $this->datosBase([
                'hora_cierre' => '2026-07-15T09:20',
                'hora_fin' => '2026-07-15T09:50',
            ]))
            ->assertSessionHasErrors('hora_incision');
    }

    public function test_el_protocolo_guarda_los_tiempos_estandar_de_cada_fase(): void
    {
        $this->actingAs($this->admin)
            ->post('/parametros/procedimientos', [
                'codigo_cups' => '470100',
                'nombre' => 'Colecistectomía laparoscópica',
                'especialidad' => 'Cirugía general',
                'complejidad' => 'media',
                'duracion_estimada_minutos' => 90,
                'minutos_prequirurgico' => 45,
                'minutos_recuperacion' => 120,
                'minutos_recambio' => 20,
                'fuente' => 'Levantamiento en sala',
                'nivel_confiabilidad' => 'medido',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $procedimiento = ProcedimientoQuirurgico::query()
            ->where('codigo_cups', '470100')
            ->firstOrFail();

        $this->assertSame(45, $procedimiento->minutos_prequirurgico);
        $this->assertSame(120, $procedimiento->minutos_recuperacion);
        $this->assertSame(20, $procedimiento->minutos_recambio);
        $this->assertSame(275, $procedimiento->cicloTotalMinutos());
    }

    public function test_el_ciclo_total_del_protocolo_ignora_las_fases_sin_levantar(): void
    {
        $procedimiento = ProcedimientoQuirurgico::factory()->create([
            'hospital_id' => $this->hospital->id,
            'duracion_estimada_minutos' => 90,
            'minutos_prequirurgico' => null,
            'minutos_recuperacion' => null,
            'minutos_recambio' => null,
        ]);

        // Mientras no se capturen, el total equivale al tiempo de sala.
        $this->assertSame(90, $procedimiento->cicloTotalMinutos());
    }
}
