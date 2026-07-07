<?php

namespace App\Http\Controllers;

use App\Services\Costing\OutlierDetector;
use App\Services\Indicators\KpiService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboards de BI (Capa 3c). Los datos llegan como props de Inertia;
 * los mismos agregados están disponibles como JSON en /api/v1/kpis/*.
 */
class DashboardCosteoController extends Controller
{
    public function index(KpiService $kpis): Response
    {
        return Inertia::render('costeo/index', [
            'costos' => $kpis->costos(),
            'completitud' => $kpis->completitud(),
            'utilizacion' => $kpis->utilizacionSalas(),
            'glosasRecaudo' => $kpis->glosasRecaudo(),
        ]);
    }

    public function componentes(KpiService $kpis): Response
    {
        return Inertia::render('costeo/componentes', $kpis->costoPorComponente());
    }

    public function outliers(OutlierDetector $detector): Response
    {
        return Inertia::render('costeo/outliers', [
            'grupos' => $detector->analizar(),
        ]);
    }

    public function rentabilidad(KpiService $kpis): Response
    {
        return Inertia::render('costeo/rentabilidad', array_merge($kpis->margen(), [
            'glosasRecaudo' => $kpis->glosasRecaudo(),
        ]));
    }

    public function variabilidad(KpiService $kpis): Response
    {
        return Inertia::render('costeo/variabilidad', $kpis->variabilidad());
    }
}
