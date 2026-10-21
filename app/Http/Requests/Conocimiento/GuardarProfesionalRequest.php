<?php

namespace App\Http\Requests\Conocimiento;

use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Cuenta de un profesional del equipo quirúrgico, vinculada a su registro de
 * recurso humano (un registro, una cuenta).
 */
class GuardarProfesionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->gestionaConocimiento() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recurso_humano_id' => [
                'required',
                'integer',
                Rule::exists('recursos_humanos', 'id')->where('hospital_id', HospitalContext::id())->where('activo', true),
                Rule::unique('users', 'recurso_humano_id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recurso_humano_id.unique' => 'Esta persona ya tiene una cuenta.',
        ];
    }
}
