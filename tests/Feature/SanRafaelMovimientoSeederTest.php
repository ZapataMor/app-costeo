<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\ConceptoCostoIndirecto;
use App\Models\Hospital;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\RecursoHumano;
use App\Support\HospitalContext;
use Database\Seeders\CatalogoQuirurgicoSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\SanRafaelCargaSeeder;
use Database\Seeders\SanRafaelMovimientoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanRafaelMovimientoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    /**
     * Seis días que cruzan el cambio a bolsas CIF del 1 de julio, en vez de
     * los seis meses y medio de la carga completa.
     */
    protected function sembrarMovimiento(): void
    {
        $seeder = new SanRafaelMovimientoSeeder;
        $seeder->desde = '2026-06-28';
        $seeder->hasta = '2026-07-03';
        $seeder->setContainer($this->app)->__invoke();
    }

    protected function sembrar(): Hospital
    {
        $this->seed(HospitalSeeder::class);
        $this->seed(UsuarioSeeder::class);
        $this->seed(CatalogoQuirurgicoSeeder::class);
        $this->seed(SanRafaelCargaSeeder::class);
        $this->sembrarMovimiento();

        $hospital = Hospital::query()->where('nit', SanRafaelCargaSeeder::NIT)->firstOrFail();
        HospitalContext::set($hospital->id);

        return $hospital;
    }

    public function test_registra_y_costea_las_cirugias_del_periodo(): void
    {
        $this->sembrar();

        $cirugias = Cirugia::query()->where('observaciones', 'like', '%[SEMILLA-CARGA]%')->get();

        // ~11 casos/día × 6 días.
        $this->assertGreaterThan(30, $cirugias->count());

        foreach ($cirugias->where('estado', 'realizada') as $cirugia) {
            $this->assertNotNull($cirugia->costo, "Cirugía {$cirugia->id} realizada sin costo.");
            $this->assertNotNull($cirugia->registrado_por);
        }

        // Lo del último día sigue en recuperación y sin costear.
        $ultimoDia = $cirugias->filter(fn (Cirugia $c) => $c->fecha->toDateString() === '2026-07-03' && $c->estado !== 'cancelada');
        $this->assertNotEmpty($ultimoDia);
        $this->assertTrue($ultimoDia->every(fn (Cirugia $c) => $c->estado === 'en_recuperacion' && $c->costo === null));
    }

    public function test_el_cambio_a_bolsas_cif_ocurre_el_primero_de_julio(): void
    {
        $this->sembrar();

        $this->assertSame(12, ConceptoCostoIndirecto::query()->where('activo', true)->count());

        $cirugias = Cirugia::query()->where('observaciones', 'like', '%[SEMILLA-CARGA]%')->get();
        $antes = $cirugias->filter(fn (Cirugia $c) => $c->fecha->lessThan('2026-07-01'));
        $despues = $cirugias->filter(fn (Cirugia $c) => $c->fecha->greaterThanOrEqualTo('2026-07-01'));

        $this->assertNotEmpty($antes);
        $this->assertNotEmpty($despues);
        $this->assertTrue($antes->every(fn (Cirugia $c) => ($c->parametros_cif_registrados['via'] ?? null) === 'factor'));
        $this->assertTrue($despues->every(fn (Cirugia $c) => ($c->parametros_cif_registrados['via'] ?? null) === 'bolsas'));
    }

    public function test_el_cirujano_es_de_la_especialidad_del_procedimiento(): void
    {
        $this->sembrar();

        $miembros = MiembroEquipoQuirurgico::query()
            ->where('rol', 'cirujano')
            ->whereHas('cirugia', fn ($q) => $q->where('observaciones', 'like', '%[SEMILLA-CARGA]%'))
            ->with(['cirugia.procedimientos', 'recursoHumano'])
            ->get();

        $this->assertNotEmpty($miembros);

        foreach ($miembros as $miembro) {
            $this->assertInstanceOf(RecursoHumano::class, $miembro->recursoHumano);
            $this->assertSame(
                $miembro->cirugia->procedimientoPrincipal()?->especialidad,
                $miembro->recursoHumano->especialidad,
            );
        }
    }

    public function test_no_se_siembra_dos_veces(): void
    {
        $this->sembrar();
        $antes = Cirugia::query()->count();

        $this->sembrarMovimiento();

        $this->assertSame($antes, Cirugia::query()->count());
    }
}
