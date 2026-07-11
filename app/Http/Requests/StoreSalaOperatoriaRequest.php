<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasTrazabilidad;
use App\Support\HospitalContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaOperatoriaRequest extends FormRequest
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
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('salas_operatorias', 'nombre')->where('hospital_id', HospitalContext::id()),
            ],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'costo_hora' => ['required', 'numeric', 'gt:0'],
            'equipamiento' => ['nullable', 'array'],
            'equipamiento.*' => ['string', 'max:120'],
            'activa' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->mensajesTrazabilidad(),
            'nombre.unique' => 'Ya existe una sala con ese nombre en este hospital.',
        ];
    }
}
