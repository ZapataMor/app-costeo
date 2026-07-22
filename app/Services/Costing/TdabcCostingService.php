<?php

namespace App\Services\Costing;

use App\Enums\EstadoCirugia;
use App\Enums\FaseCiclo;
use App\Exceptions\CirugiaNoCosteableException;
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
 *
 * Solo se costean cirugías realizadas, y siempre con las tarifas congeladas
 * al momento del registro (snapshot); las tarifas vigentes solo se usan de
 * respaldo para datos históricos anteriores al snapshot.
 */
class TdabcCostingService
{
    public function calcular(Cirugia $cirugia): CostoCirugia
    {
        if ($cirugia->estado !== EstadoCirugia::Realizada->value) {
            throw CirugiaNoCosteableException::porEstado($cirugia->estado);
        }

        $cirugia->loadMissing([
            'hospital',
            'sala',
            'equipoQuirurgico.recursoHumano',
            'consumos.insumo',
            'equiposMedicos',
        ]);

        $hospital = $cirugia->hospital;
        $minutosDisponibles = $cirugia->minutos_disponibles_mes_registrado
            ?? $hospital->minutosDisponiblesMes();

        if ($minutosDisponibles <= 0) {
            throw new InvalidArgumentException(
                "El hospital {$hospital->id} no tiene minutos disponibles configurados.",
            );
        }

        $factorIndirecto = $cirugia->factor_indirecto_registrado ?? $hospital->factor_indirecto;

        $detalle = [
            'minutos_disponibles_mes' => $minutosDisponibles,
            'recurso_humano' => [],
            'sala' => null,
            'equipos' => [],
            'insumos' => [],
            // Costo directo agrupado por fase del ciclo: es lo que permite
            // comparar cuánto cuesta preparar al paciente frente a operarlo.
            'por_fase' => [],
        ];

        $porFase = array_fill_keys(FaseCiclo::values(), 0.0);

        // 1. Recurso humano: costo mensual congelado × minutos ÷ minutos disponibles.
        //    (equivale a costo/minuto × minutos, sin error de redondeo intermedio)
        $costoRecursoHumano = 0.0;

        foreach ($cirugia->equipoQuirurgico as $miembro) {
            $recurso = $miembro->recursoHumano;
            $costoMensual = $miembro->costo_mensual_registrado !== null
                ? (float) $miembro->costo_mensual_registrado
                : $recurso->costoMensualTotal();

            $costo = round($costoMensual * $miembro->minutos_participacion / $minutosDisponibles, 2);
            $costoRecursoHumano += $costo;
            $porFase[$miembro->fase->value] += $costo;

            $detalle['recurso_humano'][] = [
                'recurso_humano_id' => $recurso->id,
                'nombre' => $recurso->nombre,
                'rol' => $miembro->rol,
                'fase' => $miembro->fase->value,
                'minutos' => $miembro->minutos_participacion,
                'costo_por_minuto' => round($costoMensual / $minutosDisponibles, 4),
                'costo' => $costo,
            ];
        }

        // 2. Sala operatoria: costo/hora congelado prorrateado a la duración real.
        $costoSala = 0.0;
        $duracion = $cirugia->duracionMinutos() ?? 0;

        if ($cirugia->sala !== null && $duracion > 0) {
            $costoHoraSala = $cirugia->costo_hora_sala_registrado !== null
                ? (float) $cirugia->costo_hora_sala_registrado
                : (float) $cirugia->sala->costo_hora;

            $costoSala = round($costoHoraSala * $duracion / 60, 2);
            // La sala solo se ocupa durante el acto quirúrgico.
            $porFase[FaseCiclo::Quirurgica->value] += $costoSala;

            $detalle['sala'] = [
                'sala_operatoria_id' => $cirugia->sala->id,
                'nombre' => $cirugia->sala->nombre,
                'minutos' => $duracion,
                'costo_hora' => $costoHoraSala,
                'costo' => $costoSala,
            ];
        }

        // 3. Equipos médicos: costo/hora congelado × minutos de uso.
        $costoEquipos = 0.0;

        foreach ($cirugia->equiposMedicos as $equipo) {
            $minutosUso = (int) $equipo->pivot?->getAttribute('minutos_uso');
            $costoHoraRegistrado = $equipo->pivot?->getAttribute('costo_hora_registrado');
            $costoHora = $costoHoraRegistrado !== null
                ? (float) $costoHoraRegistrado
                : (float) $equipo->costo_hora;

            $costo = round($costoHora * $minutosUso / 60, 2);
            $costoEquipos += $costo;
            // Los equipos médicos se usan en sala; no se desglosan por fase.
            $porFase[FaseCiclo::Quirurgica->value] += $costo;

            $detalle['equipos'][] = [
                'equipo_medico_id' => $equipo->id,
                'nombre' => $equipo->nombre,
                'minutos' => $minutosUso,
                'costo_hora' => $costoHora,
                'costo' => $costo,
            ];
        }

        // 4. Insumos: suma de los consumos registrados (snapshot de precios).
        $costoInsumos = 0.0;

        foreach ($cirugia->consumos as $consumo) {
            $costoInsumos += (float) $consumo->costo_total;
            $porFase[$consumo->fase->value] += (float) $consumo->costo_total;

            $detalle['insumos'][] = [
                'insumo_id' => $consumo->insumo_id,
                'fase' => $consumo->fase->value,
                'nombre' => $consumo->insumo?->nombre,
                'cantidad' => (float) $consumo->cantidad,
                'costo_unitario' => (float) $consumo->costo_unitario_registrado,
                'costo' => (float) $consumo->costo_total,
            ];
        }

        $detalle['por_fase'] = array_map(
            static fn (float $costo): float => round($costo, 2),
            $porFase,
        );

        $costoInsumos = round($costoInsumos, 2);
        $costoDirecto = round($costoRecursoHumano + $costoSala + $costoEquipos + $costoInsumos, 2);
        $costoIndirecto = round($costoDirecto * $factorIndirecto, 2);
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
