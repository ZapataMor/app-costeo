<?php

namespace Database\Factories;

use App\Models\Cirugia;
use App\Models\ConsumoInsumo;
use App\Models\Insumo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsumoInsumo>
 */
class ConsumoInsumoFactory extends Factory
{
    protected $model = ConsumoInsumo::class;

    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 10);
        $costoUnitario = fake()->numberBetween(1, 100) * 1000;

        return [
            'cirugia_id' => Cirugia::factory(),
            'insumo_id' => Insumo::factory(),
            'cantidad' => $cantidad,
            'costo_unitario_registrado' => $costoUnitario,
            'costo_total' => $cantidad * $costoUnitario,
        ];
    }
}
