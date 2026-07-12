<?php

namespace App\Http\Controllers\Parametros;

use App\Http\Controllers\Controller;
use App\Models\EquipoMedico;
use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Support\HospitalContext;
use Illuminate\Database\Eloquent\Model;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hub del módulo Parámetros (Capa 1): una card por cada catálogo del
 * hospital activo, con conteo y vista previa de los últimos registros.
 */
class ParametrosController extends Controller
{
    public function index(): Response
    {
        $hospital = Hospital::find(HospitalContext::id());

        return Inertia::render('parametros/index', [
            'modulos' => [
                'recursosHumanos' => $this->resumen(RecursoHumano::query()->orderBy('nombre'), fn (RecursoHumano $r): array => [
                    'id' => $r->id,
                    'nombre' => $r->nombre,
                    'detalle' => $r->rol,
                ]),
                'insumos' => $this->resumen(Insumo::query()->orderBy('nombre'), fn (Insumo $i): array => [
                    'id' => $i->id,
                    'nombre' => $i->nombre,
                    'detalle' => $i->categoria,
                ]),
                'equiposMedicos' => $this->resumen(EquipoMedico::query()->orderBy('nombre'), fn (EquipoMedico $e): array => [
                    'id' => $e->id,
                    'nombre' => $e->nombre,
                    'detalle' => null,
                ]),
                'salasOperatorias' => $this->resumen(SalaOperatoria::query()->orderBy('nombre'), fn (SalaOperatoria $s): array => [
                    'id' => $s->id,
                    'nombre' => $s->nombre,
                    'detalle' => null,
                ]),
                'procedimientos' => $this->resumen(ProcedimientoQuirurgico::query()->orderBy('nombre'), fn (ProcedimientoQuirurgico $p): array => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'detalle' => $p->codigo_cups,
                ]),
            ],
            'hospitalActivo' => $hospital?->only([
                'id', 'nombre', 'horas_dia', 'dias_mes', 'factor_indirecto',
            ]),
        ]);
    }

    /**
     * Conteo + primeros registros de un catálogo (ya filtrado al hospital
     * activo por el HospitalScope global).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant Model>  $query
     * @param  callable(Model): array{id: int, nombre: string, detalle: string|null}  $fila
     * @return array{total: int, items: list<array{id: int, nombre: string, detalle: string|null}>}
     */
    protected function resumen($query, callable $fila): array
    {
        return [
            'total' => (clone $query)->count(),
            'items' => $query->limit(5)->get()->map($fila)->values()->all(),
        ];
    }
}
