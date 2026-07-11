<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetHospitalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Switcher de hospital del super_admin: fija (o limpia) el hospital
 * activo de la sesión para navegar los datos de un hospital concreto.
 */
class HospitalActivoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('elegir-hospital');

        $validado = $request->validate([
            'hospital_id' => ['nullable', 'integer', 'exists:hospitales,id'],
        ], [
            'hospital_id.exists' => 'El hospital seleccionado no existe.',
        ]);

        if (($validado['hospital_id'] ?? null) === null) {
            $request->session()->forget(SetHospitalContext::SESSION_KEY);
        } else {
            $request->session()->put(SetHospitalContext::SESSION_KEY, (int) $validado['hospital_id']);
        }

        return back();
    }
}
