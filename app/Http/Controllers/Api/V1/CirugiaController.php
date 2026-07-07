<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCirugiaRequest;
use App\Models\Cirugia;
use App\Models\ConsumoInsumo;
use App\Models\Insumo;
use App\Models\MiembroEquipoQuirurgico;
use App\Services\Costing\TdabcCostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CirugiaController extends Controller
{
    /**
     * Registra una cirugía con procedimientos, equipo quirúrgico,
     * consumos de insumos y equipos médicos en una sola transacción.
     */
    public function store(StoreCirugiaRequest $request): JsonResponse
    {
        $atributos = $request->safe()->except([
            'procedimientos', 'equipo', 'consumos', 'equipos_medicos',
        ]);

        /** @var list<array{id: int, es_principal?: bool}> $procedimientos */
        $procedimientos = $request->validated('procedimientos', []);

        /** @var list<array{recurso_humano_id: int, rol: string, minutos_participacion: int}> $equipo */
        $equipo = $request->validated('equipo', []);

        /** @var list<array{insumo_id: int, cantidad: float|int}> $consumos */
        $consumos = $request->validated('consumos', []);

        /** @var list<array{id: int, minutos_uso: int}> $equiposMedicos */
        $equiposMedicos = $request->validated('equipos_medicos', []);

        $cirugia = DB::transaction(function () use ($atributos, $procedimientos, $equipo, $consumos, $equiposMedicos): Cirugia {
            $cirugia = Cirugia::create($atributos);

            // Procedimientos: garantiza exactamente un principal
            $hayPrincipal = array_any(
                $procedimientos,
                fn (array $p): bool => (bool) ($p['es_principal'] ?? false),
            );

            foreach ($procedimientos as $indice => $procedimiento) {
                $cirugia->procedimientos()->attach($procedimiento['id'], [
                    'es_principal' => $hayPrincipal
                        ? (bool) ($procedimiento['es_principal'] ?? false)
                        : $indice === 0,
                ]);
            }

            foreach ($equipo as $miembro) {
                MiembroEquipoQuirurgico::create([
                    'cirugia_id' => $cirugia->id,
                    'recurso_humano_id' => $miembro['recurso_humano_id'],
                    'rol' => $miembro['rol'],
                    'minutos_participacion' => $miembro['minutos_participacion'],
                ]);
            }

            foreach ($consumos as $consumo) {
                $insumo = Insumo::findOrFail((int) $consumo['insumo_id']);

                ConsumoInsumo::create([
                    'cirugia_id' => $cirugia->id,
                    'insumo_id' => $insumo->id,
                    'cantidad' => $consumo['cantidad'],
                    'costo_unitario_registrado' => $insumo->costo_unitario,
                    'costo_total' => round((float) $consumo['cantidad'] * (float) $insumo->costo_unitario, 2),
                ]);
            }

            foreach ($equiposMedicos as $equipoMedico) {
                $cirugia->equiposMedicos()->attach($equipoMedico['id'], [
                    'minutos_uso' => $equipoMedico['minutos_uso'],
                ]);
            }

            return $cirugia;
        });

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
        return response()->json($motor->calcular($cirugia));
    }
}
