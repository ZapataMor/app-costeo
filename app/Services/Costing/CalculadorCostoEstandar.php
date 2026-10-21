<?php

namespace App\Services\Costing;

use App\Models\Cirugia;
use App\Models\EquipoMedico;
use App\Models\Insumo;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\Scopes\HospitalScope;

/**
 * Costo estándar de una cirugía: lo que debió costar si se hubiera hecho
 * según su protocolo (backlog #3).
 *
 * Usa las **mismas tarifas congeladas** de la cirugía —costo mensual del
 * personal que participó, costo/hora de la sala y de los equipos, precio de
 * los insumos consumidos— y solo cambia las cantidades: duración estimada en
 * vez de la real, plantilla de personal, equipos e insumos en vez de lo
 * registrado. Así la desviación mide uso (tiempo, consumo, personal), no
 * inflación ni cambios de nómina.
 *
 * Reglas de cada componente:
 *   - Personal: una línea por rol de la plantilla, a la tarifa promedio de
 *     quienes ocuparon ese rol en la cirugía (o del hospital si nadie lo
 *     ocupó), por los minutos de la plantilla o la duración estimada.
 *   - Sala: costo/hora congelado × duración estimada.
 *   - Equipos e insumos: la plantilla sin las líneas opcionales.
 *   - Indirecto: la misma vía del caso (factor o bolsas) sobre las unidades
 *     estándar.
 *
 * Sin protocolo no hay estándar: devuelve null en vez de inventar uno.
 */
class CalculadorCostoEstandar
{
    public function __construct(protected AsignadorCif $asignador) {}

    /**
     * @param  array{minutos_disponibles: int, factor_indirecto: float, via_bolsas: bool, parametros_cif: array<string, mixed>|null, sala_derivada: bool, equipos_derivados: bool, personal_derivado: bool}  $contexto
     * @return array{total: float, directo: float, indirecto: float, recurso_humano: float, sala: float, equipos: float, insumos: float, minutos_sala: int, minutos_personal: int}|null
     */
    public function calcular(Cirugia $cirugia, array $contexto): ?array
    {
        $cirugia->loadMissing([
            'procedimientos.plantillaPersonal',
            'procedimientos.plantillaEquipos.equipoMedico',
            'procedimientos.plantillaInsumos.insumo',
        ]);

        $procedimientos = $cirugia->procedimientos;

        if ($procedimientos->isEmpty()) {
            return null;
        }

        $tienePlantilla = $procedimientos->contains(fn (ProcedimientoQuirurgico $p): bool => $p->plantillaPersonal->isNotEmpty()
            || $p->plantillaInsumos->isNotEmpty()
            || $p->plantillaEquipos->isNotEmpty());

        if (! $tienePlantilla) {
            return null;
        }

        // Varios procedimientos en el mismo acto suman su tiempo de sala.
        $minutosSala = (int) $procedimientos->sum('duracion_estimada_minutos');
        $principal = $cirugia->procedimientoPrincipal() ?? $procedimientos->first();

        [$recursoHumano, $minutosPersonal] = $this->personal($cirugia, $principal, $minutosSala, $contexto);
        $sala = $this->sala($cirugia, $minutosSala, $contexto['sala_derivada']);
        $equipos = $contexto['equipos_derivados'] ? 0.0 : $this->equipos($cirugia, $procedimientos->all(), $minutosSala);
        $insumos = $this->insumos($cirugia, $procedimientos->all());

        $directo = round($recursoHumano + $sala + $equipos + $insumos, 2);

        $indirecto = $contexto['via_bolsas']
            ? $this->asignador->asignar($contexto['parametros_cif'] ?? [], [
                'minuto_quirofano' => (float) $minutosSala,
                'minuto_personal' => (float) $minutosPersonal,
                'costo_directo' => $directo,
            ])['total']
            : round($directo * $contexto['factor_indirecto'], 2);

        return [
            'total' => round($directo + $indirecto, 2),
            'directo' => $directo,
            'indirecto' => $indirecto,
            'recurso_humano' => $recursoHumano,
            'sala' => $sala,
            'equipos' => $equipos,
            'insumos' => $insumos,
            'minutos_sala' => $minutosSala,
            'minutos_personal' => $minutosPersonal,
        ];
    }

    /**
     * @param  array{minutos_disponibles: int, personal_derivado: bool}  $contexto
     * @return array{0: float, 1: int}
     */
    protected function personal(Cirugia $cirugia, ProcedimientoQuirurgico $principal, int $minutosSala, array $contexto): array
    {
        // Tarifa por rol: la congelada de quienes lo ocuparon en esta cirugía.
        $tarifasPorRol = [];
        foreach ($cirugia->equipoQuirurgico as $miembro) {
            $tarifasPorRol[$miembro->rol][] = $miembro->costo_mensual_registrado !== null
                ? (float) $miembro->costo_mensual_registrado
                : $miembro->recursoHumano->costoMensualTotal(! $contexto['personal_derivado']);
        }

        $lineas = $principal->plantillaPersonal->where('opcional', false);

        // Sin plantilla de personal, el estándar es el mismo equipo humano
        // durante la duración estimada.
        if ($lineas->isEmpty()) {
            $lineas = collect(array_map(
                fn (string $rol): array => ['rol' => $rol, 'cantidad' => count($tarifasPorRol[$rol]), 'minutos' => null],
                array_keys($tarifasPorRol),
            ));
        }

        $costo = 0.0;
        $minutos = 0;

        foreach ($lineas as $linea) {
            $rol = (string) data_get($linea, 'rol');
            $cantidad = (int) data_get($linea, 'cantidad', 1);
            $minutosLinea = (int) (data_get($linea, 'minutos') ?? $minutosSala);
            $tarifa = isset($tarifasPorRol[$rol])
                ? array_sum($tarifasPorRol[$rol]) / count($tarifasPorRol[$rol])
                : $this->tarifaPromedioDelHospital($cirugia->hospital_id, $rol, $contexto['personal_derivado']);

            $costo += round($tarifa * $minutosLinea * $cantidad / $contexto['minutos_disponibles'], 2);
            $minutos += $minutosLinea * $cantidad;
        }

        return [round($costo, 2), $minutos];
    }

    /**
     * Filtra el hospital a mano: el costeo también corre desde consola, sin
     * hospital en contexto, y ahí el scope global no acotaría nada.
     */
    protected function tarifaPromedioDelHospital(int $hospitalId, string $rol, bool $personalDerivado): float
    {
        $recursos = RecursoHumano::query()
            ->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospitalId)
            ->where('rol', $rol)
            ->where('activo', true)
            ->get();

        if ($recursos->isEmpty()) {
            return 0.0;
        }

        return (float) $recursos->avg(fn (RecursoHumano $r): float => $r->costoMensualTotal(! $personalDerivado));
    }

    protected function sala(Cirugia $cirugia, int $minutosSala, bool $derivada): float
    {
        if ($derivada || $cirugia->sala === null) {
            return 0.0;
        }

        $costoHora = $cirugia->costo_hora_sala_registrado !== null
            ? (float) $cirugia->costo_hora_sala_registrado
            : (float) $cirugia->sala->costo_hora;

        return round($costoHora * $minutosSala / 60, 2);
    }

    /**
     * @param  list<ProcedimientoQuirurgico>  $procedimientos
     */
    protected function equipos(Cirugia $cirugia, array $procedimientos, int $minutosSala): float
    {
        $congelados = [];
        foreach ($cirugia->equiposMedicos as $equipo) {
            $registrado = $equipo->pivot?->getAttribute('costo_hora_registrado');
            $congelados[$equipo->id] = $registrado !== null ? (float) $registrado : (float) $equipo->costo_hora;
        }

        $costo = 0.0;
        $vistos = [];

        foreach ($procedimientos as $procedimiento) {
            foreach ($procedimiento->plantillaEquipos->where('opcional', false) as $linea) {
                // Un aparato compartido por dos procedimientos del mismo acto
                // se usa una vez.
                if (isset($vistos[$linea->equipo_medico_id]) || ! $linea->equipoMedico instanceof EquipoMedico) {
                    continue;
                }
                $vistos[$linea->equipo_medico_id] = true;

                $costoHora = $congelados[$linea->equipo_medico_id] ?? (float) $linea->equipoMedico->costo_hora;
                $costo += round($costoHora * ($linea->minutos_uso ?? $minutosSala) / 60, 2);
            }
        }

        return round($costo, 2);
    }

    /**
     * @param  list<ProcedimientoQuirurgico>  $procedimientos
     */
    protected function insumos(Cirugia $cirugia, array $procedimientos): float
    {
        $congelados = [];
        foreach ($cirugia->consumos as $consumo) {
            $congelados[$consumo->insumo_id] = (float) $consumo->costo_unitario_registrado;
        }

        $costo = 0.0;

        foreach ($procedimientos as $procedimiento) {
            foreach ($procedimiento->plantillaInsumos->where('opcional', false) as $linea) {
                if (! $linea->insumo instanceof Insumo) {
                    continue;
                }

                $precio = $congelados[$linea->insumo_id] ?? (float) $linea->insumo->costo_unitario;
                $costo += round((float) $linea->cantidad * $precio, 2);
            }
        }

        return round($costo, 2);
    }
}
