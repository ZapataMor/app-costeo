<?php

namespace App\Http\Requests;

use App\Enums\Regimen;
use App\Models\Paciente;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
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
                // guarda cifrado, Ley 1581/2012), ignorando al propio paciente.
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var Paciente|null $paciente */
                    $paciente = $this->route('paciente');

                    $existe = Paciente::query()
                        ->where('documento_hash', Paciente::hashDocumento(trim((string) $value)))
                        ->when($paciente !== null, fn ($query) => $query->whereKeyNot($paciente->getKey()))
                        ->exists();

                    if ($existe) {
                        $fail('Ya existe otro paciente registrado con este documento.');
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
