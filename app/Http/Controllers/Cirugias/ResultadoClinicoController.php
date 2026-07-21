<?php

namespace App\Http\Controllers\Cirugias;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarResultadoClinicoRequest;
use App\Models\Cirugia;
use App\Models\ResultadoClinico;
use App\Models\Scopes\HospitalScope;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Resultado clínico del procedimiento (dimensión «resultado» de Donabedian):
 * complicaciones, estancia, reingreso y mortalidad. Sin esta captura, el
 * indicador de completitud nunca podía llegar al 100 % y la Capa 4 se
 * quedaría sin insumo para las lecciones aprendidas.
 *
 * Upsert por cirugía (relación 1:1).
 */
class ResultadoClinicoController extends Controller
{
    public function store(GuardarResultadoClinicoRequest $request, Cirugia $cirugia): RedirectResponse
    {
        ResultadoClinico::withoutGlobalScope(HospitalScope::class)->updateOrCreate(
            ['cirugia_id' => $cirugia->id],
            [...$request->validated(), 'hospital_id' => $cirugia->hospital_id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Resultado clínico guardado.']);

        return back();
    }
}
