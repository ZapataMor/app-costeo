<?php

namespace Database\Factories;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Models\ConceptoCostoIndirecto;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConceptoCostoIndirecto>
 */
class ConceptoCostoIndirectoFactory extends Factory
{
    protected $model = ConceptoCostoIndirecto::class;

    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'nombre' => 'Energía eléctrica',
            'categoria' => CategoriaCif::Infraestructura->value,
            'base_asignacion' => BaseAsignacionCif::MinutoQuirofano->value,
            'monto_mensual' => 20_000_000,
            'porcentaje' => null,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            // Nace apagado: solo ActivarCategoriaCif lo enciende.
            'activo' => false,
        ];
    }

    /** Bolsa que se reparte como porcentaje del costo directo. */
    public function porcentajeDirecto(float $porcentaje = 0.05): static
    {
        return $this->state(fn (): array => [
            'base_asignacion' => BaseAsignacionCif::PorcentajeDirecto->value,
            'monto_mensual' => null,
            'porcentaje' => $porcentaje,
        ]);
    }

    public function categoria(CategoriaCif $categoria): static
    {
        return $this->state(fn (): array => ['categoria' => $categoria->value]);
    }
}
