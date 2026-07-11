<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configuración de capacidad del hospital para el modelo TDABC:
 * horas por día, días por mes y factor de costos indirectos.
 */
class UpdateHospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'horas_dia' => ['required', 'integer', 'min:1', 'max:24'],
            'dias_mes' => ['required', 'integer', 'min:1', 'max:31'],
            'factor_indirecto' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'horas_dia.max' => 'Las horas por día no pueden superar 24.',
            'dias_mes.max' => 'Los días por mes no pueden superar 31.',
            'factor_indirecto.max' => 'El factor indirecto se expresa como proporción entre 0 y 1 (p. ej. 0.12).',
        ];
    }
}
