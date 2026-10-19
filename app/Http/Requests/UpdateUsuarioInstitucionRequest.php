<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * La contraseña es opcional al editar: en blanco conserva la actual.
 */
class UpdateUsuarioInstitucionRequest extends StoreUsuarioInstitucionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $usuario */
        $usuario = $this->route('usuario');

        return [
            ...parent::rules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
