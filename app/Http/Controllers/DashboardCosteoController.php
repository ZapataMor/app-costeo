<?php

namespace App\Http\Controllers;

use App\Services\Costing\OutlierDetector;
use App\Services\Indicators\KpiService;
use App\Support\Periodo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboards de BI (Capa 3c). Los datos llegan como props de Inertia;
 * los mismos agregados están disponibles como JSON en /api/v1/kpis/*.
 *
 * Todas las vistas aceptan `?desde=&hasta=` para acotar el periodo; sin
 * ellos se calcula sobre toda la historia del hospital.
 */
class DashboardCosteoController extends Controller
{
    public function index(Request $request, KpiService $kpis): Response
    {
        $kpis->enPeriodo(Periodo::desdeRequest($request));

        return Inertia::render('costeo/index', [
            'costos' => $kpis->costos(),
            'completitud' => $kpis->completitud(),
            'utilizacion' => $kpis->utilizacionSalas(),
            'glosasRecaudo' => $kpis->glosasRecaudo(),
            'tendencia' => $kpis->tendenciaMensual(),
            ...$this->periodo($kpis),
        ]);
    }

    public function componentes(Request $request, KpiService $kpis): Response
    {
        $kpis->enPeriodo(Periodo::desdeRequest($request));

        return Inertia::render('costeo/componentes', [
            ...$kpis->costoPorComponente(),
            ...$this->periodo($kpis),
        ]);
    }

    public function outliers(Request $request, OutlierDetector $detector): Response
    {
        $periodo = Periodo::desdeRequest($request);

        return Inertia::render('costeo/outliers', [
            'grupos' => $detector->enPeriodo($periodo)->analizar(),
            'periodo' => $periodo->aArray(),
            'periodoEtiqueta' => $periodo->etiqueta(),
        ]);
    }

    public function rentabilidad(Request $request, KpiService $kpis): Response
    {
        $kpis->enPeriodo(Periodo::desdeRequest($request));

        return Inertia::render('costeo/rentabilidad', [
            ...$kpis->margen(),
            'glosasRecaudo' => $kpis->glosasRecaudo(),
            ...$this->periodo($kpis),
        ]);
    }

    public function variabilidad(Request $request, KpiService $kpis): Response
    {
        $kpis->enPeriodo(Periodo::desdeRequest($request));

        return Inertia::render('costeo/variabilidad', [
            ...$kpis->variabilidad(),
            ...$this->periodo($kpis),
        ]);
    }

    /**
     * Periodo activo, para repintar el selector y rotular la vista.
     *
     * @return array<string, mixed>
     */
    protected function periodo(KpiService $kpis): array
    {
        return [
            'periodo' => $kpis->periodo()->aArray(),
            'periodoEtiqueta' => $kpis->periodo()->etiqueta(),
        ];
    }
}
