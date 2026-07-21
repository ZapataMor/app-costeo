<?php

namespace App\Http\Requests\Concerns;

use App\Enums\NivelConfiabilidad;
use Illuminate\Validation\Rule;

/**
 * Reglas de los campos de trazabilidad académica de los parámetros
 * (fuente + nivel_confiabilidad). Si el nivel no se envía, la base de
 * datos aplica el default 'estimado'.
 *
 * @see NivelConfiabilidad
 */
trait ReglasTrazabilidad
{
    /** @return array<string, mixed> */
    protected function reglasTrazabilidad(): array
    {
        return [
            'fuente' => ['nullable', 'string', 'max:255'],
            'nivel_confiabilidad' => ['sometimes', 'required', Rule::in(NivelConfiabilidad::values())],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesTrazabilidad(): array
    {
        return [
            'nivel_confiabilidad.in' => 'El nivel de confiabilidad debe ser: medido, estimado o supuesto.',
            'nivel_confiabilidad.required' => 'Indica el nivel de confiabilidad del dato.',
        ];
    }
}
