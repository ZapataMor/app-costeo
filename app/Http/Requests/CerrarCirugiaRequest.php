<?php

namespace App\Http\Requests;

use App\Models\Cirugia;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Cierre de un procedimiento, en los dos pasos que tiene el ciclo real:
 *
 *   1. «sala»  → se registra la salida de quirófano y queda «en recuperación».
 *   2. «ciclo» → se registra el egreso de recuperación, queda «realizada» y
 *                se costea.
 *
 * Se parte en dos porque el paciente no egresa cuando termina la cirugía:
 * pedir el egreso en el mismo momento obligaría a inventarlo. El paso lo
 * decide el estado del registro, no el cliente, para que nadie pueda saltarse
 * el intermedio enviando el campo equivocado.
 */
class CerrarCirugiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** Paso que corresponde a este registro según lo que ya tiene capturado. */
    public function paso(): string
    {
        return $this->cirugia()?->hora_fin === null ? 'sala' : 'ciclo';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->paso() === 'sala') {
            return [
                'hora_fin' => ['required', 'date', $this->reglaPosteriorA('hora_inicio', estricto: true)],
            ];
        }

        return [
            'hora_salida_recuperacion' => ['required', 'date', $this->reglaPosteriorA('hora_fin')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'hora_fin.required' => 'Indique a qué hora salió el paciente de sala.',
            'hora_salida_recuperacion.required' => 'Indique a qué hora egresó el paciente de recuperación.',
        ];
    }

    /**
     * La marca nueva no puede ser anterior a la que la precede en el ciclo.
     * Se compara contra el valor ya guardado —no contra otro campo del
     * request— porque en el cierre solo llega una marca por vez.
     */
    protected function reglaPosteriorA(string $marcaPrevia, bool $estricto = false): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($marcaPrevia, $estricto): void {
            $cirugia = $this->cirugia();

            if ($cirugia === null || ! is_string($value) || $value === '') {
                return;
            }

            $previa = $cirugia->getAttribute($marcaPrevia);

            if (! $previa instanceof CarbonInterface) {
                return;
            }

            try {
                $nueva = Carbon::parse($value);
            } catch (Throwable) {
                return;
            }

            $invalida = $estricto
                ? $nueva->lessThanOrEqualTo($previa)
                : $nueva->lessThan($previa);

            if ($invalida) {
                $fail(($estricto ? 'Debe ser posterior a ' : 'No puede ser anterior a ')
                    .$previa->format('d/m/Y H:i').'.');
            }
        };
    }

    protected function cirugia(): ?Cirugia
    {
        $cirugia = $this->route('cirugia');

        return $cirugia instanceof Cirugia ? $cirugia : null;
    }
}
