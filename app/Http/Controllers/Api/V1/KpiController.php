<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Costing\OutlierDetector;
use App\Services\Indicators\KpiService;
use App\Support\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints agregados de indicadores (Capa 3b — marco Donabedian).
 * Todos los datos quedan acotados al hospital del usuario autenticado.
 *
 * Aceptan `?desde=&hasta=` (Y-m-d) para acotar el periodo, igual que los
 * dashboards; sin ellos calculan sobre toda la historia.
 */
class KpiController extends Controller
{
    public function __construct(
        protected KpiService $kpis,
        protected OutlierDetector $outliers,
    ) {}

    /** Proceso: costo promedio por cirugía y por procedimiento. */
    public function costos(Request $request): JsonResponse
    {
        return response()->json($this->enPeriodo($request)->costos());
    }

    /** Proceso: coeficiente de variación de costos por procedimiento. */
    public function variabilidad(Request $request): JsonResponse
    {
        return response()->json($this->enPeriodo($request)->variabilidad());
    }

    /** Resultado: margen costo real vs. tarifa facturada (ref. SOAT −25 %). */
    public function margen(Request $request): JsonResponse
    {
        return response()->json($this->enPeriodo($request)->margen());
    }

    /** Estructura: utilización de salas del mes indicado (?mes=YYYY-MM). */
    public function utilizacionSalas(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'mes' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        return response()->json($this->kpis->utilizacionSalas($validado['mes'] ?? null));
    }

    /** Resultado financiero: tasa de glosas y de recaudo. */
    public function glosasRecaudo(Request $request): JsonResponse
    {
        return response()->json($this->enPeriodo($request)->glosasRecaudo());
    }

    /** Supervisión: completitud de captura de las cirugías realizadas. */
    public function completitud(Request $request): JsonResponse
    {
        return response()->json($this->enPeriodo($request)->completitud());
    }

    /** Detección de outliers de costo por procedimiento (z-score + IQR). */
    public function outliers(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'procedimiento_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'grupos' => $this->outliers
                ->enPeriodo(Periodo::desdeRequest($request))
                ->analizar($validado['procedimiento_id'] ?? null),
        ]);
    }

    /** Costo promedio por componente y procedimiento (dashboard). */
    public function componentes(Request $request): JsonResponse
    {
        return response()->json($this->enPeriodo($request)->costoPorComponente());
    }

    protected function enPeriodo(Request $request): KpiService
    {
        return $this->kpis->enPeriodo(Periodo::desdeRequest($request));
    }
}
