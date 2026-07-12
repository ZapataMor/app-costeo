<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardPorRolTest extends TestCase
{
    use RefreshDatabase;

    protected Hospital $hospitalA;

    protected Hospital $hospitalB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospitalA = $this->crearHospitalConCirugia(costoTotal: 100_000);
        $this->hospitalB = $this->crearHospitalConCirugia(costoTotal: 900_000);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    public function test_admin_hospital_ve_solo_los_indicadores_de_su_hospital(): void
    {
        $admin = User::factory()->create(['hospital_id' => $this->hospitalA->id]);

        $this->actingAs($admin)
            ->get('/costeo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/index')
                ->where('costos.global.n_cirugias_costeadas', 1)
                ->where('costos.global.costo_promedio', 100000));
    }

    public function test_super_admin_sin_hospital_ve_el_dashboard_consolidado(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get('/costeo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/index')
                ->where('costos.global.n_cirugias_costeadas', 2)
                ->where('costos.global.costo_promedio', 500000));
    }

    public function test_super_admin_filtra_el_dashboard_con_el_switcher(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $this->post('/hospital-activo', ['hospital_id' => $this->hospitalB->id])->assertRedirect();

        $this->get('/costeo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('costeo/index')
                ->where('costos.global.n_cirugias_costeadas', 1)
                ->where('costos.global.costo_promedio', 900000));
    }

    public function test_todas_las_paginas_de_costeo_cargan_para_el_super_admin_consolidado(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        foreach (['/costeo', '/costeo/componentes', '/costeo/outliers', '/costeo/rentabilidad', '/costeo/variabilidad'] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    protected function crearHospitalConCirugia(int $costoTotal): Hospital
    {
        $hospital = Hospital::factory()->create();
        HospitalContext::set($hospital->id);

        $paciente = Paciente::factory()->create(['hospital_id' => $hospital->id]);
        $cirugia = Cirugia::factory()->create([
            'hospital_id' => $hospital->id,
            'paciente_id' => $paciente->id,
            'sala_operatoria_id' => null,
        ]);
        CostoCirugia::factory()->create([
            'cirugia_id' => $cirugia->id,
            'hospital_id' => $hospital->id,
            'costo_total' => $costoTotal,
        ]);

        HospitalContext::clear();

        return $hospital;
    }
}
