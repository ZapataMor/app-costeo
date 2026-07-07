<?php

namespace Database\Factories;

use App\Models\EquipoMedico;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipoMedico>
 */
class EquipoMedicoFactory extends Factory
{
    protected $model = EquipoMedico::class;

    public function definition(): array
    {
        $valor = fake()->numberBetween(20000, 300000) * 1000;

        return [
            'hospital_id' => Hospital::factory(),
            'nombre' => fake()->randomElement(['Electrobisturí', 'Laparoscopio', 'Monitor de signos', 'Máquina de anestesia']).' '.fake()->unique()->numerify('##'),
            'codigo' => fake()->unique()->bothify('EQ-####'),
            'valor_adquisicion' => $valor,
            'vida_util_anios' => fake()->numberBetween(5, 10),
            'costo_hora' => fake()->numberBetween(5, 60) * 1000,
            'activo' => true,
        ];
    }
}
