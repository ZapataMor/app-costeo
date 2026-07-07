<?php

namespace Database\Factories;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostoCirugia>
 */
class CostoCirugiaFactory extends Factory
{
    protected $model = CostoCirugia::class;

    public function definition(): array
    {
        $recursoHumano = fake()->numberBetween(150, 400) * 1000;
        $sala = fake()->numberBetween(40, 160) * 1000;
        $equipos = fake()->numberBetween(0, 80) * 1000;
        $insumos = fake()->numberBetween(80, 400) * 1000;
        $directo = $recursoHumano + $sala + $equipos + $insumos;

        return [
            'cirugia_id' => Cirugia::factory(),
            'hospital_id' => Hospital::factory(),
            'costo_recurso_humano' => $recursoHumano,
            'costo_sala' => $sala,
            'costo_equipos' => $equipos,
            'costo_insumos' => $insumos,
            'costo_directo' => $directo,
            'costo_indirecto' => 0,
            'costo_total' => $directo,
            'detalle' => null,
            'calculado_en' => now(),
        ];
    }
}
