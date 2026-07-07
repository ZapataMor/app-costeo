<?php

namespace Database\Factories;

use App\Enums\Regimen;
use App\Models\Hospital;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paciente>
 */
class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'tipo_documento' => 'CC',
            'documento' => fake()->unique()->numerify('##########'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-75 years', '-16 years')->format('Y-m-d'),
            'sexo' => fake()->randomElement(['M', 'F']),
            'regimen' => fake()->randomElement(Regimen::values()),
            'asegurador' => fake()->randomElement(['EPS Familiar', 'Nueva EPS', 'Cajacopi', 'Anas Wayuu', 'Sanitas']),
            'zona' => fake()->randomElement(['urbana', 'rural']),
            'municipio' => fake()->randomElement(['Riohacha', 'Maicao', 'Uribia', 'Manaure', 'Fonseca']),
        ];
    }
}
