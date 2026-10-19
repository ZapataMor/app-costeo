<?php

namespace App\Http\Requests;

use App\Enums\NivelComplejidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de una institución (hospital o centro médico) por el super_admin.
 * Los datos de identificación y la capacidad TDABC viajan juntos: sin la
 * capacidad el hospital no puede costear nada.
 */
class StoreInstitucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:20', Rule::unique('hospitales', 'nit')],
            'nivel_complejidad' => ['required', Rule::in(NivelComplejidad::values())],
            'municipio' => ['nullable', 'string', 'max:255'],
            'departamento' => ['required', 'string', 'max:255'],
            'horas_dia' => ['required', 'integer', 'min:1', 'max:24'],
            'dias_mes' => ['required', 'integer', 'min:1', 'max:31'],
            'minutos_efectivos_hora' => ['required', 'integer', 'min:1', 'max:60'],
            'factor_indirecto' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nit.unique' => 'Ya existe una institución registrada con ese NIT.',
            'horas_dia.max' => 'Las horas por día no pueden superar 24.',
            'dias_mes.max' => 'Los días por mes no pueden superar 31.',
            'minutos_efectivos_hora.max' => 'Los minutos efectivos por hora no pueden superar 60.',
            'factor_indirecto.max' => 'El factor indirecto se expresa como proporción entre 0 y 1 (p. ej. 0.12).',
        ];
    }
}
