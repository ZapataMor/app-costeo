<?php

namespace Database\Seeders;

use App\Models\Hospital;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;

/**
 * Hospitales (tenants) de la instalación.
 *
 * Idempotente: la clave es el NIT, que ya tiene índice único. Volver a
 * ejecutarlo actualiza los parámetros del hospital en vez de duplicarlo.
 *
 * Para una instalación real, reemplaza el contenido de HOSPITALES por los
 * datos verdaderos antes de ejecutar. `factor_indirecto` en 0 significa que
 * los costos indirectos ya vienen asignados dentro de cada recurso humano.
 */
class HospitalSeeder extends Seeder
{
    use InformaEnConsola;

    /**
     * @var list<array<string, mixed>>
     */
    public const HOSPITALES = [
        [
            'nit' => '800100200-1',
            'nombre' => 'Hospital San Rafael de Maicao',
            'nivel_complejidad' => 'mediana',
            'municipio' => 'Maicao',
            'departamento' => 'La Guajira',
            'horas_dia' => 12,
            'dias_mes' => 26,
            'factor_indirecto' => 0,
        ],
        [
            'nit' => '800300400-2',
            'nombre' => 'Hospital Nuestra Señora de Riohacha',
            'nivel_complejidad' => 'mediana',
            'municipio' => 'Riohacha',
            'departamento' => 'La Guajira',
            'horas_dia' => 12,
            'dias_mes' => 26,
            'factor_indirecto' => 0.12,
        ],
    ];

    public function run(): void
    {
        foreach (self::HOSPITALES as $datos) {
            Hospital::updateOrCreate(['nit' => $datos['nit']], $datos);
        }

        $this->informar('Hospitales sembrados: '.count(self::HOSPITALES));
    }
}
