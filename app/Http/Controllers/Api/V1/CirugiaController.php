<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoCirugia;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCirugiaRequest;
use App\Models\Cirugia;
use App\Services\Cirugias\RegistrarCirugia;
use App\Services\Costing\TdabcCostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CirugiaController extends Controller
{
    /**
     * Registra una cirugía con procedimientos, equipo quirúrgico,
     * consumos de insumos y equipos médicos en una sola transacción.
     */
    public function store(StoreCirugiaRequest $request, RegistrarCirugia $registrar): JsonResponse
    {
        $cirugia = $registrar->ejecutar($request->validated());

        return response()->json(
            $cirugia->load(['procedimientos', 'equipoQuirurgico.recursoHumano', 'consumos.insumo', 'equiposMedicos']),
            201,
        );
    }

    /**
     * Ejecuta el motor TDABC sobre la cirugía y devuelve el desglose.
     * El route-model-binding aplica el HospitalScope: una cirugía de otro
     * hospital responde 404.
     */
    public function calcularCosto(Cirugia $cirugia, TdabcCostingService $motor): JsonResponse
    {
        Gate::authorize('operar-hospital');

        if ($cirugia->estado !== EstadoCirugia::Realizada->value) {
            return response()->json([
                'message' => 'Solo se costean cirugías en estado «realizada».',
            ], 422);
        }

        return response()->json($motor->calcular($cirugia));
    }
}
