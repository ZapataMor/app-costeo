<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcedimientoQuirurgicoRequest;
use App\Models\ProcedimientoQuirurgico;
use Illuminate\Http\JsonResponse;

class ProcedimientoQuirurgicoController extends Controller
{
    public function store(StoreProcedimientoQuirurgicoRequest $request): JsonResponse
    {
        $procedimiento = ProcedimientoQuirurgico::create($request->validated());

        return response()->json($procedimiento, 201);
    }
}
