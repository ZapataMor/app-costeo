<?php

namespace App\Http\Requests;

use App\Enums\RolQuirurgico;
use App\Http\Requests\Concerns\ReglasTrazabilidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecursoHumanoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'rol' => ['required', Rule::in(RolQuirurgico::values())],
            'especialidad' => ['nullable', 'string', 'max:120'],
            'salario_mensual' => ['required', 'numeric', 'gt:0'],
            'prestaciones_mensuales' => ['required', 'numeric', 'min:0'],
            'costos_indirectos_mensuales' => ['required', 'numeric', 'min:0'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mensajesTrazabilidad();
    }
}
