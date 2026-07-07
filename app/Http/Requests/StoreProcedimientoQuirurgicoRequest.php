<?php

namespace App\Http\Requests;

use App\Enums\NivelComplejidad;
use App\Support\HospitalContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcedimientoQuirurgicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
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
            'duracion_estimada_minutos' => ['required', 'integer', 'min:1', 'max:1440'],
            'tarifa_soat' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo_cups.regex' => 'El código CUPS debe tener 6 dígitos.',
        ];
    }
}
