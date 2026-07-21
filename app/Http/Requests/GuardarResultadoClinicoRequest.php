<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarResultadoClinicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hospital') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'complicacion_intraoperatoria' => ['required', 'boolean'],
            // Una complicación sin descripción no sirve para la Capa 4
            // (lecciones aprendidas): se exige el detalle al marcarla.
            'descripcion_complicacion_intra' => [
                'nullable', 'required_if:complicacion_intraoperatoria,true,1', 'string', 'max:1000',
            ],
            'complicacion_postoperatoria' => ['required', 'boolean'],
            'descripcion_complicacion_post' => [
                'nullable', 'required_if:complicacion_postoperatoria,true,1', 'string', 'max:1000',
            ],
            'dias_estancia' => ['required', 'integer', 'min:0', 'max:365'],
            'reingreso_30_dias' => ['required', 'boolean'],
            'mortalidad' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'descripcion_complicacion_intra.required_if' => 'Describa la complicación intraoperatoria.',
            'descripcion_complicacion_post.required_if' => 'Describa la complicación postoperatoria.',
        ];
    }
}
