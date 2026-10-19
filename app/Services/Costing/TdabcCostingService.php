<?php

namespace App\Services\Costing;

use App\Enums\CategoriaCif;
use App\Enums\EstadoCirugia;
use App\Enums\FaseCiclo;
use App\Exceptions\CirugiaNoCosteableException;
use App\Models\Cirugia;
use App\Models\CirugiaConceptoIndirecto;
use App\Models\CostoCirugia;
use App\Models\Scopes\HospitalScope;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Motor de costeo TDABC (Time-Driven Activity-Based Costing).
 *
 * Costo total = Σ(costo/minuto del recurso × minutos de uso) + costo de insumos
 * costo/minuto = (salario + prestaciones + indirectos) ÷ minutos disponibles/mes
 * minutos disponibles/mes = horas_dia × dias_mes × minutos_efectivos_hora
 * (por defecto 12 × 26 × 60 = 18.720)
 *
 * La sala y los equipos médicos se costean por su tarifa/hora prorrateada
 * a minutos.
 *
 * El costo indirecto llega por una de dos vías excluyentes, decidida al
 * registrar la cirugía y congelada en `parametros_cif_registrados`:
 *
 * - **bolsas**: cada concepto CIF vigente se reparte por su propio inductor
 *   (minuto de quirófano, minuto de personal o porcentaje del directo). Es el
 *   costeo ABC propiamente dicho.
 * - **factor**: el `factor_indirecto` único del hospital sobre el costo
 *   directo. Es el costeo tradicional, y es lo que se aplica mientras el
 *   hospital no tenga bolsas activas.
 *
 * Cuando una categoría entra por bolsa, el componente equivalente del costo
 * directo se excluye: el `costo_hora` de la sala ya contiene servicios
 * públicos y mantenimiento, así que cobrarlo además por la bolsa de
 * infraestructura contaría lo mismo dos veces.
 *
 * Solo se costean cirugías realizadas, y siempre con las tarifas congeladas
 * al momento del registro (snapshot); las tarifas vigentes solo se usan de
 * respaldo para datos históricos anteriores al snapshot.
 */
class TdabcCostingService
{
    public function __construct(
        // Toda cirugía costeada pasa por el detector aquí y no en los
        // controladores: el costeo se dispara desde el registro, el cierre,
        // la corrección y la API, y engancharlo en cada punto garantizaba que
        // tarde o temprano uno se quedara sin alertar.
        protected DetectorSobrecostos $detector,
        protected AsignadorCif $asignador,
    ) {}

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
        $minutosEfectivosHora = $cirugia->minutos_efectivos_hora_registrado
            ?? $hospital->minutos_efectivos_hora;

        // Las cirugías anteriores al motor CIF no tienen foto: caen en la vía
        // del factor y con todos los componentes digitados, que es
        // exactamente como se costearon en su día.
        $parametrosCif = $cirugia->parametros_cif_registrados;
        $viaBolsas = ($parametrosCif['via'] ?? 'factor') === 'bolsas';

        $salaDerivada = AsignadorCif::derivadoDeCif($parametrosCif, CategoriaCif::Infraestructura);
        $equiposDerivados = AsignadorCif::derivadoDeCif($parametrosCif, CategoriaCif::DepreciacionEquipos);
        $personalDerivado = AsignadorCif::derivadoDeCif($parametrosCif, CategoriaCif::PersonalIndirecto);

        $detalle = [
            'minutos_disponibles_mes' => $minutosDisponibles,
            // Descompone el denominador anterior: sin esto, un costo viejo no
            // dice si sus 18.720 minutos vienen de 60 o de otra capacidad.
            'minutos_efectivos_hora' => $minutosEfectivosHora,
            'recurso_humano' => [],
            'sala' => null,
            'equipos' => [],
            'insumos' => [],
            // Costo directo agrupado por fase del ciclo: es lo que permite
            // comparar cuánto cuesta preparar al paciente frente a operarlo.
            'por_fase' => [],
            'indirecto' => null,
            'indirecto_por_fase' => [],
        ];

        $porFase = array_fill_keys(FaseCiclo::values(), 0.0);

        // 1. Recurso humano: costo mensual congelado × minutos ÷ minutos disponibles.
        //    (equivale a costo/minuto × minutos, sin error de redondeo intermedio)
        $costoRecursoHumano = 0.0;
        $minutosPersonal = 0.0;

        foreach ($cirugia->equipoQuirurgico as $miembro) {
            $recurso = $miembro->recursoHumano;
            // El congelado ya trae aplicada la exclusión de indirectos que
            // regía al registrar; el respaldo la reproduce para los datos
            // anteriores al snapshot.
            $costoMensual = $miembro->costo_mensual_registrado !== null
                ? (float) $miembro->costo_mensual_registrado
                : $recurso->costoMensualTotal(! $personalDerivado);

            $costo = round($costoMensual * $miembro->minutos_participacion / $minutosDisponibles, 2);
            $costoRecursoHumano += $costo;
            $minutosPersonal += (float) $miembro->minutos_participacion;
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

            $costoSala = $salaDerivada ? 0.0 : round($costoHoraSala * $duracion / 60, 2);
            // La sala solo se ocupa durante el acto quirúrgico.
            $porFase[FaseCiclo::Quirurgica->value] += $costoSala;

            $detalle['sala'] = [
                'sala_operatoria_id' => $cirugia->sala->id,
                'nombre' => $cirugia->sala->nombre,
                'minutos' => $duracion,
                'costo_hora' => $costoHoraSala,
                'costo' => $costoSala,
                // La tarifa digitada se muestra igual: sin ella, la ficha no
                // explicaría por qué el costo de la sala es cero.
                'derivado_de_cif' => $salaDerivada,
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

            $costo = $equiposDerivados ? 0.0 : round($costoHora * $minutosUso / 60, 2);
            $costoEquipos += $costo;
            // Los equipos médicos se usan en sala; no se desglosan por fase.
            $porFase[FaseCiclo::Quirurgica->value] += $costo;

            $detalle['equipos'][] = [
                'equipo_medico_id' => $equipo->id,
                'nombre' => $equipo->nombre,
                'minutos' => $minutosUso,
                'costo_hora' => $costoHora,
                'costo' => $costo,
                'derivado_de_cif' => $equiposDerivados,
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

        // 5. Indirecto: bolsas por inductor o factor plano, nunca los dos.
        $asignacion = $viaBolsas
            ? $this->asignador->asignar($parametrosCif ?? [], [
                'minuto_quirofano' => (float) $duracion,
                'minuto_personal' => $minutosPersonal,
                'costo_directo' => $costoDirecto,
            ])
            : ['total' => round($costoDirecto * $factorIndirecto, 2), 'lineas' => []];

        $costoIndirecto = $asignacion['total'];
        $costoTotal = round($costoDirecto + $costoIndirecto, 2);

        $detalle['indirecto'] = [
            'via' => $viaBolsas ? 'bolsas' : 'factor',
            'factor_indirecto' => $viaBolsas ? null : $factorIndirecto,
            'bolsas' => $asignacion['lineas'],
        ];
        $detalle['indirecto_por_fase'] = $this->indirectoPorFase($detalle['por_fase'], $costoIndirecto);

        $costo = DB::transaction(function () use ($cirugia, $detalle, $asignacion, $costoRecursoHumano, $costoSala, $costoEquipos, $costoInsumos, $costoDirecto, $costoIndirecto, $costoTotal): CostoCirugia {
            $costo = CostoCirugia::withoutGlobalScope(HospitalScope::class)->updateOrCreate(
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

            $this->guardarBolsas($cirugia, $asignacion['lineas']);

            return $costo;
        });

        $costo->setRelation('cirugia', $cirugia);
        $this->detector->evaluar($costo);

        return $costo;
    }

    /**
     * Prorratea el indirecto entre las fases según lo que cada una pesa en el
     * costo directo. El mayor resto garantiza que las partes sumen el total:
     * un desglose por fase que no cuadre con el indirecto de la ficha es un
     * descuadre contable, no un redondeo.
     *
     * @param  array<string, float>  $porFaseDirecto
     * @return array<string, float>
     */
    private function indirectoPorFase(array $porFaseDirecto, float $costoIndirecto): array
    {
        $totalCentavos = (int) round($costoIndirecto * 100);

        // Sin costo directo no hay pesos con los que prorratear (una cirugía
        // cuyos componentes están todos derivados a bolsas). El indirecto es
        // real de todos modos, así que se imputa a la fase quirúrgica en vez
        // de evaporarse.
        if (array_sum($porFaseDirecto) <= 0.0 && $totalCentavos !== 0) {
            $porFaseDirecto[FaseCiclo::Quirurgica->value] = 1.0;
        }

        $centavos = AsignadorCif::repartirPorMayorResto($porFaseDirecto, $totalCentavos);

        return array_map(static fn (int $c): float => $c / 100, $centavos);
    }

    /**
     * Reescribe las líneas del pivote. Se borran y se vuelven a insertar
     * porque recostear puede cambiar qué bolsas aplican —una corrección de
     * horas mueve las unidades— y dejar líneas viejas descuadraría la suma.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function guardarBolsas(Cirugia $cirugia, array $lineas): void
    {
        CirugiaConceptoIndirecto::withoutGlobalScope(HospitalScope::class)
            ->where('cirugia_id', $cirugia->id)
            ->delete();

        foreach ($lineas as $linea) {
            CirugiaConceptoIndirecto::withoutGlobalScope(HospitalScope::class)->create([
                'cirugia_id' => $cirugia->id,
                'concepto_costo_indirecto_id' => $linea['concepto_costo_indirecto_id'],
                'hospital_id' => $cirugia->hospital_id,
                'nombre_registrado' => $linea['nombre'],
                'categoria_registrada' => $linea['categoria'],
                'base_asignacion_registrada' => $linea['base_asignacion'],
                'monto_mensual_registrado' => $linea['monto_mensual'],
                'porcentaje_registrado' => $linea['porcentaje'],
                'denominador_registrado' => $linea['denominador'],
                'tasa_registrada' => $linea['tasa'],
                'unidades_aplicadas' => $linea['unidades'],
                'monto_asignado' => $linea['monto'],
            ]);
        }
    }
}
