<?php

namespace App\Http\Requests;

use App\Enums\CausaSobrecosto;
use App\Enums\EstadoAlerta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevisarAlertaSobrecostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $revisada = EstadoAlerta::Revisada->value;

        return [
            // Solo dos destinos: se explica o se descarta. Devolver una
            // alerta a «pendiente» a mano permitiría vaciar la bandeja sin
            // explicar nada, que es exactamente lo que hay que evitar.
            'estado' => ['required', Rule::in([$revisada, EstadoAlerta::Descartada->value])],

            // Una revisión sin causa no aporta conocimiento: sería cerrar el
            // caso y perder justo el dato por el que existe la bandeja.
            'causa' => [
                'nullable',
                "required_if:estado,{$revisada}",
                Rule::enum(CausaSobrecosto::class),
            ],

            // «Otra» sin explicación es un agujero en el catálogo que nadie
            // podrá interpretar después; se exige el texto para poder crecer
            // el catálogo con lo que realmente pasa en el hospital.
            'causa_detalle' => [
                'nullable',
                'required_if:causa,'.CausaSobrecosto::Otra->value,
                'string',
                'max:1000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'causa.required_if' => 'Indique la causa del sobrecosto para dar la alerta por revisada.',
            'causa_detalle.required_if' => 'Describa la causa cuando elija «Otra».',
        ];
    }
}
