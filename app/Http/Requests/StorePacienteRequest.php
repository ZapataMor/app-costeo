<?php

namespace App\Http\Requests;

use App\Enums\Regimen;
use App\Models\Paciente;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(['CC', 'TI', 'RC', 'CE', 'PA', 'PT'])],
            'documento' => [
                'required',
                'string',
                'max:20',
                // Unicidad por hospital comparando el hash (el documento se
                // guarda cifrado, Ley 1581/2012, y no es consultable directo)
                function (string $attribute, mixed $value, Closure $fail): void {
                    $existe = Paciente::query()
                        ->where('documento_hash', Paciente::hashDocumento(trim((string) $value)))
                        ->exists();

                    if ($existe) {
                        $fail('Ya existe un paciente registrado con este documento.');
                    }
                },
            ],
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
            'sexo' => ['nullable', Rule::in(['M', 'F', 'O'])],
            'regimen' => ['required', Rule::in(Regimen::values())],
            'asegurador' => ['nullable', 'string', 'max:120'],
            'zona' => ['required', Rule::in(['urbana', 'rural'])],
            'municipio' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('documento')) {
            $this->merge(['documento' => trim((string) $this->input('documento'))]);
        }
    }
}
