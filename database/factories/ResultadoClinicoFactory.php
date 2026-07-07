<?php

namespace Database\Factories;

use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\ResultadoClinico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultadoClinico>
 */
class ResultadoClinicoFactory extends Factory
{
    protected $model = ResultadoClinico::class;

    public function definition(): array
    {
        $complicacionPost = fake()->boolean(10);

        return [
            'cirugia_id' => Cirugia::factory(),
            'hospital_id' => Hospital::factory(),
            'complicacion_intraoperatoria' => fake()->boolean(5),
            'descripcion_complicacion_intra' => null,
            'complicacion_postoperatoria' => $complicacionPost,
            'descripcion_complicacion_post' => $complicacionPost ? 'Infección del sitio operatorio [SEMILLA]' : null,
            'dias_estancia' => fake()->numberBetween(1, $complicacionPost ? 12 : 5),
            'reingreso_30_dias' => fake()->boolean(5),
            'mortalidad' => fake()->boolean(1),
        ];
    }
}
