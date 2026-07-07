<?php

namespace Database\Factories;

use App\Enums\CategoriaInsumo;
use App\Models\Hospital;
use App\Models\Insumo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Insumo>
 */
class InsumoFactory extends Factory
{
    protected $model = Insumo::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'codigo' => fake()->unique()->bothify('INS-#####'),
            'nombre' => 'Insumo de ejemplo '.fake()->unique()->word(),
            'categoria' => fake()->randomElement([CategoriaInsumo::Dispositivo->value, CategoriaInsumo::Material->value]),
            'codigo_atc' => null,
            'unidad' => fake()->randomElement(['unidad', 'ampolla', 'paquete', 'metro']),
            'costo_unitario' => fake()->numberBetween(1, 150) * 1000,
            'activo' => true,
        ];
    }

    public function medicamento(): static
    {
        return $this->state(fn (): array => [
            'categoria' => CategoriaInsumo::Medicamento->value,
            'codigo_atc' => fake()->randomElement(['J01CA04', 'N01BB01', 'H01BB02', 'N02BE01', 'B05BA03']),
            'unidad' => 'ampolla',
        ]);
    }
}
