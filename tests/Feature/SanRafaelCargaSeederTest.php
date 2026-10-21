<?php

namespace Tests\Feature;

use App\Models\ConceptoCostoIndirecto;
use App\Models\EquipoMedico;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\PlantillaEquipo;
use App\Models\PlantillaInsumo;
use App\Models\PlantillaPersonal;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Support\HospitalContext;
use Database\Seeders\CatalogoQuirurgicoSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\SanRafaelCargaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanRafaelCargaSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    protected function sembrar(): Hospital
    {
        $this->seed(HospitalSeeder::class);
        $this->seed(CatalogoQuirurgicoSeeder::class);
        $this->seed(SanRafaelCargaSeeder::class);

        $hospital = Hospital::query()->where('nit', SanRafaelCargaSeeder::NIT)->firstOrFail();
        HospitalContext::set($hospital->id);

        return $hospital;
    }

    /** @return list<int> */
    protected function conteos(): array
    {
        return [
            SalaOperatoria::count(),
            RecursoHumano::count(),
            Insumo::count(),
            EquipoMedico::count(),
            ProcedimientoQuirurgico::count(),
            ConceptoCostoIndirecto::count(),
            PlantillaInsumo::count(),
            PlantillaEquipo::count(),
            PlantillaPersonal::count(),
        ];
    }

    public function test_carga_un_hospital_de_cinco_salas_con_volumen_proporcionado(): void
    {
        $this->sembrar();

        $this->assertSame(5, SalaOperatoria::count());
        $this->assertSame(62, RecursoHumano::count());
        $this->assertSame(22, RecursoHumano::query()->where('rol', 'cirujano')->count());
        $this->assertSame(8, RecursoHumano::query()->where('rol', 'anestesiologo')->count());
        $this->assertSame(52, ProcedimientoQuirurgico::count());
        $this->assertSame(61, EquipoMedico::count());
        $this->assertGreaterThan(200, Insumo::count());
        $this->assertSame(12, ConceptoCostoIndirecto::count());

        // Un quirófano mayor = una máquina de anestesia: nunca más que salas.
        $this->assertSame(4, EquipoMedico::query()->where('codigo', 'like', 'EQ-ANE-%')->count());
    }

    public function test_es_idempotente_y_no_toca_el_catalogo_semilla(): void
    {
        $this->sembrar();
        $antes = $this->conteos();
        $cirujanaReferencia = RecursoHumano::query()->where('nombre', 'Dra. Carmen Epiayú [SEMILLA]')->firstOrFail();

        $this->seed(SanRafaelCargaSeeder::class);

        $this->assertSame($antes, $this->conteos());
        $this->assertSame('10000000.00', $cirujanaReferencia->fresh()?->salario_mensual);
    }

    public function test_todo_medicamento_trae_atc_valido_y_todo_procedimiento_su_protocolo(): void
    {
        $this->sembrar();

        Insumo::query()->where('categoria', 'medicamento')->each(function (Insumo $insumo) {
            $this->assertMatchesRegularExpression('/^[A-Z]\d{2}[A-Z]{2}\d{2}$/', (string) $insumo->codigo_atc, $insumo->nombre);
        });

        ProcedimientoQuirurgico::query()->each(function (ProcedimientoQuirurgico $procedimiento) {
            $this->assertTrue(
                PlantillaPersonal::query()->where('procedimiento_quirurgico_id', $procedimiento->id)->where('rol', 'cirujano')->exists(),
                "{$procedimiento->nombre} sin cirujano en el protocolo.",
            );
            $this->assertTrue(
                PlantillaInsumo::query()->where('procedimiento_quirurgico_id', $procedimiento->id)->exists(),
                "{$procedimiento->nombre} sin insumos en el protocolo.",
            );
        });
    }

    public function test_las_bolsas_cif_nacen_inactivas_y_el_hospital_sigue_por_la_via_directa(): void
    {
        $hospital = $this->sembrar();

        $this->assertSame(0, ConceptoCostoIndirecto::query()->where('activo', true)->count());
        $this->assertSame('digitado', $hospital->fresh()?->origen_infraestructura->value);

        // La bolsa de depreciación sale del inventario: Σ valor ÷ vida útil ÷ 12.
        $esperada = EquipoMedico::query()->get()
            ->sum(fn (EquipoMedico $e) => (float) $e->valor_adquisicion / (int) $e->vida_util_anios / 12);
        $bolsa = ConceptoCostoIndirecto::query()->where('categoria', 'depreciacion_equipos')
            ->where('nombre', 'like', 'Depreciación%')->firstOrFail();
        $this->assertEqualsWithDelta($esperada, (float) $bolsa->monto_mensual, 500);
    }

    public function test_no_carga_nada_en_otro_hospital(): void
    {
        $this->sembrar();

        $otro = Hospital::query()->where('nit', '!=', SanRafaelCargaSeeder::NIT)->firstOrFail();
        HospitalContext::set($otro->id);

        $this->assertSame(1, SalaOperatoria::count());
        $this->assertSame(0, ConceptoCostoIndirecto::count());
    }
}
