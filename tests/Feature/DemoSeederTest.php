<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
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
}
