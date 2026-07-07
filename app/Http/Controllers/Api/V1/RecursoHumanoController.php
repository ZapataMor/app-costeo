<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecursoHumanoRequest;
use App\Models\RecursoHumano;
use Illuminate\Http\JsonResponse;

class RecursoHumanoController extends Controller
{
    public function store(StoreRecursoHumanoRequest $request): JsonResponse
    {
        $recurso = RecursoHumano::create($request->validated());

        return response()->json($recurso, 201);
    }
}
