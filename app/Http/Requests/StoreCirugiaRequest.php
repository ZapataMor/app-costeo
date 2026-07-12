<?php

namespace App\Http\Requests;

use App\Enums\EstadoCirugia;
use App\Enums\RolQuirurgico;
use App\Enums\TipoCirugia;
use App\Support\HospitalContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'hora_inicio' => ['required', 'date'],
            // Consistencia temporal: la cirugía debe terminar después de empezar
            'hora_fin' => ['nullable', 'date', 'after:hora_inicio'],
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
            'equipo.*.minutos_participacion' => ['required', 'integer', 'min:1', 'max:1440'],

            'consumos' => ['sometimes', 'array'],
            'consumos.*.insumo_id' => [
                'required',
                'distinct',
                Rule::exists('insumos', 'id')->where('hospital_id', $hospitalId),
            ],
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
        ];
    }
}
