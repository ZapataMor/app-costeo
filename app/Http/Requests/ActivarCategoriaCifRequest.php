<?php

namespace App\Http\Requests;

use App\Enums\CategoriaCif;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Encender o apagar una categoría CIF completa.
 *
 * `confirmado` es la aceptación explícita de que el componente directo
 * equivalente pase a derivarse de la bolsa. No es un campo de formulario
 * corriente: viaja solo cuando el usuario ya vio el diálogo con los
 * registros en conflicto y decidió seguir.
 */
class ActivarCategoriaCifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'categoria' => ['required', Rule::in(CategoriaCif::values())],
            'confirmado' => ['sometimes', 'boolean'],
        ];
    }
}
