<?php

namespace Tests\Unit;

use App\Enums\EstadoCirugia;
use App\Enums\FaseCiclo;
use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\Paciente;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Services\Plantillas\GeneradorPlantilla;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La plantilla deducida del histórico: es lo que evita que un hospital tenga
 * que escribir sus protocolos de cero para empezar a usar la aplicación.
 */
class GeneradorPlantillaTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private ProcedimientoQuirurgico $procedimiento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospital = Hospital::factory()->create();
        HospitalContext::set($this->hospital->id);

        $this->procedimiento = ProcedimientoQuirurgico::factory()->create(['hospital_id' => $this->hospital->id]);
    }

    public function test_sin_historico_suficiente_no_propone_nada(): void
    {
        $this->crearCirugiaCon(insumos: [], personal: []);
        $this->crearCirugiaCon(insumos: [], personal: []);

        $generador = new GeneradorPlantilla;

        // Dos cirugías: por debajo del mínimo, la moda no significa nada.
        $this->assertNull($generador->proponer($this->procedimiento));
        $this->assertSame(0, $generador->generar($this->procedimiento));
    }

    public function test_lo_que_aparece_en_la_mayoria_entra_como_estandar(): void
    {
        $gasas = Insumo::factory()->create(['hospital_id' => $this->hospital->id]);
        $clip = Insumo::factory()->create(['hospital_id' => $this->hospital->id]);

        // Las gasas van en las cuatro; el clip, en una sola.
        foreach (range(1, 4) as $i) {
            $this->crearCirugiaCon(
                insumos: $i === 1
                    ? [[$gasas->id, 3], [$clip->id, 1]]
                    : [[$gasas->id, 3]],
                personal: [],
            );
        }

        $propuesta = (new GeneradorPlantilla)->proponer($this->procedimiento);

        $this->assertNotNull($propuesta);
        $this->assertSame(4, $propuesta['n_cirugias']);

        // Solo las gasas: el clip aparece en el 25 %, por debajo del umbral
        // de lo estándar y del de lo opcional (que es "al menos" 25 %)…
        $insumos = collect($propuesta['insumos'])->keyBy('insumo_id');

        $this->assertTrue($insumos->has($gasas->id));
        $this->assertFalse($insumos[$gasas->id]['opcional']);
        $this->assertEquals(3.0, $insumos[$gasas->id]['cantidad']);

        // …el clip entra, pero marcado como opcional.
        $this->assertTrue($insumos->has($clip->id));
        $this->assertTrue($insumos[$clip->id]['opcional']);
    }

    public function test_la_cantidad_es_la_habitual_no_el_promedio(): void
    {
        $sutura = Insumo::factory()->create(['hospital_id' => $this->hospital->id]);

        // Tres cirugías con 2 suturas y una atípica con 20: el promedio daría
        // 6,5, un número que nadie usó nunca.
        foreach ([2, 2, 2, 20] as $cantidad) {
            $this->crearCirugiaCon(
                insumos: [[$sutura->id, $cantidad]],
                personal: [],
            );
        }

        $propuesta = (new GeneradorPlantilla)->proponer($this->procedimiento);

        $this->assertNotNull($propuesta);
        $this->assertEquals(2.0, $propuesta['insumos'][0]['cantidad']);
    }

    public function test_fija_la_persona_solo_si_siempre_es_la_misma(): void
    {
        $fijo = RecursoHumano::factory()->create(['hospital_id' => $this->hospital->id, 'rol' => 'instrumentador']);
        $rotativo1 = RecursoHumano::factory()->create(['hospital_id' => $this->hospital->id, 'rol' => 'cirujano']);
        $rotativo2 = RecursoHumano::factory()->create(['hospital_id' => $this->hospital->id, 'rol' => 'cirujano']);

        foreach ([$rotativo1, $rotativo2, $rotativo1] as $cirujano) {
            $this->crearCirugiaCon(
                insumos: [],
                personal: [
                    [$fijo->id, 'instrumentador'],
                    [$cirujano->id, 'cirujano'],
                ],
            );
        }

        $propuesta = (new GeneradorPlantilla)->proponer($this->procedimiento);

        $this->assertNotNull($propuesta);

        $porRol = collect($propuesta['personal'])->keyBy('rol');

        // El instrumentador siempre fue el mismo: se deja fijado.
        $this->assertSame($fijo->id, $porRol['instrumentador']['recurso_humano_id']);

        // El cirujano lo define el turno: se pide el rol, no la persona.
        $this->assertNull($porRol['cirujano']['recurso_humano_id']);
        $this->assertSame(1, $porRol['cirujano']['cantidad']);
    }

    public function test_generar_reemplaza_la_plantilla_anterior(): void
    {
        $insumo = Insumo::factory()->create(['hospital_id' => $this->hospital->id]);

        foreach (range(1, 3) as $i) {
            $this->crearCirugiaCon(insumos: [[$insumo->id, 1]], personal: []);
        }

        $this->procedimiento->plantillaInsumos()->create([
            'insumo_id' => $insumo->id,
            'fase' => FaseCiclo::Postquirurgica->value,
            'cantidad' => 99,
            'opcional' => false,
        ]);

        $generador = new GeneradorPlantilla;

        $this->assertGreaterThan(0, $generador->generar($this->procedimiento));

        $filas = $this->procedimiento->plantillaInsumos()->get();

        $this->assertCount(1, $filas);
        $this->assertSame(FaseCiclo::Quirurgica, $filas->first()->fase);
        $this->assertEquals(1.0, $filas->first()->cantidad);
    }

    /**
     * @param  list<array{0: int, 1: float|int}>  $insumos  [insumo_id, cantidad]
     * @param  list<array{0: int, 1: string}>  $personal  [recurso_humano_id, rol]
     */
    private function crearCirugiaCon(array $insumos, array $personal): Cirugia
    {
        $paciente = Paciente::factory()->create(['hospital_id' => $this->hospital->id]);

        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $this->hospital->id,
            'paciente_id' => $paciente->id,
            'fecha' => '2026-06-10',
            'estado' => EstadoCirugia::Realizada->value,
            'hora_inicio' => '2026-06-10 08:00:00',
            'hora_fin' => '2026-06-10 10:00:00',
        ]);

        $cirugia->procedimientos()->attach($this->procedimiento->id, [
            'es_principal' => true,
        ]);

        foreach ($insumos as [$insumoId, $cantidad]) {
            $cirugia->consumos()->create([
                'hospital_id' => $this->hospital->id,
                'insumo_id' => $insumoId,
                'fase' => FaseCiclo::Quirurgica->value,
                'cantidad' => $cantidad,
                'costo_unitario_registrado' => 1000,
                'costo_total' => 1000 * $cantidad,
            ]);
        }

        foreach ($personal as [$recursoId, $rol]) {
            $cirugia->equipoQuirurgico()->create([
                'hospital_id' => $this->hospital->id,
                'recurso_humano_id' => $recursoId,
                'rol' => $rol,
                'fase' => FaseCiclo::Quirurgica->value,
                'minutos_participacion' => 120,
                'costo_mensual_registrado' => 5_000_000,
            ]);
        }

        return $cirugia;
    }
}
