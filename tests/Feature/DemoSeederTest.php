<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\RecursoHumano;
use App\Models\User;
use App\Support\HospitalContext;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_el_seeder_crea_la_cesarea_de_referencia_de_520_mil(): void
    {
        $this->seed(DemoSeeder::class);

        $referencia = Cirugia::withoutGlobalScopes()
            ->where('observaciones', 'like', '%referencia%')
            ->firstOrFail();

        $costo = CostoCirugia::withoutGlobalScopes()
            ->where('cirugia_id', $referencia->id)
            ->firstOrFail();

        $this->assertEqualsWithDelta(520000.0, (float) $costo->costo_total, 0.01);
    }

    public function test_el_seeder_crea_dos_hospitales_con_datos_separados(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, Hospital::count());

        foreach (Hospital::all() as $hospital) {
            $this->assertGreaterThan(
                0,
                Cirugia::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count(),
                "El hospital {$hospital->nombre} no tiene cirugías sembradas.",
            );
        }
    }

    public function test_el_seeder_crea_un_super_admin_y_un_admin_con_varios_digitadores_por_hospital(): void
    {
        $this->seed(DemoSeeder::class);

        $superAdmin = User::where('email', 'superadmin@demo.test')->firstOrFail();
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertNull($superAdmin->hospital_id);

        foreach (Hospital::all() as $hospital) {
            $admins = User::where('hospital_id', $hospital->id)
                ->where('role', 'admin_hospital')->get();
            $this->assertCount(1, $admins, "El hospital {$hospital->nombre} debe tener exactamente 1 admin.");
            $this->assertTrue($admins->first()->isAdminHospital());

            // Varios digitadores por hospital: es lo que permite distinguir
            // quién registró cada cirugía en el explorador.
            $digitadores = User::where('hospital_id', $hospital->id)
                ->where('role', 'digitador')->get();
            $this->assertGreaterThanOrEqual(
                2,
                $digitadores->count(),
                "El hospital {$hospital->nombre} debe tener al menos 2 digitadores.",
            );
            $this->assertTrue($digitadores->every(fn (User $u): bool => $u->isDigitador()));
        }
    }

    public function test_cada_cirugia_registrada_queda_atribuida_a_un_digitador(): void
    {
        $this->seed(DemoSeeder::class);

        $digitadores = User::where('role', 'digitador')->pluck('id')->all();

        $cirugias = Cirugia::withoutGlobalScopes()
            ->whereNotNull('registrado_por')
            ->get();

        $this->assertGreaterThan(0, $cirugias->count(), 'Ninguna cirugía quedó atribuida.');

        foreach ($cirugias as $cirugia) {
            $this->assertContains($cirugia->registrado_por, $digitadores);
        }
    }

    public function test_el_seeder_deja_cirugias_sin_costear_para_el_kpi_de_completitud(): void
    {
        $this->seed(DemoSeeder::class);

        $sinCostear = Cirugia::withoutGlobalScopes()->doesntHave('costo')->count();

        $this->assertGreaterThan(0, $sinCostear, 'Todas las cirugías quedaron costeadas: el KPI daría 100 % irreal.');
    }

    public function test_los_parametros_sembrados_tienen_trazabilidad(): void
    {
        $this->seed(DemoSeeder::class);

        $recurso = RecursoHumano::withoutGlobalScopes()->firstOrFail();
        $this->assertNotNull($recurso->fuente);
        $this->assertSame('medido', $recurso->nivel_confiabilidad->value);

        $insumo = Insumo::withoutGlobalScopes()->firstOrFail();
        $this->assertNotNull($insumo->fuente);
        $this->assertSame('medido', $insumo->nivel_confiabilidad->value);
    }
}
