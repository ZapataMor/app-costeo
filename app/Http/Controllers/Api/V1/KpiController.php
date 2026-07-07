<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Costing\OutlierDetector;
use App\Services\Indicators\KpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints agregados de indicadores (Capa 3b — marco Donabedian).
 * Todos los datos quedan acotados al hospital del usuario autenticado.
 */
class KpiController extends Controller
{
    public function __construct(
        protected KpiService $kpis,
        protected OutlierDetector $outliers,
    ) {}

    /** Proceso: costo promedio por cirugía y por procedimiento. */
    public function costos(): JsonResponse
    {
        return response()->json($this->kpis->costos());
    }

    /** Proceso: coeficiente de variación de costos por procedimiento. */
    public function variabilidad(): JsonResponse
    {
        return response()->json($this->kpis->variabilidad());
    }

    /** Resultado: margen costo real vs. tarifa facturada (ref. SOAT −25 %). */
    public function margen(): JsonResponse
    {
        return response()->json($this->kpis->margen());
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
    public function glosasRecaudo(): JsonResponse
    {
        return response()->json($this->kpis->glosasRecaudo());
    }

    /** Supervisión: completitud de captura de las cirugías realizadas. */
    public function completitud(): JsonResponse
    {
        return response()->json($this->kpis->completitud());
    }

    /** Detección de outliers de costo por procedimiento (z-score + IQR). */
    public function outliers(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'procedimiento_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'grupos' => $this->outliers->analizar($validado['procedimiento_id'] ?? null),
        ]);
    }

    /** Costo promedio por componente y procedimiento (dashboard). */
    public function componentes(): JsonResponse
    {
        return response()->json($this->kpis->costoPorComponente());
    }
}
