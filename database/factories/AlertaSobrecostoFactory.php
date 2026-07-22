<?php

namespace Database\Factories;

use App\Enums\ComponenteCosto;
use App\Enums\EstadoAlerta;
use App\Models\AlertaSobrecosto;
use App\Models\Cirugia;
use App\Models\Hospital;
use App\Models\ProcedimientoQuirurgico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertaSobrecosto>
 */
class AlertaSobrecostoFactory extends Factory
{
    protected $model = AlertaSobrecosto::class;

    public function definition(): array
    {
        $esperado = fake()->numberBetween(800, 2000) * 1000;
        $exceso = $esperado * fake()->randomFloat(2, 0.4, 2.0);

        return [
            'hospital_id' => Hospital::factory(),
            'cirugia_id' => Cirugia::factory(),
            'procedimiento_quirurgico_id' => ProcedimientoQuirurgico::factory(),
            'costo_total' => $esperado + $exceso,
            'costo_esperado' => $esperado,
            'exceso' => $exceso,
            'exceso_pct' => round($exceso / $esperado, 4),
            'z' => fake()->randomFloat(3, 3.1, 6),
            'criterios' => ['z', 'iqr'],
            'n_baseline' => fake()->numberBetween(5, 40),
            'atribucion' => [
                [
                    'componente' => ComponenteCosto::Insumos->value,
                    'etiqueta' => ComponenteCosto::Insumos->etiqueta(),
                    'costo' => $esperado + $exceso,
                    'esperado' => $esperado,
                    'exceso' => $exceso,
                    'aporte_pct' => 1.0,
                ],
            ],
            'componente_dominante' => ComponenteCosto::Insumos->value,
            'estado' => EstadoAlerta::Pendiente->value,
            'detectado_en' => now(),
        ];
    }
}
