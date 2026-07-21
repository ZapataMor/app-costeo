<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarFacturacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'valor_facturado' => ['required', 'numeric', 'min:0'],
            // Lo glosado y lo recaudado son partes de lo facturado: no pueden
            // excederlo sin volver sin sentido las tasas de glosa y recaudo.
            'valor_glosado' => ['required', 'numeric', 'min:0', 'lte:valor_facturado'],
            'valor_recaudado' => ['required', 'numeric', 'min:0', 'lte:valor_facturado'],
            'tarifa_referencia_soat' => ['nullable', 'numeric', 'min:0'],
            'fecha_facturacion' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'valor_glosado.lte' => 'El valor glosado no puede superar el valor facturado.',
            'valor_recaudado.lte' => 'El valor recaudado no puede superar el valor facturado.',
        ];
    }
}
