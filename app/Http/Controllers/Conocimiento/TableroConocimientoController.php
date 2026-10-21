<?php

namespace App\Http\Controllers\Conocimiento;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Services\Conocimiento\TableroConocimiento;
use App\Support\HospitalContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tablero de la Capa 4: lecciones contra la meta de la tesis, alertas por
 * revisar, sobrecosto evitable, recomendaciones y actividad de comunidades.
 */
class TableroConocimientoController extends Controller
{
    public function index(TableroConocimiento $tablero): Response
    {
        $hospital = Hospital::query()->findOrFail(HospitalContext::id());

        return Inertia::render('conocimiento/index', [
            'resumen' => $tablero->resumen($hospital),
        ]);
    }
}
