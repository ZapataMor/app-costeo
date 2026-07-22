<?php

namespace App\Http\Controllers\Costeo;

use App\Enums\FaseCiclo;
use App\Enums\RolQuirurgico;
use App\Http\Controllers\Controller;
use App\Models\RecursoHumano;
use App\Services\Indicators\PersonalCosteoService;
use App\Support\Periodo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Costeo por persona (Costeo → Personal): cuánto le cuesta al hospital cada
 * miembro del equipo quirúrgico y cuánto gasto moviliza, con el histórico de
 * tiempos de sus operaciones.
 *
 * El cálculo vive en PersonalCosteoService; aquí solo se resuelven filtros y
 * periodo. El HospitalScope acota todo al hospital activo.
 */
class PersonalCosteoController extends Controller
{
    public function index(Request $request, PersonalCosteoService $personal): Response
    {
        $personal->enPeriodo(Periodo::desdeRequest($request));

        $filtros = [
            'q' => trim((string) $request->query('q', '')),
            'rol' => (string) $request->query('rol', ''),
        ];

        return Inertia::render('costeo/personal/index', [
            'personal' => $personal->ranking($filtros['q'], $filtros['rol']),
            'totales' => $personal->totales(),
            'filtros' => $filtros,
            'roles' => RolQuirurgico::values(),
            'minimoParaComparar' => PersonalCosteoService::MINIMO_PARA_COMPARAR,
            'periodo' => $personal->periodo()->aArray(),
            'periodoEtiqueta' => $personal->periodo()->etiqueta(),
        ]);
    }

    public function show(
        Request $request,
        RecursoHumano $personal,
        PersonalCosteoService $servicio,
    ): Response {
        $servicio->enPeriodo(Periodo::desdeRequest($request));

        return Inertia::render('costeo/personal/show', [
            'persona' => $servicio->ficha($personal),
            ...$servicio->desgloses($personal),
            'porProcedimiento' => $servicio->porProcedimiento($personal),
            'historial' => $servicio->historial($personal),
            'etiquetasFase' => collect(FaseCiclo::cases())
                ->mapWithKeys(fn (FaseCiclo $fase): array => [$fase->value => $fase->etiqueta()])
                ->all(),
            'minimoParaComparar' => PersonalCosteoService::MINIMO_PARA_COMPARAR,
            'periodo' => $servicio->periodo()->aArray(),
            'periodoEtiqueta' => $servicio->periodo()->etiqueta(),
        ]);
    }
}
