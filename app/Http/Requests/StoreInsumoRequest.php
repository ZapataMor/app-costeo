<?php

namespace App\Http\Requests;

use App\Enums\CategoriaInsumo;
use App\Support\HospitalContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInsumoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('insumos', 'codigo')->where('hospital_id', HospitalContext::id()),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(CategoriaInsumo::values())],
            // ATC obligatorio para medicamentos: 7 caracteres (p. ej. J01CA04)
            'codigo_atc' => [
                'required_if:categoria,'.CategoriaInsumo::Medicamento->value,
                'nullable',
                'regex:/^[A-Z]\d{2}[A-Z]{2}\d{2}$/',
            ],
            'unidad' => ['required', 'string', 'max:20'],
            'costo_unitario' => ['required', 'numeric', 'gt:0'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo_atc.required_if' => 'Los medicamentos deben registrar código ATC.',
            'codigo_atc.regex' => 'El código ATC no tiene el formato esperado (p. ej. J01CA04).',
        ];
    }
}
