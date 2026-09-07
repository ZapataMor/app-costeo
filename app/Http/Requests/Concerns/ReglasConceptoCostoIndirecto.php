<?php

namespace App\Http\Requests\Concerns;

use App\Enums\BaseAsignacionCif;
use App\Enums\CategoriaCif;
use App\Rules\SinSolapeDeVigencias;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por el alta y la edición de conceptos CIF.
 *
 * El monto y el porcentaje son excluyentes según la base de asignación:
 * `porcentaje_directo` se configura con un porcentaje y prohíbe el monto;
 * las bases por minuto, al revés. Sin esa exclusión un concepto podía
 * guardarse con los dos campos y el motor tendría que adivinar cuál usar.
 */
trait ReglasConceptoCostoIndirecto
{
    /** @return array<string, mixed> */
    protected function reglasConcepto(?int $ignorarId = null): array
    {
        $esPorcentaje = $this->input('base_asignacion') === BaseAsignacionCif::PorcentajeDirecto->value;

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(CategoriaCif::values())],
            'base_asignacion' => ['required', Rule::in(BaseAsignacionCif::values())],

            'monto_mensual' => $esPorcentaje
                ? ['prohibited']
                : ['required', 'numeric', 'gt:0'],

            'porcentaje' => $esPorcentaje
                ? ['required', 'numeric', 'gt:0', 'max:1']
                : ['prohibited'],

            'vigente_desde' => [
                'required',
                'date',
                new SinSolapeDeVigencias(
                    (string) $this->input('nombre'),
                    // Un concepto se identifica por nombre + categoría: dos
                    // «Mantenimiento» de categorías distintas son dos bolsas.
                    $this->input('categoria'),
                    $this->input('vigente_hasta'),
                    $ignorarId,
                ),
            ],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesConcepto(): array
    {
        return [
            'monto_mensual.required' => 'Indica el monto mensual de la bolsa.',
            'monto_mensual.gt' => 'El monto mensual debe ser mayor que cero.',
            'monto_mensual.prohibited' => 'Una bolsa por porcentaje del costo directo no lleva monto mensual: se calcula sobre cada cirugía.',

            'porcentaje.required' => 'Indica el porcentaje del costo directo.',
            'porcentaje.max' => 'El porcentaje se expresa como proporción entre 0 y 1 (p. ej. 0.12 = 12 %).',
            'porcentaje.prohibited' => 'El porcentaje solo aplica a la base «porcentaje del costo directo»; esta bolsa se reparte por su monto mensual.',

            'vigente_desde.required' => 'Indica desde cuándo rige el concepto.',
            'vigente_hasta.after_or_equal' => 'La vigencia no puede terminar antes de empezar.',
        ];
    }
}
