<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\SalaOperatoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaOperatoria>
 */
class SalaOperatoriaFactory extends Factory
{
    protected $model = SalaOperatoria::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'nombre' => 'Sala '.fake()->unique()->numberBetween(1, 99),
            'ubicacion' => 'Piso '.fake()->numberBetween(1, 3),
            'costo_hora' => fake()->numberBetween(30, 60) * 1000,
            'equipamiento' => ['lámpara cielítica', 'mesa quirúrgica', 'monitor'],
            'activa' => true,
        ];
    }
}
