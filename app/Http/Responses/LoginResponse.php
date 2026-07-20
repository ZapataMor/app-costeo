<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Redirección posterior al login según el rol: el digitador entra a su
 * único módulo (registro de procedimientos); el resto va al dashboard.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        // El digitador va siempre a su módulo; ignorar `intended` evita
        // enviarlo a una página que su rol tiene vetada (403).
        return $user->isDigitador()
            ? redirect()->route('cirugias.index')
            : redirect()->intended(config('fortify.home'));
    }
}
