<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasConceptoCostoIndirecto;
use App\Http\Requests\Concerns\ReglasTrazabilidad;
use Illuminate\Foundation\Http\FormRequest;

class StoreConceptoCostoIndirectoRequest extends FormRequest
{
    use ReglasConceptoCostoIndirecto, ReglasTrazabilidad;

    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->reglasTrazabilidad(),
            ...$this->reglasConcepto(),
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
