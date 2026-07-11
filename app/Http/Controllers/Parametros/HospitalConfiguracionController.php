<?php

namespace App\Http\Controllers\Parametros;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHospitalRequest;
use App\Models\Hospital;
use App\Support\HospitalContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración de capacidad TDABC del hospital activo:
 * horas/día, días/mes y factor de costos indirectos.
 */
class HospitalConfiguracionController extends Controller
{
    public function edit(): Response
    {
        $hospital = $this->hospitalActivo();

        return Inertia::render('parametros/hospital', [
            'hospital' => $hospital->only([
                'id', 'nombre', 'nit', 'municipio', 'departamento',
                'horas_dia', 'dias_mes', 'factor_indirecto',
            ]),
            'minutosDisponiblesMes' => $hospital->minutosDisponiblesMes(),
        ]);
    }

    public function update(UpdateHospitalRequest $request): RedirectResponse
    {
        $this->hospitalActivo()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configuración del hospital actualizada.']);

        return redirect()->route('parametros.hospital.edit');
    }

    protected function hospitalActivo(): Hospital
    {
        $hospital = Hospital::find(HospitalContext::id());

        abort_if($hospital === null, 403, 'Selecciona un hospital para editar su configuración.');

        return $hospital;
    }
}
