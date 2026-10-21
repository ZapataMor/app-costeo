<?php

namespace App\Http\Requests\Conocimiento;

use App\Enums\CausaSobrecosto;
use App\Enums\EstadoAlerta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cierre de una alerta de conocimiento: se explica (con causa y comentario)
 * o se descarta. Como en la bandeja de sobrecostos, no se puede devolver a
 * «pendiente» a mano.
 */
class RevisarAlertaConocimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->gestionaConocimiento() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in([EstadoAlerta::Revisada->value, EstadoAlerta::Descartada->value])],
            'causa' => ['nullable', Rule::enum(CausaSobrecosto::class)],
            'comentario' => [
                'nullable',
                'required_if:estado,'.EstadoAlerta::Descartada->value,
                'string',
                'max:2000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'comentario.required_if' => 'Explique por qué se descarta: sin eso, la regla seguirá avisando lo mismo.',
        ];
    }
}
