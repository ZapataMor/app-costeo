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
 * Corrige una cirugía ya registrada (procedimientos, equipo quirúrgico,
 * consumos y equipos médicos) en una transacción.
 *
 * Regla de tarifas: una corrección NO reescribe la historia. Las tarifas
 * congeladas al registrar se conservan para todo lo que ya estaba en la
 * cirugía; solo lo que se agrega ahora toma la tarifa vigente (es lo que
 * habría pasado si se hubiera registrado desde el principio). Igual con
 * los parámetros TDABC del hospital: se mantienen los del registro, y el
 * costo/hora de sala solo se vuelve a congelar si se cambió de sala.
 *
 * Espera datos ya validados por UpdateCirugiaRequest.
 */
class ActualizarCirugia
{
    /**
     * @param  array<string, mixed>  $datos  datos validados de UpdateCirugiaRequest
     */
    public function ejecutar(Cirugia $cirugia, array $datos): Cirugia
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

        return DB::transaction(function () use ($cirugia, $atributos, $procedimientos, $equipo, $consumos, $equiposMedicos): Cirugia {
            $cirugia->loadMissing(['hospital', 'equipoQuirurgico', 'consumos', 'equiposMedicos']);

            // Tarifas ya congeladas, indexadas por el recurso al que pertenecen.
            $costoMensualPrevio = $cirugia->equipoQuirurgico
                ->pluck('costo_mensual_registrado', 'recurso_humano_id');
            $costoUnitarioPrevio = $cirugia->consumos
                ->pluck('costo_unitario_registrado', 'insumo_id');
            $costoHoraEquipoPrevio = $cirugia->equiposMedicos
                ->mapWithKeys(fn (EquipoMedico $e): array => [
                    $e->id => $e->pivot?->getAttribute('costo_hora_registrado'),
                ]);

            $salaAnterior = $cirugia->sala_operatoria_id;

            $cirugia->update($atributos);

            // Solo un cambio de sala vuelve a congelar su tarifa; los
            // parámetros TDABC del hospital son los del registro original.
            if ($cirugia->sala_operatoria_id !== $salaAnterior) {
                $cirugia->load('sala');
                $cirugia->forceFill([
                    'costo_hora_sala_registrado' => $cirugia->sala?->costo_hora,
                ])->save();
            }

            // Procedimientos: garantiza exactamente un principal.
            $hayPrincipal = array_any(
                $procedimientos,
                fn (array $p): bool => (bool) ($p['es_principal'] ?? false),
            );

            $cirugia->procedimientos()->sync(
                collect($procedimientos)
                    ->mapWithKeys(fn (array $p, int $i): array => [
                        $p['id'] => [
                            'es_principal' => $hayPrincipal
                                ? (bool) ($p['es_principal'] ?? false)
                                : $i === 0,
                        ],
                    ])
                    ->all(),
            );

            $cirugia->equipoQuirurgico()->delete();

            foreach ($equipo as $miembro) {
                $recurso = RecursoHumano::findOrFail((int) $miembro['recurso_humano_id']);

                MiembroEquipoQuirurgico::create([
                    'cirugia_id' => $cirugia->id,
                    'recurso_humano_id' => $recurso->id,
                    'rol' => $miembro['rol'],
                    'hora_inicio' => $miembro['hora_inicio'] ?? null,
                    'hora_fin' => $miembro['hora_fin'] ?? null,
                    'minutos_participacion' => $miembro['minutos_participacion'],
                    'costo_mensual_registrado' => $costoMensualPrevio[$recurso->id]
                        ?? $recurso->costoMensualTotal(),
                ]);
            }

            $cirugia->consumos()->delete();

            foreach ($consumos as $consumo) {
                $insumo = Insumo::findOrFail((int) $consumo['insumo_id']);
                $costoUnitario = $costoUnitarioPrevio[$insumo->id] ?? $insumo->costo_unitario;

                ConsumoInsumo::create([
                    'cirugia_id' => $cirugia->id,
                    'insumo_id' => $insumo->id,
                    'cantidad' => $consumo['cantidad'],
                    'costo_unitario_registrado' => $costoUnitario,
                    'costo_total' => round((float) $consumo['cantidad'] * (float) $costoUnitario, 2),
                ]);
            }

            $cirugia->equiposMedicos()->sync(
                collect($equiposMedicos)
                    ->mapWithKeys(function (array $equipoMedico) use ($costoHoraEquipoPrevio): array {
                        $modelo = EquipoMedico::findOrFail((int) $equipoMedico['id']);

                        return [
                            $modelo->id => [
                                'minutos_uso' => $equipoMedico['minutos_uso'],
                                'costo_hora_registrado' => $costoHoraEquipoPrevio[$modelo->id]
                                    ?? $modelo->costo_hora,
                            ],
                        ];
                    })
                    ->all(),
            );

            return $cirugia->refresh();
        });
    }
}
