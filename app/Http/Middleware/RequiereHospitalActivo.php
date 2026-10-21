<?php

namespace App\Http\Middleware;

use App\Support\HospitalContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * El conocimiento es siempre de un hospital: el super_admin que no ha
 * elegido uno en el selector vuelve al dashboard con el aviso, en vez de
 * toparse con un 404.
 */
class RequiereHospitalActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (HospitalContext::id() === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Seleccione un hospital para ver su conocimiento.']);

            return redirect()->route('home');
        }

        return $next($request);
    }
}
