<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Redirección posterior al login según el rol: el digitador entra a su
 * único módulo (registro de procedimientos), el super_admin al dashboard
 * consolidado y el admin_hospital a Parámetros.
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
        // enviarlo a una página que su rol tiene vetada (403). Por lo mismo,
        // solo el super_admin aterriza en el dashboard.
        if ($user->isDigitador()) {
            return redirect()->route('cirugias.index');
        }

        return $user->isSuperAdmin()
            ? redirect()->intended(config('fortify.home'))
            : redirect()->intended(route('parametros.index'));
    }
}
