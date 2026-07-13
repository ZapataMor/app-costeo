<?php

namespace App\Services\Cirugias;

use App\Models\Cirugia;
use App\Models\ConsumoInsumo;
use App\Models\EquipoMedico;
use App\Models\Insumo;
use App\Models\MiembroEquipoQuirurgico;
use App\Models\RecursoHumano;
use Illuminate\Support\Facades\DB;

/**
 * Registra una cirugía con procedimientos, equipo quirúrgico, consumos de
 * insumos y equipos médicos en una transacción. Congela (snapshot) TODAS
 * las tarifas vigentes al momento del registro —insumos, costo mensual del
 * personal, costo/hora de sala y equipos, y parámetros TDABC del hospital—
 * para que el costo histórico no cambie si los parámetros de Capa 1 se
 * actualizan después.
 *
 * Compartido por la API v1 y la UI web; espera datos ya validados por
 * StoreCirugiaRequest.
 */
class RegistrarCirugia
{
    /**
     * @param  array<string, mixed>  $datos  datos validados de StoreCirugiaRequest
     */
    public function ejecutar(array $datos): Cirugia
    {
        $atributos = collect($datos)
            ->except(['procedimientos', 'equipo', 'consumos', 'equipos_medicos'])
            ->all();

        /** @var list<array{id: int, es_principal?: bool}> $procedimientos */
        $procedimientos = $datos['procedimientos'] ?? [];

        /** @var list<array{recurso_humano_id: int, rol: string, minutos_participacion: int}> $equipo */
        $equipo = $datos['equipo'] ?? [];

        /** @var list<array{insumo_id: int, cantidad: float|int}> $consumos */
        $consumos = $datos['consumos'] ?? [];

        /** @var list<array{id: int, minutos_uso: int}> $equiposMedicos */
        $equiposMedicos = $datos['equipos_medicos'] ?? [];

        return DB::transaction(function () use ($atributos, $procedimientos, $equipo, $consumos, $equiposMedicos): Cirugia {
            $cirugia = Cirugia::create($atributos);

            // Snapshot de los parámetros TDABC del hospital y la sala
            $cirugia->loadMissing(['hospital', 'sala']);
            $cirugia->forceFill([
                'minutos_disponibles_mes_registrado' => $cirugia->hospital->minutosDisponiblesMes(),
                'factor_indirecto_registrado' => $cirugia->hospital->factor_indirecto,
                'costo_hora_sala_registrado' => $cirugia->sala?->costo_hora,
            ])->save();

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
                $recurso = RecursoHumano::findOrFail((int) $miembro['recurso_humano_id']);

                MiembroEquipoQuirurgico::create([
                    'cirugia_id' => $cirugia->id,
                    'recurso_humano_id' => $recurso->id,
                    'rol' => $miembro['rol'],
                    'minutos_participacion' => $miembro['minutos_participacion'],
                    'costo_mensual_registrado' => $recurso->costoMensualTotal(),
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
                $equipoModelo = EquipoMedico::findOrFail((int) $equipoMedico['id']);

                $cirugia->equiposMedicos()->attach($equipoModelo->id, [
                    'minutos_uso' => $equipoMedico['minutos_uso'],
                    'costo_hora_registrado' => $equipoModelo->costo_hora,
                ]);
            }

            // Recarga los defaults que aplica la base de datos (p. ej.
            // estado 'en_proceso' cuando no se envía explícitamente).
            return $cirugia->refresh();
        });
    }
}
