<?php

namespace Database\Factories;

use App\Models\Cirugia;
use App\Models\Facturacion;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facturacion>
 */
class FacturacionFactory extends Factory
{
    protected $model = Facturacion::class;

    public function definition(): array
    {
        $facturado = fake()->numberBetween(400, 2500) * 1000;
        $glosado = fake()->boolean(20) ? (int) round($facturado * fake()->randomFloat(2, 0.05, 0.2)) : 0;
        $recaudado = (int) round(($facturado - $glosado) * fake()->randomFloat(2, 0.8, 1.0));

        return [
            'cirugia_id' => Cirugia::factory(),
            'hospital_id' => Hospital::factory(),
            'valor_facturado' => $facturado,
            'valor_glosado' => $glosado,
            'valor_recaudado' => $recaudado,
            'tarifa_referencia_soat' => null,
            'fecha_facturacion' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
        ];
    }
}
