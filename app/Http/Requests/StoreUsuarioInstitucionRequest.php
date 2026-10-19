<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Usuario de una institución creado por el super_admin: administrador del
 * hospital o digitador. El super_admin no se crea desde aquí; es un rol de
 * plataforma y no cuelga de ningún hospital.
 */
class StoreUsuarioInstitucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::rolesAsignables())],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'role.in' => 'El rol debe ser administrador del hospital o digitador.',
        ];
    }

    /**
     * Roles que un usuario de institución puede tener.
     *
     * @return list<string>
     */
    public static function rolesAsignables(): array
    {
        return [RolUsuario::AdminHospital->value, RolUsuario::Digitador->value];
    }
}
