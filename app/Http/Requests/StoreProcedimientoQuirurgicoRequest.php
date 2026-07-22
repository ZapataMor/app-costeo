<?php

namespace App\Http\Requests;

use App\Enums\NivelComplejidad;
use App\Http\Requests\Concerns\ReglasTrazabilidad;
use App\Support\HospitalContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcedimientoQuirurgicoRequest extends FormRequest
{
    use ReglasTrazabilidad;

    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->reglasTrazabilidad(),
            // CUPS: código de 6 dígitos (Resolución 2238 de 2020)
            'codigo_cups' => [
                'required',
                'regex:/^\d{6}$/',
                Rule::unique('procedimientos_quirurgicos', 'codigo_cups')
                    ->where('hospital_id', HospitalContext::id()),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'especialidad' => ['required', 'string', 'max:120'],
            'complejidad' => ['required', Rule::in(NivelComplejidad::values())],
            // Tiempo de sala: el único obligatorio, porque es el que se costea.
            'duracion_estimada_minutos' => ['required', 'integer', 'min:1', 'max:1440'],
            // Fases restantes del ciclo: opcionales mientras el hospital las
            // levanta. Admiten 0 (un ambulatorio puede no tener recambio).
            'minutos_prequirurgico' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'minutos_recuperacion' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'minutos_recambio' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'tarifa_soat' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->mensajesTrazabilidad(),
            'codigo_cups.regex' => 'El código CUPS debe tener 6 dígitos.',
        ];
    }
}
