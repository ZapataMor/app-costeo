<?php

namespace App\Http\Requests\Conocimiento;

use App\Enums\CategoriaLeccion;
use App\Enums\CausaSobrecosto;
use App\Enums\TipoLeccion;
use App\Support\HospitalContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una lección aprendida o mejor práctica.
 *
 * Las tres partes de texto siguen el formato clásico de lección aprendida:
 * qué pasó (contexto), qué aprendimos (lección) y qué hacer distinto
 * (recomendación). Todo lo que la enlaza —procedimiento, alertas, cirugías
 * de evidencia— debe ser del hospital activo.
 */
class GuardarLeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && HospitalContext::id() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $delHospital = fn (string $tabla) => Rule::exists($tabla, 'id')->where('hospital_id', HospitalContext::id());

        return [
            'tipo' => ['required', Rule::enum(TipoLeccion::class)],
            'titulo' => ['required', 'string', 'max:200'],
            'categoria' => ['required', Rule::enum(CategoriaLeccion::class)],
            'contexto' => ['required', 'string', 'min:20', 'max:5000'],
            'leccion' => ['required', 'string', 'min:20', 'max:5000'],
            'recomendacion' => ['nullable', 'string', 'max:5000'],
            'causa' => ['nullable', Rule::enum(CausaSobrecosto::class)],
            'procedimiento_quirurgico_id' => ['nullable', 'integer', $delHospital('procedimientos_quirurgicos')],
            'especialidad' => ['nullable', 'string', 'max:120'],
            'alerta_sobrecosto_id' => ['nullable', 'integer', $delHospital('alertas_sobrecosto')],
            'alerta_conocimiento_id' => ['nullable', 'integer', $delHospital('alertas_conocimiento')],
            'impacto_estimado_cop' => ['nullable', 'numeric', 'min:0'],
            'etiquetas' => ['nullable', 'array', 'max:8'],
            'etiquetas.*' => ['string', 'max:40'],
            'cirugias' => ['nullable', 'array', 'max:20'],
            'cirugias.*' => ['integer', $delHospital('cirugias')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contexto.min' => 'Describa qué pasó con al menos 20 caracteres.',
            'leccion.min' => 'Escriba lo aprendido con al menos 20 caracteres.',
        ];
    }
}
