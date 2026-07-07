<?php

namespace App\Services\Costing;

use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Scopes\HospitalScope;
use InvalidArgumentException;

/**
 * Motor de costeo TDABC (Time-Driven Activity-Based Costing).
 *
 * Costo total = Σ(costo/minuto del recurso × minutos de uso) + costo de insumos
 * costo/minuto = (salario + prestaciones + indirectos) ÷ minutos disponibles/mes
 * minutos disponibles/mes = horas_dia × dias_mes × 60 (por defecto 12 × 26 × 60 = 18.720)
 *
 * La sala y los equipos médicos se costean por su tarifa/hora prorrateada
 * a minutos. El costo indirecto adicional aplica el factor_indirecto del
 * hospital sobre el costo directo.
 */
class TdabcCostingService
{
    public function calcular(Cirugia $cirugia): CostoCirugia
    {
        $cirugia->loadMissing([
            'hospital',
            'sala',
            'equipoQuirurgico.recursoHumano',
            'consumos.insumo',
            'equiposMedicos',
        ]);

        $hospital = $cirugia->hospital;
        $minutosDisponibles = $hospital->minutosDisponiblesMes();

        if ($minutosDisponibles <= 0) {
            throw new InvalidArgumentException(
                "El hospital {$hospital->id} no tiene minutos disponibles configurados.",
            );
        }

        $detalle = [
            'minutos_disponibles_mes' => $minutosDisponibles,
            'recurso_humano' => [],
            'sala' => null,
            'equipos' => [],
            'insumos' => [],
        ];

        // 1. Recurso humano: costo mensual total × minutos ÷ minutos disponibles.
        //    (equivale a costo/minuto × minutos, sin error de redondeo intermedio)
        $costoRecursoHumano = 0.0;

        foreach ($cirugia->equipoQuirurgico as $miembro) {
            $recurso = $miembro->recursoHumano;
            $costo = round(
                $recurso->costoMensualTotal() * $miembro->minutos_participacion / $minutosDisponibles,
                2,
            );
            $costoRecursoHumano += $costo;

            $detalle['recurso_humano'][] = [
                'recurso_humano_id' => $recurso->id,
                'nombre' => $recurso->nombre,
                'rol' => $miembro->rol,
                'minutos' => $miembro->minutos_participacion,
                'costo_por_minuto' => round($recurso->costoPorMinuto($minutosDisponibles), 4),
                'costo' => $costo,
            ];
        }

        // 2. Sala operatoria: costo/hora prorrateado a la duración real.
        $costoSala = 0.0;
        $duracion = $cirugia->duracionMinutos() ?? 0;

        if ($cirugia->sala !== null && $duracion > 0) {
            $costoSala = round((float) $cirugia->sala->costo_hora * $duracion / 60, 2);

            $detalle['sala'] = [
                'sala_operatoria_id' => $cirugia->sala->id,
                'nombre' => $cirugia->sala->nombre,
                'minutos' => $duracion,
                'costo_hora' => (float) $cirugia->sala->costo_hora,
                'costo' => $costoSala,
            ];
        }

        // 3. Equipos médicos: costo/hora × minutos de uso.
        $costoEquipos = 0.0;

        foreach ($cirugia->equiposMedicos as $equipo) {
            $minutosUso = (int) $equipo->pivot?->getAttribute('minutos_uso');
            $costo = round((float) $equipo->costo_hora * $minutosUso / 60, 2);
            $costoEquipos += $costo;

            $detalle['equipos'][] = [
                'equipo_medico_id' => $equipo->id,
                'nombre' => $equipo->nombre,
                'minutos' => $minutosUso,
                'costo_hora' => (float) $equipo->costo_hora,
                'costo' => $costo,
            ];
        }

        // 4. Insumos: suma de los consumos registrados (snapshot de precios).
        $costoInsumos = 0.0;

        foreach ($cirugia->consumos as $consumo) {
            $costoInsumos += (float) $consumo->costo_total;

            $detalle['insumos'][] = [
                'insumo_id' => $consumo->insumo_id,
                'nombre' => $consumo->insumo?->nombre,
                'cantidad' => (float) $consumo->cantidad,
                'costo_unitario' => (float) $consumo->costo_unitario_registrado,
                'costo' => (float) $consumo->costo_total,
            ];
        }

        $costoInsumos = round($costoInsumos, 2);
        $costoDirecto = round($costoRecursoHumano + $costoSala + $costoEquipos + $costoInsumos, 2);
        $costoIndirecto = round($costoDirecto * $hospital->factor_indirecto, 2);
        $costoTotal = round($costoDirecto + $costoIndirecto, 2);

        return CostoCirugia::withoutGlobalScope(HospitalScope::class)->updateOrCreate(
            ['cirugia_id' => $cirugia->id],
            [
                'hospital_id' => $cirugia->hospital_id,
                'costo_recurso_humano' => round($costoRecursoHumano, 2),
                'costo_sala' => $costoSala,
                'costo_equipos' => round($costoEquipos, 2),
                'costo_insumos' => $costoInsumos,
                'costo_directo' => $costoDirecto,
                'costo_indirecto' => $costoIndirecto,
                'costo_total' => $costoTotal,
                'detalle' => $detalle,
                'calculado_en' => now(),
            ],
        );
    }
}
