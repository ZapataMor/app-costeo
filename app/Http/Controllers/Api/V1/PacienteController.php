<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePacienteRequest;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;

class PacienteController extends Controller
{
    public function store(StorePacienteRequest $request): JsonResponse
    {
        $paciente = Paciente::create($request->validated());

        return response()->json($paciente, 201);
    }
}
