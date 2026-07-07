<?php

namespace Database\Factories;

use App\Enums\RolQuirurgico;
use App\Models\Hospital;
use App\Models\RecursoHumano;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecursoHumano>
 */
class RecursoHumanoFactory extends Factory
{
    protected $model = RecursoHumano::class;

    public function definition(): array
    {
        $salario = fake()->numberBetween(3000, 12000) * 1000;

        return [
            'hospital_id' => Hospital::factory(),
            'nombre' => fake()->name(),
            'rol' => fake()->randomElement(RolQuirurgico::values()),
            'especialidad' => null,
            'salario_mensual' => $salario,
            'prestaciones_mensuales' => (int) round($salario * 0.45),
            'costos_indirectos_mensuales' => fake()->numberBetween(200, 1000) * 1000,
            'activo' => true,
        ];
    }
}
