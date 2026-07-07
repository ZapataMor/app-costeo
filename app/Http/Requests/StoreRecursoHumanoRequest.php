<?php

namespace App\Http\Requests;

use App\Enums\RolQuirurgico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecursoHumanoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'rol' => ['required', Rule::in(RolQuirurgico::values())],
            'especialidad' => ['nullable', 'string', 'max:120'],
            'salario_mensual' => ['required', 'numeric', 'gt:0'],
            'prestaciones_mensuales' => ['required', 'numeric', 'min:0'],
            'costos_indirectos_mensuales' => ['required', 'numeric', 'min:0'],
            'activo' => ['boolean'],
        ];
    }
}
