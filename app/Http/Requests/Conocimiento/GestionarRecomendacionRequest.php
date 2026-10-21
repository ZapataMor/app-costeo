<?php

namespace App\Http\Requests\Conocimiento;

use App\Enums\EstadoRecomendacion;
use App\Support\HospitalContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Decisión sobre una recomendación: aceptarla con responsable y fecha,
 * marcarla implementada o descartarla con motivo.
 */
class GestionarRecomendacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->gestionaConocimiento() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $aceptada = EstadoRecomendacion::Aceptada->value;
        $descartada = EstadoRecomendacion::Descartada->value;

        return [
            'estado' => ['required', Rule::in([$aceptada, EstadoRecomendacion::Implementada->value, $descartada])],
            'responsable_id' => [
                'nullable',
                "required_if:estado,{$aceptada}",
                'integer',
                Rule::exists('users', 'id')->where('hospital_id', HospitalContext::id()),
            ],
            'fecha_compromiso' => ['nullable', "required_if:estado,{$aceptada}", 'date'],
            'nota' => ['nullable', "required_if:estado,{$descartada}", 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'responsable_id.required_if' => 'Asigne un responsable: una recomendación aceptada sin dueño no se implementa.',
            'fecha_compromiso.required_if' => 'Indique para cuándo se compromete la implementación.',
            'nota.required_if' => 'Explique por qué se descarta.',
        ];
    }
}
