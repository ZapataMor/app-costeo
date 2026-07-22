<?php

namespace App\Http\Controllers\Parametros;

use App\Enums\FaseCiclo;
use App\Enums\RolQuirurgico;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPlantillaProcedimientoRequest;
use App\Models\EquipoMedico;
use App\Models\Insumo;
use App\Models\PlantillaEquipo;
use App\Models\PlantillaInsumo;
use App\Models\PlantillaPersonal;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plantilla estándar del procedimiento: qué insumos, personal y equipos se
 * usan siempre en él.
 *
 * Va aparte del CRUD del procedimiento porque es otra decisión y otro
 * momento: primero se da de alta el protocolo con sus tiempos, después —y
 * normalmente con el jefe de quirófanos al lado— se arma lo que consume.
 */
class PlantillaProcedimientoController extends Controller
{
    public function edit(ProcedimientoQuirurgico $procedimiento): Response
    {
        $procedimiento->load(ProcedimientoQuirurgico::RELACIONES_PLANTILLA);

        return Inertia::render('parametros/procedimientos/plantilla', [
            'procedimiento' => [
                ...$procedimiento->only([
                    'id', 'codigo_cups', 'nombre', 'especialidad',
                    'duracion_estimada_minutos', 'minutos_prequirurgico',
                    'minutos_recuperacion',
                ]),
            ],
            'plantilla' => [
                'insumos' => $procedimiento->plantillaInsumos
                    ->map(fn (PlantillaInsumo $fila): array => [
                        'insumo_id' => (string) $fila->insumo_id,
                        'fase' => $fila->fase->value,
                        // Sin ceros de relleno: se edita en un input numérico.
                        'cantidad' => rtrim(rtrim((string) $fila->cantidad, '0'), '.'),
                        'opcional' => $fila->opcional,
                    ])->values(),
                'personal' => $procedimiento->plantillaPersonal
                    ->map(fn (PlantillaPersonal $fila): array => [
                        'rol' => $fila->rol,
                        'fase' => $fila->fase->value,
                        'cantidad' => (string) $fila->cantidad,
                        'recurso_humano_id' => (string) ($fila->recurso_humano_id ?? ''),
                        'minutos' => (string) ($fila->minutos ?? ''),
                        'opcional' => $fila->opcional,
                    ])->values(),
                'equipos' => $procedimiento->plantillaEquipos
                    ->map(fn (PlantillaEquipo $fila): array => [
                        'equipo_medico_id' => (string) $fila->equipo_medico_id,
                        'minutos_uso' => (string) ($fila->minutos_uso ?? ''),
                        'opcional' => $fila->opcional,
                    ])->values(),
            ],
            // Solo activos: dar de baja un insumo no debe poder meterse en
            // procedimientos nuevos a través de la plantilla.
            'insumos' => Insumo::where('activo', true)->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'unidad', 'costo_unitario']),
            'recursos' => RecursoHumano::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'rol', 'especialidad']),
            'equiposMedicos' => EquipoMedico::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'costo_hora']),
            'rolesQuirurgicos' => RolQuirurgico::values(),
            'fases' => FaseCiclo::values(),
        ]);
    }

    /**
     * Reemplaza la plantilla completa. Borrar y volver a crear es correcto
     * aquí: estas filas no guardan historia —lo que se costeó ya quedó
     * congelado en la cirugía— y evita la contorsión de diferenciar altas,
     * bajas y cambios sobre líneas que no tienen identidad estable.
     */
    public function update(
        GuardarPlantillaProcedimientoRequest $request,
        ProcedimientoQuirurgico $procedimiento,
    ): RedirectResponse {
        $datos = $request->validated();

        DB::transaction(function () use ($procedimiento, $datos): void {
            $procedimiento->plantillaInsumos()->delete();
            $procedimiento->plantillaPersonal()->delete();
            $procedimiento->plantillaEquipos()->delete();

            foreach ($datos['insumos'] as $fila) {
                $procedimiento->plantillaInsumos()->create([
                    'insumo_id' => $fila['insumo_id'],
                    'fase' => $fila['fase'],
                    'cantidad' => $fila['cantidad'],
                    'opcional' => $fila['opcional'] ?? false,
                ]);
            }

            foreach ($datos['personal'] as $fila) {
                $procedimiento->plantillaPersonal()->create([
                    'rol' => $fila['rol'],
                    'fase' => $fila['fase'],
                    'cantidad' => $fila['cantidad'],
                    'recurso_humano_id' => $fila['recurso_humano_id'] ?: null,
                    'minutos' => $fila['minutos'] ?? null,
                    'opcional' => $fila['opcional'] ?? false,
                ]);
            }

            foreach ($datos['equipos'] as $fila) {
                $procedimiento->plantillaEquipos()->create([
                    'equipo_medico_id' => $fila['equipo_medico_id'],
                    'minutos_uso' => $fila['minutos_uso'] ?? null,
                    'opcional' => $fila['opcional'] ?? false,
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plantilla guardada. Los registros nuevos de este procedimiento vendrán con ella puesta.',
        ]);

        return back();
    }
}
