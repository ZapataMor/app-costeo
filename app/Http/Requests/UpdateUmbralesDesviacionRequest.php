<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Umbrales de la clasificación de desviación, capturados en porcentaje
 * (−5, 5, 20) porque así los lee un jefe de quirófanos; se guardan como
 * proporción, igual que el factor indirecto.
 */
class UpdateUmbralesDesviacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subejecucion_pct' => ['required', 'numeric', 'min:-90', 'max:0'],
            'sobrecosto_leve_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'sobrecosto_alto_pct' => ['required', 'numeric', 'gt:sobrecosto_leve_pct', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'subejecucion_pct.max' => 'La subejecución es una desviación negativa (p. ej. −5).',
            'sobrecosto_alto_pct.gt' => 'El umbral de sobrecosto alto debe ser mayor que el de sobrecosto leve.',
        ];
    }

    /** @return array{umbral_subejecucion: float, umbral_sobrecosto_leve: float, umbral_sobrecosto_alto: float} */
    public function umbrales(): array
    {
        return [
            'umbral_subejecucion' => round((float) $this->input('subejecucion_pct') / 100, 4),
            'umbral_sobrecosto_leve' => round((float) $this->input('sobrecosto_leve_pct') / 100, 4),
            'umbral_sobrecosto_alto' => round((float) $this->input('sobrecosto_alto_pct') / 100, 4),
        ];
    }
}
