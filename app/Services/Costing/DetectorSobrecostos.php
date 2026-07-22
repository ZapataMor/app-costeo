<?php

namespace App\Services\Costing;

use App\Enums\ComponenteCosto;
use App\Enums\EstadoAlerta;
use App\Enums\EstadoCirugia;
use App\Models\AlertaSobrecosto;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Scopes\HospitalScope;
use App\Support\Estadistica;

/**
 * Convierte el resultado del costeo en una alerta accionable.
 *
 * `OutlierDetector` responde «¿cuáles casos de este procedimiento se salieron
 * del rango?» mirando toda la historia de golpe: sirve para explorar, pero
 * solo actúa si alguien abre el dashboard. Este servicio hace la pregunta al
 * revés y una cirugía a la vez —«la que se acaba de costear, ¿se salió?»—,
 * que es la única forma de avisar cuando el equipo todavía recuerda qué pasó.
 *
 * Y añade lo que al dashboard le falta: **de dónde salió el exceso**. Un
 * sobrecosto de $2,4 M no le dice nada a un jefe de quirófanos; «$1,9 M de
 * ese exceso son insumos» le dice exactamente dónde mirar.
 *
 * Criterios de detección, los mismos de `OutlierDetector` para que las dos
 * vistas nunca se contradigan (Tukey/IQR y z-score), pero solo por encima:
 * un costo anormalmente *bajo* casi siempre es captura incompleta, no ahorro,
 * y mezclarlo aquí llenaría la bandeja de casos que no son sobrecostos.
 */
class DetectorSobrecostos
{
    /**
     * Mínimo de cirugías comparables para que el baseline signifique algo.
     * Con menos, cualquier caso parece atípico y la bandeja se llena de ruido
     * justo cuando el hospital empieza a usar el sistema —que es cuando peor
     * puede permitirse dejar de confiar en las alertas—.
     */
    public const MINIMO_BASELINE = 5;

    /**
     * Exceso mínimo sobre la mediana para que el caso valga una revisión.
     *
     * Los criterios estadísticos solos no bastan: un procedimiento muy
     * estandarizado puede tener un IQR de cero —seis casos que costaron
     * exactamente lo mismo—, y entonces *cualquier* peso de más queda «fuera
     * de rango» y genera alerta. Revisar un exceso del 3 % cuesta más tiempo
     * del que ahorra, así que la significancia estadística tiene que venir
     * acompañada de significancia económica.
     */
    public const UMBRAL_EXCESO_RELATIVO = 0.10;

    public function evaluar(CostoCirugia $costo): ?AlertaSobrecosto
    {
        $cirugia = $costo->cirugia;

        if ($cirugia === null) {
            return null;
        }

        $cirugia->loadMissing('procedimientos');
        $procedimiento = $cirugia->procedimientoPrincipal();

        if ($procedimiento === null) {
            return null;
        }

        $baseline = $this->baseline($cirugia, $procedimiento->id);

        if (count($baseline) < self::MINIMO_BASELINE) {
            return null;
        }

        $totales = array_map(static fn (array $fila): float => $fila['costo_total'], $baseline);
        $veredicto = $this->evaluarTotal((float) $costo->costo_total, $totales);

        $esperado = Estadistica::percentil($totales, 0.5);
        $exceso = round((float) $costo->costo_total - $esperado, 2);
        $excesoRelativo = $esperado > 0 ? $exceso / $esperado : 0.0;

        if ($veredicto === null || $excesoRelativo < self::UMBRAL_EXCESO_RELATIVO) {
            // Ya no es atípico, o el exceso es demasiado pequeño para valer
            // una revisión: la alerta que hubiera quedado abierta ya no
            // describe nada que alguien deba ir a mirar.
            $this->descartarPendiente($cirugia->id);

            return null;
        }

        $atribucion = $this->atribuir($costo, $baseline);

        return $this->registrar($cirugia, $procedimiento->id, [
            'costo_total' => round((float) $costo->costo_total, 2),
            'costo_esperado' => round($esperado, 2),
            'exceso' => $exceso,
            'exceso_pct' => round($excesoRelativo, 4),
            'z' => $veredicto['z'],
            'criterios' => $veredicto['criterios'],
            'n_baseline' => count($baseline),
            'atribucion' => $atribucion,
            'componente_dominante' => $this->dominante($atribucion),
        ]);
    }

    /**
     * Cirugías costeadas del mismo procedimiento principal y hospital,
     * excluida la que se está evaluando: compararla contra sí misma achataría
     * el rango y escondería justo los casos más extremos.
     *
     * @return list<array<string, float>>
     */
    protected function baseline(Cirugia $cirugia, int $procedimientoId): array
    {
        $columnas = array_map(
            static fn (ComponenteCosto $componente): string => 'costos_cirugia.'.$componente->columna(),
            ComponenteCosto::cases(),
        );

        $filas = CostoCirugia::query()
            ->withoutGlobalScope(HospitalScope::class)
            ->where('costos_cirugia.hospital_id', $cirugia->hospital_id)
            ->where('costos_cirugia.cirugia_id', '!=', $cirugia->id)
            ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('cirugias.hora_fin')
            ->join('cirugia_procedimiento', function ($join) use ($procedimientoId): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')
                    ->where('cirugia_procedimiento.es_principal', true)
                    ->where('cirugia_procedimiento.procedimiento_quirurgico_id', $procedimientoId);
            })
            ->select(['costos_cirugia.costo_total', ...$columnas])
            ->toBase()
            ->get();

        $baseline = [];

        foreach ($filas as $fila) {
            $valores = ['costo_total' => (float) $fila->costo_total];

            foreach (ComponenteCosto::cases() as $componente) {
                $valores[$componente->value] = (float) $fila->{$componente->columna()};
            }

            $baseline[] = $valores;
        }

        return $baseline;
    }

    /**
     * ¿El costo total se sale por arriba? Devuelve el z y los criterios que
     * dispararon, o null si el caso está dentro de rango.
     *
     * @param  list<float>  $baseline
     * @return array{z: float|null, criterios: list<string>}|null
     */
    protected function evaluarTotal(float $costoTotal, array $baseline): ?array
    {
        $media = Estadistica::media($baseline);
        $desviacion = Estadistica::desviacionEstandar($baseline);
        $q3 = Estadistica::percentil($baseline, 0.75);
        $iqr = $q3 - Estadistica::percentil($baseline, 0.25);

        $z = $desviacion > 0 ? ($costoTotal - $media) / $desviacion : null;

        $criterios = array_values(array_filter([
            $z !== null && $z > OutlierDetector::UMBRAL_Z ? 'z' : null,
            $costoTotal > $q3 + OutlierDetector::FACTOR_IQR * $iqr ? 'iqr' : null,
        ]));

        return $criterios === []
            ? null
            : ['z' => $z !== null ? round($z, 3) : null, 'criterios' => $criterios];
    }

    /**
     * Reparte el exceso entre los componentes del TDABC.
     *
     * Cada componente se compara contra su propia mediana, no contra una
     * proporción del total: un caso puede gastar el doble en insumos y a la
     * vez menos en sala, y solo el desglose deja ver la compensación. Los
     * aportes se calculan sobre la suma de los excesos *positivos*, porque lo
     * que hay que explicar es de dónde salió el gasto de más, no cómo se
     * repartió el neto.
     *
     * @param  list<array<string, float>>  $baseline
     * @return list<array<string, mixed>>
     */
    protected function atribuir(CostoCirugia $costo, array $baseline): array
    {
        $lineas = [];

        foreach (ComponenteCosto::cases() as $componente) {
            $valores = array_column($baseline, $componente->value);
            $esperado = Estadistica::percentil($valores, 0.5);
            $real = (float) $costo->{$componente->columna()};

            $lineas[] = [
                'componente' => $componente->value,
                'etiqueta' => $componente->etiqueta(),
                'costo' => round($real, 2),
                'esperado' => round($esperado, 2),
                'exceso' => round($real - $esperado, 2),
            ];
        }

        $excesoPositivo = array_sum(array_map(
            static fn (array $linea): float => max($linea['exceso'], 0),
            $lineas,
        ));

        return array_map(static function (array $linea) use ($excesoPositivo): array {
            $linea['aporte_pct'] = $excesoPositivo > 0
                ? round(max($linea['exceso'], 0) / $excesoPositivo, 4)
                : 0.0;

            return $linea;
        }, $lineas);
    }

    /**
     * Componente que más aporta al exceso. El indirecto queda excluido: es un
     * factor sobre el directo, así que siempre sube con los demás y señalarlo
     * como causa no le diría nada a nadie.
     *
     * @param  list<array<string, mixed>>  $atribucion
     */
    protected function dominante(array $atribucion): string
    {
        $candidatos = array_filter(
            $atribucion,
            static fn (array $linea): bool => $linea['componente'] !== ComponenteCosto::Indirecto->value,
        );

        usort($candidatos, static fn (array $a, array $b): int => $b['exceso'] <=> $a['exceso']);

        return $candidatos[0]['componente'] ?? ComponenteCosto::Insumos->value;
    }

    /**
     * Crea o actualiza la alerta de la cirugía.
     *
     * Si las cifras no cambiaron, se deja intacta: recostear una cirugía ya
     * revisada no debe borrar la causa que alguien se tomó el trabajo de
     * averiguar. Si sí cambiaron, la revisión anterior describía otro caso y
     * la alerta vuelve a pendiente.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function registrar(Cirugia $cirugia, int $procedimientoId, array $datos): AlertaSobrecosto
    {
        $existente = AlertaSobrecosto::query()
            ->withoutGlobalScope(HospitalScope::class)
            ->firstWhere('cirugia_id', $cirugia->id);

        if ($existente !== null
            && (float) $existente->costo_total === $datos['costo_total']
            && (float) $existente->costo_esperado === $datos['costo_esperado']
        ) {
            return $existente;
        }

        return AlertaSobrecosto::query()
            ->withoutGlobalScope(HospitalScope::class)
            ->updateOrCreate(['cirugia_id' => $cirugia->id], [
                ...$datos,
                'hospital_id' => $cirugia->hospital_id,
                'procedimiento_quirurgico_id' => $procedimientoId,
                'estado' => EstadoAlerta::Pendiente,
                'causa' => null,
                'causa_detalle' => null,
                'revisado_por' => null,
                'revisado_en' => null,
                'detectado_en' => now(),
            ]);
    }

    /** Elimina la alerta pendiente de una cirugía que ya no es atípica. */
    protected function descartarPendiente(int $cirugiaId): void
    {
        AlertaSobrecosto::query()
            ->withoutGlobalScope(HospitalScope::class)
            ->where('cirugia_id', $cirugiaId)
            ->pendientes()
            ->delete();
    }
}
