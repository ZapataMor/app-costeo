<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsumoRequest;
use App\Models\Insumo;
use Illuminate\Http\JsonResponse;

class InsumoController extends Controller
{
    public function store(StoreInsumoRequest $request): JsonResponse
    {
        $insumo = Insumo::create($request->validated());

        return response()->json($insumo, 201);
    }
}
