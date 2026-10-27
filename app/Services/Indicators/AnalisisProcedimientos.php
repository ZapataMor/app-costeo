<?php

namespace App\Services\Indicators;

use App\Services\Costing\OutlierDetector;
use App\Support\Periodo;

/**
 * Una fila por procedimiento con todo lo que se sabe de él en el periodo:
 * costo y composición, desviación frente al estándar, margen sobre lo
 * facturado, variabilidad y atípicos.
 *
 * Antes cada dato vivía en una pestaña distinta (Procedimientos, Rentabilidad,
 * Composición, Variabilidad, Costos fuera de lo normal) y entender un solo
 * procedimiento obligaba a abrir cuatro. Los cálculos siguen siendo los de
 * `KpiService` y `OutlierDetector`; aquí solo se juntan por procedimiento.
 */
class AnalisisProcedimientos
{
    public function __construct(
        protected KpiService $kpis,
        protected OutlierDetector $detector,
    ) {}

    public function enPeriodo(Periodo $periodo): static
    {
        $this->kpis->enPeriodo($periodo);
        $this->detector->enPeriodo($periodo);

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>> indexado por id de procedimiento
     */
    public function porProcedimiento(): array
    {
        $margenes = collect($this->kpis->margen()['por_procedimiento'])
            ->keyBy(fn (array $fila): int => (int) $fila['procedimiento']['id']);
        $desviaciones = $this->kpis->desviacionPorProcedimiento();
        $grupos = $this->detector->analizar()
            ->keyBy(fn (array $grupo): int => (int) $grupo['procedimiento']['id']);

        $filas = [];

        foreach ($this->kpis->costoPorComponente()['por_procedimiento'] as $fila) {
            $id = (int) $fila['procedimiento']['id'];
            $margen = $margenes->get($id);
            $grupo = $grupos->get($id);
            $desviacion = $desviaciones[$id] ?? null;
            $cv = $grupo['coeficiente_variacion'] ?? null;

            $filas[$id] = [
                'procedimiento' => $fila['procedimiento'],
                'n' => $fila['n'],
                'costo_promedio' => $fila['total'],
                'componentes' => [
                    'recurso_humano' => $fila['recurso_humano'],
                    'sala' => $fila['sala'],
                    'equipos' => $fila['equipos'],
                    'insumos' => $fila['insumos'],
                    'indirectos' => $fila['indirectos'],
                ],
                'n_con_estandar' => $desviacion['n'] ?? 0,
                'desviacion_pct' => $desviacion['desviacion_pct'] ?? null,
                'n_facturadas' => $margen['n'] ?? 0,
                'facturado_promedio' => $margen['facturado_promedio'] ?? null,
                'margen_pct' => $margen['margen_vs_facturado_pct'] ?? null,
                // Lo que se ganó o perdió en total con el procedimiento: el
                // margen por cirugía por las cirugías facturadas.
                'resultado' => $margen !== null && $margen['margen_vs_facturado'] !== null
                    ? round($margen['margen_vs_facturado'] * $margen['n'], 2)
                    : null,
                'coeficiente_variacion' => $cv,
                'nivel_variabilidad' => $this->kpis->nivelVariabilidad($cv),
                'mediana' => $grupo['caja']['mediana'] ?? null,
                'atipicos' => (int) ($grupo['total_outliers'] ?? 0),
            ];
        }

        return $filas;
    }
}
