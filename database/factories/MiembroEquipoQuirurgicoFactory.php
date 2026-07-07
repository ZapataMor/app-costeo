<?php

namespace Database\Factories;

use App\Enums\RolQuirurgico;
use App\Models\Cirugia;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\RecursoHumano;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MiembroEquipoQuirurgico>
 */
class MiembroEquipoQuirurgicoFactory extends Factory
{
    protected $model = MiembroEquipoQuirurgico::class;

    public function definition(): array
    {
        return [
            'cirugia_id' => Cirugia::factory(),
            'recurso_humano_id' => RecursoHumano::factory(),
            'rol' => fake()->randomElement(RolQuirurgico::values()),
            'minutos_participacion' => fake()->numberBetween(30, 180),
        ];
    }
}
