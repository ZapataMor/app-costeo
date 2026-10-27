<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResuelvePeriodoCosteo;
use App\Services\Costing\OutlierDetector;
use App\Services\Indicators\AnalisisProcedimientos;
use App\Services\Indicators\KpiService;
use App\Support\Periodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboards de BI (Capa 3c). Los datos llegan como props de Inertia;
 * los mismos agregados están disponibles como JSON en /api/v1/kpis/*.
 *
 * Todas las vistas aceptan `?desde=&hasta=` para acotar el periodo; sin
 * ellos usan los últimos 3 meses con cirugías y `?todo=1` pide toda la
 * historia (ver ResuelvePeriodoCosteo).
 */
class DashboardCosteoController extends Controller
{
    use ResuelvePeriodoCosteo;

    /**
     * Resumen: ¿ganamos o perdemos?, ¿cuánto nos desviamos del protocolo?,
     * ¿cuánta capacidad pagamos sin usar?, y qué procedimientos lo explican.
     * Cada indicador viene con el mismo indicador del periodo anterior.
     */
    public function index(Request $request, KpiService $kpis, AnalisisProcedimientos $analisis): Response
    {
        $periodo = $this->periodoCosteo($request);
        $kpis->enPeriodo($periodo);
        $anterior = $periodo->anterior();

        return Inertia::render('costeo/index', [
            'costos' => $kpis->costos(),
            'resultado' => $kpis->resultado(),
            'desviacion' => $kpis->desviacionEstandar(),
            'completitud' => $kpis->completitud(),
            'tendencia' => $kpis->tendenciaMensual(),
            'capacidad' => $kpis->capacidadNoUtilizada(),
            'procedimientos' => array_values($analisis->enPeriodo($periodo)->porProcedimiento()),
            'anterior' => $anterior === null ? null : $this->comparacion($kpis, $anterior),
            ...$this->propsPeriodo($periodo),
        ]);
    }

    /**
     * Los indicadores del Resumen en el periodo anterior, para la línea
     * «▲ vs. periodo anterior» de cada tarjeta.
     *
     * @return array<string, mixed>
     */
    protected function comparacion(KpiService $kpis, Periodo $anterior): array
    {
        $previo = (clone $kpis)->enPeriodo($anterior);

        return [
            'costos' => $previo->costos()['global'],
            'resultado' => $previo->resultado(),
            'desviacion' => $previo->desviacionEstandar(),
            'mezcla' => $kpis->variacionAMezclaConstante($anterior),
        ];
    }

    /**
     * La composición del costo es ahora una vista de la tabla de
     * procedimientos; la ruta se conserva para los enlaces guardados.
     */
    public function componentes(Request $request): RedirectResponse
    {
        return redirect()->to('/costeo/procedimientos?'.http_build_query([
            ...$request->query(),
            'vista' => 'composicion',
        ]));
    }

    /** Atípicos y variabilidad viven en la misma pestaña. */
    public function outliers(Request $request): RedirectResponse
    {
        $consulta = http_build_query($request->query());

        return redirect()->to('/costeo/variabilidad'.($consulta !== '' ? "?{$consulta}" : ''));
    }

    public function rentabilidad(Request $request, KpiService $kpis): Response
    {
        $periodo = $this->periodoCosteo($request);
        $kpis->enPeriodo($periodo);

        return Inertia::render('costeo/rentabilidad', [
            ...$kpis->margen(),
            'glosasRecaudo' => $kpis->glosasRecaudo(),
            ...$this->propsPeriodo($periodo),
        ]);
    }

    /**
     * Variabilidad y atípicos: la misma pregunta —¿se opera siempre igual?—
     * vista por procedimiento (coeficiente de variación) y por cirugía (casos
     * fuera del rango habitual). Antes eran dos pestañas con la misma tabla.
     */
    public function variabilidad(Request $request, OutlierDetector $detector, KpiService $kpis): Response
    {
        $periodo = $this->periodoCosteo($request);

        $grupos = $detector->enPeriodo($periodo)->analizar()
            ->map(fn (array $grupo): array => [
                ...$grupo,
                'nivel_variabilidad' => $kpis->nivelVariabilidad($grupo['coeficiente_variacion']),
            ])
            ->sortByDesc('coeficiente_variacion')
            ->values();

        return Inertia::render('costeo/variabilidad', [
            'grupos' => $grupos,
            ...$this->propsPeriodo($periodo),
        ]);
    }
}
