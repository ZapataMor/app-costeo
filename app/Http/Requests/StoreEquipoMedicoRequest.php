<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasTrazabilidad;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipoMedicoRequest extends FormRequest
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
            'codigo' => ['nullable', 'string', 'max:30'],
            'valor_adquisicion' => ['nullable', 'numeric', 'min:0'],
            'vida_util_anios' => ['nullable', 'integer', 'min:1', 'max:50'],
            'costo_hora' => ['required', 'numeric', 'gt:0'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mensajesTrazabilidad();
    }
}
