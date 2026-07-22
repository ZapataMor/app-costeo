<?php

namespace App\Http\Requests;

use App\Enums\FaseCiclo;
use App\Enums\RolQuirurgico;
use App\Support\HospitalContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Plantilla estándar de un procedimiento: lo que se usa siempre.
 *
 * Las tres listas pueden llegar vacías —un procedimiento sin plantilla se
 * sigue registrando a mano, como antes—, así que guardar una lista vacía es
 * la forma de borrar la plantilla.
 */
class GuardarPlantillaProcedimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hospitalId = HospitalContext::id();

        return [
            'insumos' => ['present', 'array'],
            // Sin `distinct`: el mismo insumo puede estar en dos fases (gasas
            // en preparación y en cirugía). La pareja insumo+fase sí es única.
            'insumos.*.insumo_id' => [
                'required',
                Rule::exists('insumos', 'id')->where('hospital_id', $hospitalId),
            ],
            'insumos.*.fase' => ['required', Rule::in(FaseCiclo::values())],
            'insumos.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'insumos.*.opcional' => ['sometimes', 'boolean'],

            'personal' => ['present', 'array'],
            'personal.*.rol' => ['required', Rule::in(RolQuirurgico::values())],
            'personal.*.fase' => ['required', Rule::in(FaseCiclo::values())],
            'personal.*.cantidad' => ['required', 'integer', 'min:1', 'max:20'],
            // La persona concreta es opcional: la plantilla define el rol.
            'personal.*.recurso_humano_id' => [
                'nullable',
                Rule::exists('recursos_humanos', 'id')->where('hospital_id', $hospitalId),
            ],
            'personal.*.minutos' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'personal.*.opcional' => ['sometimes', 'boolean'],

            'equipos' => ['present', 'array'],
            'equipos.*.equipo_medico_id' => [
                'required',
                'distinct',
                Rule::exists('equipos_medicos', 'id')->where('hospital_id', $hospitalId),
            ],
            'equipos.*.minutos_uso' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'equipos.*.opcional' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rechazarRepetidos(
                $validator,
                'insumos',
                fn (array $fila): string => ($fila['insumo_id'] ?? '').'|'.($fila['fase'] ?? ''),
                'fase',
                'Este insumo ya está en la plantilla para esa fase. Ajuste la cantidad en una sola línea.',
            );

            $this->rechazarRepetidos(
                $validator,
                'personal',
                fn (array $fila): string => ($fila['rol'] ?? '').'|'.($fila['fase'] ?? '').'|'.($fila['recurso_humano_id'] ?? ''),
                'rol',
                'Esta línea ya está en la plantilla. Use la cantidad para pedir más de una persona con el mismo rol.',
            );
        });
    }

    /**
     * @param  callable(array<string, mixed>): string  $clave
     */
    protected function rechazarRepetidos(Validator $validator, string $campo, callable $clave, string $subcampo, string $mensaje): void
    {
        $filas = $this->input($campo);

        if (! is_array($filas)) {
            return;
        }

        $vistas = [];

        foreach ($filas as $indice => $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $actual = $clave($fila);

            if (isset($vistas[$actual])) {
                $validator->errors()->add("{$campo}.{$indice}.{$subcampo}", $mensaje);

                continue;
            }

            $vistas[$actual] = true;
        }
    }
}
