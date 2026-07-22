<?php

namespace App\Http\Requests;

use App\Enums\EstadoCirugia;
use App\Enums\FaseCiclo;
use App\Enums\RolQuirurgico;
use App\Enums\TipoCirugia;
use App\Support\HospitalContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class StoreCirugiaRequest extends FormRequest
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
            'paciente_id' => [
                'required',
                Rule::exists('pacientes', 'id')->where('hospital_id', $hospitalId),
            ],
            'sala_operatoria_id' => [
                'nullable',
                Rule::exists('salas_operatorias', 'id')->where('hospital_id', $hospitalId),
            ],
            'fecha' => ['required', 'date'],
            // Fase pre-quirúrgica: el paciente ya está en el hospital pero la
            // sala todavía no se ocupa.
            'hora_ingreso_paciente' => ['nullable', 'date'],
            // La hora de inicio debe caer en el mismo día de la fecha: los
            // indicadores mensuales filtran por fecha pero suman minutos de
            // las horas, y un desfase distorsionaría la utilización de salas.
            'hora_inicio' => ['required', 'date', $this->reglaHoraInicioCoincideConFecha()],
            // Incisión y cierre acotan el tiempo quirúrgico neto dentro del
            // tiempo de sala; ambas opcionales, pero si viene una debe venir
            // la otra (ver validaciones adicionales más abajo).
            'hora_incision' => ['nullable', 'date'],
            'hora_cierre' => ['nullable', 'date'],
            // Consistencia temporal: la cirugía debe terminar después de empezar
            'hora_fin' => ['nullable', 'date', 'after:hora_inicio'],
            // Fase post-quirúrgica: egreso de recuperación. Es lo que cierra
            // el ciclo y habilita el estado «realizada».
            'hora_salida_recuperacion' => ['nullable', 'date'],
            'tipo' => ['required', Rule::in(TipoCirugia::values())],
            'estado' => ['sometimes', Rule::in(EstadoCirugia::values())],
            // CIE-10: letra + 2 dígitos + subcategoría opcional (p. ej. O82, K35.8)
            'diagnostico_cie10' => ['nullable', 'regex:/^[A-Z]\d{2}(\.\d{1,2})?$/'],
            'observaciones' => ['nullable', 'string'],

            'procedimientos' => ['required', 'array', 'min:1'],
            'procedimientos.*.id' => [
                'required',
                'distinct',
                Rule::exists('procedimientos_quirurgicos', 'id')->where('hospital_id', $hospitalId),
            ],
            'procedimientos.*.es_principal' => ['sometimes', 'boolean'],

            'equipo' => ['sometimes', 'array'],
            'equipo.*.recurso_humano_id' => [
                'required',
                Rule::exists('recursos_humanos', 'id')->where('hospital_id', $hospitalId),
            ],
            'equipo.*.rol' => ['required', Rule::in(RolQuirurgico::values())],
            // Fase a la que se atribuye la participación: el instrumentador
            // que alista la sala y el que opera son dos líneas distintas.
            'equipo.*.fase' => ['required', Rule::in(FaseCiclo::values())],
            // La participación se puede capturar por horas de entrada y
            // salida (como la cirugía) o directo en minutos; prepareForValidation
            // deriva los minutos cuando vienen las horas.
            'equipo.*.hora_inicio' => ['nullable', 'date'],
            'equipo.*.hora_fin' => ['nullable', 'date', 'after:equipo.*.hora_inicio'],
            'equipo.*.minutos_participacion' => ['required', 'integer', 'min:1', 'max:1440'],

            'consumos' => ['sometimes', 'array'],
            // Sin `distinct`: el mismo insumo puede consumirse en más de una
            // fase (gasas en preparación y en cirugía). Lo que no puede
            // repetirse es la pareja insumo + fase, y eso se valida aparte.
            'consumos.*.insumo_id' => [
                'required',
                Rule::exists('insumos', 'id')->where('hospital_id', $hospitalId),
            ],
            'consumos.*.fase' => ['required', Rule::in(FaseCiclo::values())],
            'consumos.*.cantidad' => ['required', 'numeric', 'gt:0'],

            'equipos_medicos' => ['sometimes', 'array'],
            'equipos_medicos.*.id' => [
                'required',
                'distinct',
                Rule::exists('equipos_medicos', 'id')->where('hospital_id', $hospitalId),
            ],
            'equipos_medicos.*.minutos_uso' => ['required', 'integer', 'min:1', 'max:1440'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'hora_fin.after' => 'La hora de finalización debe ser posterior a la hora de inicio.',
            'diagnostico_cie10.regex' => 'El diagnóstico debe ser un código CIE-10 válido (p. ej. O82 o K35.8).',
            'equipo.*.hora_fin.after' => 'La salida debe ser posterior a la entrada.',
            'equipo.*.minutos_participacion.required' => 'Indique los minutos, o las horas de entrada y salida.',
        ];
    }

    /**
     * Las marcas de fase forman una sola línea de tiempo, y el estado
     * «realizada» afirma que el ciclo terminó. Ambas cosas se validan aquí y
     * no con reglas `after:` sueltas, porque casi todas las marcas son
     * opcionales: comparar contra un campo ausente daría falsos errores.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarSecuenciaDeFases($validator);
            $this->validarCicloCompletoParaRealizada($validator);
            $this->validarLineasNoRepetidas($validator);
        });
    }

    /**
     * Una misma pareja recurso+rol+fase, o insumo+fase, no puede aparecer dos
     * veces: sería doble conteo, y además la base de datos lo rechaza con un
     * error que el usuario no entendería.
     */
    protected function validarLineasNoRepetidas(Validator $validator): void
    {
        $this->reportarRepetidos(
            $validator,
            'equipo',
            fn (array $fila): string => ($fila['recurso_humano_id'] ?? '').'|'.($fila['rol'] ?? '').'|'.($fila['fase'] ?? ''),
            'Esta persona ya está registrada con ese rol en la misma fase. Suma los minutos en una sola línea.',
        );

        $this->reportarRepetidos(
            $validator,
            'consumos',
            fn (array $fila): string => ($fila['insumo_id'] ?? '').'|'.($fila['fase'] ?? ''),
            'Este insumo ya está registrado en la misma fase. Suma las cantidades en una sola línea.',
        );
    }

    /**
     * @param  callable(array<string, mixed>): string  $clave
     */
    protected function reportarRepetidos(Validator $validator, string $campo, callable $clave, string $mensaje): void
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
                $validator->errors()->add("{$campo}.{$indice}.fase", $mensaje);

                continue;
            }

            $vistas[$actual] = true;
        }
    }

    protected function validarSecuenciaDeFases(Validator $validator): void
    {
        // Incisión y cierre son un par: una sola de las dos no acota nada.
        $incision = $this->fecha('hora_incision');
        $cierre = $this->fecha('hora_cierre');

        if ($incision !== null && $cierre === null) {
            $validator->errors()->add('hora_cierre', 'Indique también la hora de cierre: sin ella no hay tiempo quirúrgico que medir.');
        }

        if ($cierre !== null && $incision === null) {
            $validator->errors()->add('hora_incision', 'Indique también la hora de incisión: sin ella no hay tiempo quirúrgico que medir.');
        }

        $secuencia = [
            'hora_ingreso_paciente' => 'el ingreso del paciente',
            'hora_inicio' => 'la entrada a sala',
            'hora_incision' => 'la incisión',
            'hora_cierre' => 'el cierre',
            'hora_fin' => 'la salida de sala',
            'hora_salida_recuperacion' => 'la salida de recuperación',
        ];

        $previoEtiqueta = null;
        $previoValor = null;

        foreach ($secuencia as $campo => $etiqueta) {
            $valor = $this->fecha($campo);

            if ($valor === null) {
                continue;
            }

            if ($previoValor !== null && $valor->lessThan($previoValor)) {
                $validator->errors()->add(
                    $campo,
                    ucfirst($etiqueta)." no puede ser anterior a {$previoEtiqueta} (".
                        $previoValor->format('d/m/Y H:i').').',
                );
            }

            // Avanza solo con marcas válidas para que un dato ya reportado no
            // encadene un segundo error sobre la marca siguiente.
            if ($previoValor === null || ! $valor->lessThan($previoValor)) {
                $previoEtiqueta = $etiqueta;
                $previoValor = $valor;
            }
        }
    }

    /**
     * Marcar «realizada» es afirmar que el paciente ya egresó. Sin la salida
     * de recuperación esa afirmación sería un dato inventado, y el costo del
     * ciclo quedaría incompleto sin que nadie lo note. Para el intermedio
     * existe «en recuperación».
     */
    protected function validarCicloCompletoParaRealizada(Validator $validator): void
    {
        $estado = $this->input('estado');

        if ($estado === EstadoCirugia::Realizada->value) {
            if ($this->fecha('hora_fin') === null) {
                $validator->errors()->add('hora_fin', 'Una cirugía realizada necesita hora de salida de sala.');
            }

            if ($this->fecha('hora_salida_recuperacion') === null) {
                $validator->errors()->add(
                    'hora_salida_recuperacion',
                    'Una cirugía realizada necesita la salida de recuperación. Si el paciente sigue en el hospital, '.
                        'use el estado «en recuperación» y complete el dato al egreso.',
                );
            }
        }

        if ($estado === EstadoCirugia::EnRecuperacion->value && $this->fecha('hora_fin') === null) {
            $validator->errors()->add('hora_fin', 'Para pasar a recuperación, el paciente ya salió de sala: indique la hora.');
        }
    }

    /** Lee una marca de tiempo del request; null si falta o no es fecha. */
    protected function fecha(string $campo): ?Carbon
    {
        $valor = $this->input($campo);

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Deriva los minutos de participación de las horas de entrada y salida
     * cuando la captura se hizo así: los minutos siguen siendo la base del
     * costo TDABC, y esto evita que el usuario tenga que restar a mano.
     */
    protected function prepareForValidation(): void
    {
        $equipo = $this->input('equipo');

        if (! is_array($equipo)) {
            return;
        }

        $this->merge([
            'equipo' => array_map(function (mixed $miembro): mixed {
                if (! is_array($miembro)) {
                    return $miembro;
                }

                $minutos = $this->minutosEntre(
                    $miembro['hora_inicio'] ?? null,
                    $miembro['hora_fin'] ?? null,
                );

                if ($minutos !== null) {
                    $miembro['minutos_participacion'] = $minutos;
                }

                return $miembro;
            }, $equipo),
        ]);
    }

    protected function minutosEntre(mixed $inicio, mixed $fin): ?int
    {
        if (! is_string($inicio) || ! is_string($fin) || $inicio === '' || $fin === '') {
            return null;
        }

        try {
            $minutos = (int) Carbon::parse($inicio)->diffInMinutes(Carbon::parse($fin), absolute: false);
        } catch (Throwable) {
            return null;
        }

        // Un rango invertido lo reporta la regla `after`, no este cálculo.
        return $minutos > 0 ? $minutos : null;
    }

    protected function reglaHoraInicioCoincideConFecha(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $fecha = $this->input('fecha');

            if (! is_string($fecha) || $fecha === '' || ! is_string($value) || $value === '') {
                return; // 'required'/'date' reportan estos casos
            }

            try {
                $inicio = Carbon::parse($value);
                $dia = Carbon::parse($fecha);
            } catch (Throwable) {
                return;
            }

            if (! $inicio->isSameDay($dia)) {
                $fail('La hora de inicio debe corresponder al día indicado en la fecha de la cirugía.');
            }
        };
    }
}
