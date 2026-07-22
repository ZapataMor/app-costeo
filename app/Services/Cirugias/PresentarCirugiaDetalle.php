<?php

namespace App\Services\Cirugias;

use App\Models\Cirugia;
use App\Models\EquipoMedico;
use App\Models\ProcedimientoQuirurgico;

/**
 * Serializa una cirugía con todas sus relaciones para las vistas de
 * detalle (registro y costeo). Centraliza el formato para que ambas
 * pantallas muestren exactamente la misma información.
 */
class PresentarCirugiaDetalle
{
    /**
     * @return array<string, mixed>
     */
    public function ejecutar(Cirugia $cirugia): array
    {
        $cirugia->loadMissing([
            'paciente',
            'sala',
            'procedimientos',
            'equipoQuirurgico.recursoHumano',
            'consumos.insumo',
            'equiposMedicos',
            'costo',
        ]);

        return [
            'id' => $cirugia->id,
            'fecha' => $cirugia->fecha->toDateString(),
            'hora_ingreso_paciente' => $cirugia->hora_ingreso_paciente?->format('Y-m-d H:i'),
            'hora_inicio' => $cirugia->hora_inicio->format('Y-m-d H:i'),
            'hora_incision' => $cirugia->hora_incision?->format('Y-m-d H:i'),
            'hora_cierre' => $cirugia->hora_cierre?->format('Y-m-d H:i'),
            'hora_fin' => $cirugia->hora_fin?->format('Y-m-d H:i'),
            'hora_salida_recuperacion' => $cirugia->hora_salida_recuperacion?->format('Y-m-d H:i'),
            // Tiempo de sala: sigue siendo la base del costo de quirófano.
            'duracion_minutos' => $cirugia->duracionMinutos(),
            'minutos_prequirurgico' => $cirugia->minutosPrequirurgico(),
            'minutos_quirurgico_neto' => $cirugia->minutosQuirurgicoNeto(),
            'minutos_recuperacion' => $cirugia->minutosRecuperacion(),
            'ciclo_total_minutos' => $cirugia->cicloTotalMinutos(),
            'tipo' => $cirugia->tipo,
            'estado' => $cirugia->estado,
            'diagnostico_cie10' => $cirugia->diagnostico_cie10,
            'observaciones' => $cirugia->observaciones,
            'paciente' => $cirugia->paciente?->only(['nombres', 'apellidos']),
            'sala' => $cirugia->sala?->only(['nombre', 'costo_hora']),
            'procedimientos' => $cirugia->procedimientos->map(fn (ProcedimientoQuirurgico $p): array => [
                'id' => $p->id,
                'codigo_cups' => $p->codigo_cups,
                'nombre' => $p->nombre,
                'es_principal' => (bool) $p->pivot?->getAttribute('es_principal'),
            ]),
            'equipo' => $cirugia->equipoQuirurgico->map(fn ($miembro): array => [
                'nombre' => $miembro->recursoHumano?->nombre,
                'rol' => $miembro->rol,
                'minutos_participacion' => $miembro->minutos_participacion,
            ]),
            'consumos' => $cirugia->consumos->map(fn ($consumo): array => [
                'insumo' => $consumo->insumo?->nombre,
                'unidad' => $consumo->insumo?->unidad,
                'cantidad' => $consumo->cantidad,
                'costo_unitario_registrado' => $consumo->costo_unitario_registrado,
                'costo_total' => $consumo->costo_total,
            ]),
            'equipos_medicos' => $cirugia->equiposMedicos->map(fn (EquipoMedico $equipo): array => [
                'nombre' => $equipo->nombre,
                'minutos_uso' => $equipo->pivot?->getAttribute('minutos_uso'),
            ]),
        ];
    }
}
