<?php

namespace App\Http\Controllers\Parametros;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHospitalRequest;
use App\Http\Requests\UpdateUmbralesDesviacionRequest;
use App\Models\ConceptoCostoIndirecto;
use App\Models\Hospital;
use App\Services\Costing\ReclasificadorDesviaciones;
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
    public function edit(): Response|RedirectResponse
    {
        if (HospitalContext::id() === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Seleccione un hospital para editar su configuración TDABC.',
            ]);

            return redirect()->route('parametros.index');
        }

        $hospital = $this->hospitalActivo();

        // La clave no puede llamarse "hospital": pisaría la prop compartida
        // del switcher de hospital (HandleInertiaRequests) y rompe la vista.
        return Inertia::render('parametros/hospital', [
            'configuracion' => $hospital->only([
                'id', 'nombre', 'nit', 'municipio', 'departamento',
                'horas_dia', 'dias_mes', 'minutos_efectivos_hora', 'factor_indirecto',
                'umbral_subejecucion', 'umbral_sobrecosto_leve', 'umbral_sobrecosto_alto',
            ]),
            'minutosDisponiblesMes' => $hospital->minutosDisponiblesMes(),
            // Con bolsas activas el factor no se aplica, y sin bolsas un
            // factor en cero significa costear sin indirectos: las dos cosas
            // hay que decirlas donde se edita el campo.
            'bolsasActivas' => ConceptoCostoIndirecto::query()->where('activo', true)->count(),
        ]);
    }

    public function update(UpdateHospitalRequest $request): RedirectResponse
    {
        $this->hospitalActivo()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configuración del hospital actualizada.']);

        return redirect()->route('parametros.hospital.edit');
    }

    /**
     * Umbrales de la clasificación de desviación (estándar vs. real). Al
     * cambiarlos se reetiqueta el histórico: la desviación no cambia, solo
     * la banda en la que cae.
     */
    public function umbrales(UpdateUmbralesDesviacionRequest $request, ReclasificadorDesviaciones $reclasificador): RedirectResponse
    {
        $hospital = $this->hospitalActivo();
        $hospital->update($request->umbrales());

        $reclasificadas = $reclasificador->ejecutar($hospital);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Umbrales actualizados; {$reclasificadas} cirugías reclasificadas.",
        ]);

        return redirect()->route('parametros.hospital.edit');
    }

    protected function hospitalActivo(): Hospital
    {
        $hospital = Hospital::find(HospitalContext::id());

        abort_if($hospital === null, 403, 'Selecciona un hospital para editar su configuración.');

        return $hospital;
    }
}
