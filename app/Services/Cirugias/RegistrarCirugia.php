<?php

namespace App\Services\Cirugias;

use App\Models\Cirugia;
use App\Models\ConsumoInsumo;
use App\Models\Insumo;
use App\Models\MiembroEquipoQuirurgico;
use Illuminate\Support\Facades\DB;

/**
 * Registra una cirugía con procedimientos, equipo quirúrgico, consumos de
 * insumos (con snapshot de precio) y equipos médicos en una transacción.
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
    }
}
