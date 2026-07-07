<?php

namespace Database\Factories;

use App\Enums\EstadoCirugia;
use App\Enums\TipoCirugia;
use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\SalaOperatoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Cirugia>
 */
class CirugiaFactory extends Factory
{
    protected $model = Cirugia::class;

    public function definition(): array
    {
        $inicio = Carbon::instance(fake()->dateTimeBetween('-3 months', '-1 week'))
            ->setTime(fake()->numberBetween(7, 16), fake()->randomElement([0, 30]));
        $duracion = fake()->numberBetween(45, 240);

        return [
            'hospital_id' => Hospital::factory(),
            'paciente_id' => Paciente::factory(),
            'sala_operatoria_id' => SalaOperatoria::factory(),
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio,
            'hora_fin' => $inicio->copy()->addMinutes($duracion),
            'tipo' => fake()->randomElement(TipoCirugia::values()),
            'estado' => EstadoCirugia::Realizada->value,
            'diagnostico_cie10' => fake()->randomElement(['O82', 'K35.8', 'K80.2', 'K40.9', 'N20.0']),
            'observaciones' => null,
        ];
    }
}
