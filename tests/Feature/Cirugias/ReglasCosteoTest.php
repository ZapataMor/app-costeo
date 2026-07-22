<?php

namespace Tests\Feature\Cirugias;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\EquipoMedico;
use App\Models\Facturacion;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Services\Cirugias\RegistrarCirugia;
use App\Services\Costing\TdabcCostingService;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas de integridad del costeo: solo se costean y contabilizan
 * cirugías realizadas y terminadas, con las tarifas congeladas al
 * momento del registro.
 */
class ReglasCosteoTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospital;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create([
            'horas_dia' => 12,
            'dias_mes' => 26,
            'factor_indirecto' => 0,
        ]);
        $this->admin = User::factory()->create(['hospital_id' => $this->hospital->id]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_una_cirugia_sin_estado_explicito_se_crea_en_proceso(): void
    {
        HospitalContext::set($this->hospital->id);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
        HospitalContext::clear();

        $respuesta = $this->actingAs($this->admin)->postJson('/api/v1/cirugias', [
            'paciente_id' => $paciente->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'tipo' => 'programada',
            'procedimientos' => [['id' => $procedimiento->id]],
        ])->assertCreated();

        $this->assertSame('en_proceso', $respuesta->json('estado'));
    }

    public function test_no_se_costean_cirugias_que_no_esten_realizadas(): void
    {
        foreach (['programada', 'en_proceso', 'cancelada'] as $estado) {
            $cirugia = $this->crearCirugia(estado: $estado);

            // Ruta web: vuelve con toast de error y no crea el costo
            $this->actingAs($this->admin)
                ->post("/cirugias/{$cirugia->id}/calcular-costo")
                ->assertRedirect();

            // API: 422 explícito
            $this->actingAs($this->admin)
                ->postJson("/api/v1/cirugias/{$cirugia->id}/calcular-costo")
                ->assertUnprocessable();

            $this->assertSame(0, $cirugia->costo()->count(), "No debía costearse una cirugía «{$estado}»");
        }
    }

    public function test_los_kpis_excluyen_cirugias_no_contabilizables(): void
    {
        // Solo esta debe contar: realizada, terminada y costeada
        $contabilizable = $this->crearCirugia(estado: 'realizada');
        $this->costear($contabilizable, 100_000);

        // Costeada pero en proceso: fuera de los indicadores
        $enProceso = $this->crearCirugia(estado: 'en_proceso');
        $this->costear($enProceso, 900_000);

        // Realizada pero sin hora de fin: fuera de los indicadores
        $sinTerminar = $this->crearCirugia(estado: 'realizada', conHoraFin: false);
        $this->costear($sinTerminar, 900_000);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/kpis/costos')
            ->assertOk()
            ->assertJsonPath('global.n_cirugias_costeadas', 1)
            ->assertJsonPath('global.costo_promedio', 100000);

        $outliers = $this->actingAs($this->admin)
            ->getJson('/api/v1/kpis/outliers')
            ->assertOk()
            ->json('grupos.0');
        $this->assertSame(1, $outliers['n']);
    }

    public function test_el_margen_solo_compara_cirugias_facturadas(): void
    {
        $facturada = $this->crearCirugia(estado: 'realizada');
        $this->costear($facturada, 100_000);
        Facturacion::factory()->create([
            'cirugia_id' => $facturada->id,
            'hospital_id' => $this->hospital->id,
            'valor_facturado' => 150_000,
        ]);

        // Costeada pero sin factura: no debe entrar a la comparación
        $sinFactura = $this->crearCirugia(estado: 'realizada');
        $this->costear($sinFactura, 900_000);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/kpis/margen')
            ->assertOk()
            ->assertJsonPath('por_procedimiento.0.n', 1)
            ->assertJsonPath('por_procedimiento.0.costo_promedio', 100000)
            ->assertJsonPath('por_procedimiento.0.facturado_promedio', 150000)
            ->assertJsonPath('por_procedimiento.0.margen_vs_facturado', 50000);
    }

    public function test_recalcular_usa_las_tarifas_congeladas_al_registrar(): void
    {
        HospitalContext::set($this->hospital->id);

        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
        $sala = SalaOperatoria::factory()->create(['hospital_id' => $this->hospital->id, 'costo_hora' => 40_000]);
        $recurso = RecursoHumano::factory()->create([
            'hospital_id' => $this->hospital->id,
            'rol' => 'cirujano',
            'salario_mensual' => 10_000_000,
            'prestaciones_mensuales' => 4_600_000,
            'costos_indirectos_mensuales' => 1_000_000, // $50.000/hora
        ]);
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospital->id, 'costo_unitario' => 10_000]);
        $equipoMedico = EquipoMedico::factory()->create(['hospital_id' => $this->hospital->id, 'costo_hora' => 30_000]);

        $cirugia = app(RegistrarCirugia::class)->ejecutar([
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => $sala->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => '2026-06-10 10:00:00', // 2 h de sala
            'tipo' => 'programada',
            'estado' => 'realizada',
            'procedimientos' => [['id' => $procedimiento->id, 'es_principal' => true]],
            'equipo' => [['recurso_humano_id' => $recurso->id, 'rol' => 'cirujano', 'fase' => 'quirurgica', 'minutos_participacion' => 120]],
            'consumos' => [['insumo_id' => $insumo->id, 'fase' => 'quirurgica', 'cantidad' => 2]],
            'equipos_medicos' => [['id' => $equipoMedico->id, 'minutos_uso' => 60]],
        ]);

        $motor = app(TdabcCostingService::class);

        // cirujano 100.000 + sala 80.000 + equipo 30.000 + insumos 20.000 = 230.000
        $primero = $motor->calcular($cirugia);
        $this->assertEqualsWithDelta(230_000.0, (float) $primero->costo_total, 0.01);

        // Cambian TODOS los parámetros de Capa 1 después del registro…
        $recurso->update(['salario_mensual' => 90_000_000]);
        $sala->update(['costo_hora' => 999_999]);
        $equipoMedico->update(['costo_hora' => 888_888]);
        $this->hospital->update(['factor_indirecto' => 0.5, 'horas_dia' => 6]);

        // …y el recálculo conserva las tarifas congeladas del registro.
        $segundo = $motor->calcular($cirugia->fresh());
        $this->assertEqualsWithDelta(230_000.0, (float) $segundo->costo_total, 0.01);
    }

    public function test_el_hash_del_documento_usa_la_clave_de_la_aplicacion(): void
    {
        $hash = Paciente::hashDocumento('1122334455');

        $this->assertSame(hash_hmac('sha256', '1122334455', (string) config('app.key')), $hash);
        // Un SHA-256 sin clave sería reversible por fuerza bruta (cédulas cortas)
        $this->assertNotSame(hash('sha256', '1122334455'), $hash);
    }

    public function test_rechaza_hora_inicio_en_un_dia_distinto_a_la_fecha(): void
    {
        HospitalContext::set($this->hospital->id);
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
        HospitalContext::clear();

        $this->actingAs($this->admin)->postJson('/api/v1/cirugias', [
            'paciente_id' => $paciente->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '2026-06-11 08:00:00', // día distinto a la fecha
            'tipo' => 'programada',
            'procedimientos' => [['id' => $procedimiento->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('hora_inicio');
    }

    public function test_eliminar_parametros_exige_hospital_activo(): void
    {
        HospitalContext::set($this->hospital->id);
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospital->id]);
        HospitalContext::clear();

        // Un super_admin sin hospital seleccionado no puede eliminar
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->delete("/parametros/insumos/{$insumo->id}")
            ->assertForbidden();

        $this->assertNotNull($insumo->fresh());
    }

    protected function crearCirugia(string $estado, bool $conHoraFin = true): Cirugia
    {
        HospitalContext::set($this->hospital->id);

        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => null,
            'fecha' => '2026-06-15',
            'hora_inicio' => '2026-06-15 08:00:00',
            'hora_fin' => $conHoraFin ? '2026-06-15 10:00:00' : null,
            'estado' => $estado,
        ]);
        $cirugia->procedimientos()->attach(
            $this->procedimientoCompartido()->id,
            ['es_principal' => true],
        );

        HospitalContext::clear();

        return $cirugia;
    }

    protected function costear(Cirugia $cirugia, int $costoTotal): void
    {
        CostoCirugia::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $this->hospital->id,
            'costo_total' => $costoTotal,
        ]);
    }

    protected function procedimientoCompartido(): ProcedimientoQuirurgico
    {
        return ProcedimientoQuirurgico::withoutGlobalScopes()
            ->firstWhere('hospital_id', $this->hospital->id)
            ?? ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
    }
}
