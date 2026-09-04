<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasConceptoCostoIndirecto;
use App\Http\Requests\Concerns\ReglasTrazabilidad;
use App\Models\ConceptoCostoIndirecto;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConceptoCostoIndirectoRequest extends FormRequest
{
    use ReglasConceptoCostoIndirecto, ReglasTrazabilidad;

    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // `route()` puede devolver el string crudo si el binding no resolvió;
        // sin la comprobación, pedir ->id ahí revienta con un 500 en vez de
        // dar un 404 limpio.
        $concepto = $this->route('concepto');

        return [
            ...$this->reglasTrazabilidad(),
            // El propio concepto no cuenta como solape consigo mismo.
            ...$this->reglasConcepto(
                $concepto instanceof ConceptoCostoIndirecto ? $concepto->id : null,
            ),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->mensajesTrazabilidad(),
            ...$this->mensajesConcepto(),
        ];
    }
}
