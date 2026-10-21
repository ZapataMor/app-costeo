<?php

namespace App\Http\Requests\Conocimiento;

use App\Models\HiloComunidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarHiloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],
            'cuerpo' => ['required', 'string', 'min:10', 'max:10000'],
            'enlazable_tipo' => ['nullable', Rule::in(array_keys(HiloComunidad::ENLAZABLES))],
            'enlazable_id' => ['nullable', 'required_with:enlazable_tipo', 'integer'],
        ];
    }
}
