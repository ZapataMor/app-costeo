<?php

namespace App\Http\Controllers;

use App\Services\Alertas\BandejaAlertas;
use App\Support\HospitalContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bandeja única de alertas: sobrecostos por cirugía y alertas de las reglas,
 * agrupados por procedimiento. Cada caso abre la bandeja de su fuente ya
 * filtrada, donde se atribuye la causa.
 */
class AlertasController extends Controller
{
    public function index(Request $request, BandejaAlertas $bandeja): Response
    {
        $hospitalId = (int) HospitalContext::id();
        $meses = $bandeja->meses($hospitalId);

        $mes = (string) $request->query('mes', '');
        $mes = in_array($mes, $meses, true) ? $mes : null;

        return Inertia::render('alertas/index', [
            'grupos' => $bandeja->grupos($hospitalId, $mes),
            'resumen' => $bandeja->resumen($hospitalId),
            'meses' => $meses,
            'filtros' => ['mes' => $mes ?? ''],
        ]);
    }
}
