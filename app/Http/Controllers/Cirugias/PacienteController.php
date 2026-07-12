<?php

namespace App\Http\Controllers\Cirugias;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePacienteRequest;
use App\Models\Paciente;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Alta rápida de paciente desde el formulario de registro de cirugía.
 */
class PacienteController extends Controller
{
    public function store(StorePacienteRequest $request): RedirectResponse
    {
        $paciente = Paciente::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Paciente {$paciente->nombres} {$paciente->apellidos} registrado.",
        ]);

        return back();
    }
}
