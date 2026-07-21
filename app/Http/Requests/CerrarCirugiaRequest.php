<?php

namespace App\Http\Requests;

use App\Models\Cirugia;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Cierre rápido de un procedimiento: se registra la hora de finalización y
 * queda en estado «realizada», que es lo único que el motor TDABC costea.
 *
 * Es la contraparte del registro en caliente: el digitador captura mientras
 * la cirugía ocurre (sin hora de fin) y cierra al terminar, sin tener que
 * volver a pasar por el formulario completo.
 */
class CerrarCirugiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hora_fin' => ['required', 'date', $this->reglaPosteriorAlInicio()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'hora_fin.required' => 'Indique a qué hora terminó el procedimiento.',
        ];
    }

    protected function reglaPosteriorAlInicio(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var Cirugia|null $cirugia */
            $cirugia = $this->route('cirugia');

            if (! $cirugia instanceof Cirugia || ! is_string($value) || $value === '') {
                return;
            }

            try {
                $fin = Carbon::parse($value);
            } catch (Throwable) {
                return;
            }

            if ($fin->lessThanOrEqualTo($cirugia->hora_inicio)) {
                $fail('La hora de finalización debe ser posterior a la hora de inicio ('
                    .$cirugia->hora_inicio->format('d/m/Y H:i').').');
            }
        };
    }
}
