<?php

namespace App\Http\Middleware;

use App\Models\Hospital;
use App\Models\User;
use App\Support\HospitalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el hospital (tenant) activo de la petición según el rol del usuario.
 *
 * - admin_hospital: SIEMPRE queda atado a su propio hospital; si no tiene
 *   hospital asignado la petición se rechaza (fail-closed).
 * - super_admin: opera sin scope (ve todos los hospitales) salvo que haya
 *   seleccionado uno con el switcher (session 'hospital_activo_id').
 */
class SetHospitalContext
{
    public const SESSION_KEY = 'hospital_activo_id';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            $hospitalId = $request->session()->get(self::SESSION_KEY);

            if ($hospitalId !== null && Hospital::whereKey($hospitalId)->exists()) {
                HospitalContext::set((int) $hospitalId);
            } else {
                HospitalContext::clear();
            }

            return $next($request);
        }

        if ($user->hospital_id === null) {
            abort(403, 'No tienes un hospital asignado. Contacta al administrador.');
        }

        HospitalContext::set($user->hospital_id);

        return $next($request);
    }

    /**
     * Limpia el contexto al terminar la petición (higiene para workers
     * de larga vida y tests que comparten el proceso).
     */
    public function terminate(Request $request, Response $response): void
    {
        HospitalContext::clear();
    }
}
