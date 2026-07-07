<?php

namespace Database\Factories;

use App\Enums\NivelComplejidad;
use App\Models\Hospital;
use App\Models\ProcedimientoQuirurgico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedimientoQuirurgico>
 */
class ProcedimientoQuirurgicoFactory extends Factory
{
    protected $model = ProcedimientoQuirurgico::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'codigo_cups' => fake()->unique()->numerify('######'),
            'nombre' => 'Procedimiento de ejemplo '.fake()->unique()->word(),
            'especialidad' => fake()->randomElement(['Cirugía general', 'Ginecobstetricia', 'Ortopedia', 'Urología']),
            'complejidad' => fake()->randomElement(NivelComplejidad::values()),
            'duracion_estimada_minutos' => fake()->numberBetween(45, 240),
            'tarifa_soat' => fake()->numberBetween(500, 3000) * 1000,
        ];
    }
}
