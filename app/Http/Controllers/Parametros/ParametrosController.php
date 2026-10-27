<?php

namespace App\Http\Controllers\Parametros;

use App\Enums\CategoriaInsumo;
use App\Enums\NivelComplejidad;
use App\Enums\NivelConfiabilidad;
use App\Enums\RolQuirurgico;
use App\Http\Controllers\Controller;
use App\Models\ConceptoCostoIndirecto;
use App\Models\EquipoMedico;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Support\HospitalContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hub del módulo Parámetros (Capa 1): una card por cada catálogo del
 * hospital activo con su conteo de registros (ya filtrado al hospital
 * activo por el HospitalScope global).
 */
class ParametrosController extends Controller
{
    public function index(): Response
    {
        $hospital = Hospital::find(HospitalContext::id());

        return Inertia::render('parametros/index', [
            'modulos' => [
                'recursosHumanos' => RecursoHumano::query()->count(),
                'insumos' => Insumo::query()->count(),
                'equiposMedicos' => EquipoMedico::query()->count(),
                'salasOperatorias' => SalaOperatoria::query()->count(),
                'procedimientos' => ProcedimientoQuirurgico::query()->count(),
                'costosIndirectos' => ConceptoCostoIndirecto::query()->count(),
            ],
            'catalogos' => [
                'roles' => RolQuirurgico::values(),
                'categorias' => CategoriaInsumo::values(),
                'complejidades' => NivelComplejidad::values(),
                'nivelesConfiabilidad' => NivelConfiabilidad::values(),
            ],
            'hospitalActivo' => $hospital?->only(['id', 'nombre']),
        ]);
    }
}
