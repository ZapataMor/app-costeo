<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hospital>
 */
class HospitalFactory extends Factory
{
    protected $model = Hospital::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Hospital '.fake()->company(),
            'nit' => fake()->unique()->numerify('8########-#'),
            'nivel_complejidad' => 'mediana',
            'municipio' => fake()->randomElement(['Riohacha', 'Maicao', 'San Juan del Cesar', 'Fonseca']),
            'departamento' => 'La Guajira',
            'horas_dia' => 12,
            'dias_mes' => 26,
            'factor_indirecto' => 0,
        ];
    }
}
