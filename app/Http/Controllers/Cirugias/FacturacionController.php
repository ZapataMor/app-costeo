<?php

namespace App\Http\Controllers\Cirugias;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarFacturacionRequest;
use App\Models\Cirugia;
use App\Models\Facturacion;
use App\Models\Scopes\HospitalScope;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Facturación del procedimiento: la contraparte de ingreso que faltaba para
 * que los KPIs de margen, glosas y recaudo (Capa 3) tengan de dónde salir.
 *
 * Es un upsert por cirugía —la tabla tiene la relación 1:1— para que
 * corregir una factura sea el mismo camino que registrarla.
 */
class FacturacionController extends Controller
{
    public function store(GuardarFacturacionRequest $request, Cirugia $cirugia): RedirectResponse
    {
        Facturacion::withoutGlobalScope(HospitalScope::class)->updateOrCreate(
            ['cirugia_id' => $cirugia->id],
            [...$request->validated(), 'hospital_id' => $cirugia->hospital_id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Facturación guardada.']);

        return back();
    }
}
