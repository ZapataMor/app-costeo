<?php

namespace App\Http\Requests\Conocimiento;

use App\Enums\SeveridadAlerta;
use App\Models\ReglaConocimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajuste de una regla: activarla, su severidad y sus umbrales. Solo se
 * aceptan los parámetros que el tipo de regla define.
 */
class ActualizarReglaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->gestionaConocimiento() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $regla = $this->route('regla');
        $parametros = $regla instanceof ReglaConocimiento ? array_keys($regla->tipo->parametrosPorDefecto()) : [];

        $reglas = [
            'activa' => ['required', 'boolean'],
            'severidad' => ['required', Rule::enum(SeveridadAlerta::class)],
            'parametros' => ['required', 'array:'.implode(',', $parametros)],
        ];

        foreach ($parametros as $clave) {
            $reglas["parametros.{$clave}"] = ['required', 'numeric', 'min:0', 'max:100000000'];
        }

        return $reglas;
    }
}
