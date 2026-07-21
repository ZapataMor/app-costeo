<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCirugia;
use App\Models\Cirugia;
use App\Services\Indicators\KpiService;
use App\Support\ExportadorCsv;
use App\Support\Periodo;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de los listados y agregados en CSV.
 *
 * Los comités hospitalarios y el análisis de la tesis trabajan sobre Excel;
 * sin exportación había que transcribir a mano lo que muestra la pantalla.
 * Respetan los mismos filtros que la vista de la que se descargan.
 */
class ExportacionController extends Controller
{
    /** Procedimientos registrados, con su costo TDABC si está calculado. */
    public function cirugias(Request $request): StreamedResponse
    {
        $periodo = Periodo::desdeRequest($request);
        $estado = trim((string) $request->string('estado'));

        $cirugias = Cirugia::query()
            ->with(['paciente', 'procedimientos', 'costo', 'facturacion'])
            ->when($estado !== '', fn (Builder $q) => $q->where('estado', $estado))
            ->when($periodo->desde !== null, fn (Builder $q) => $q->whereDate('fecha', '>=', $periodo->desde->toDateString()))
            ->when($periodo->hasta !== null, fn (Builder $q) => $q->whereDate('fecha', '<=', $periodo->hasta->toDateString()))
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        return ExportadorCsv::descargar(
            ExportadorCsv::nombre('procedimientos'),
            [
                'ID', 'Fecha', 'Paciente', 'CUPS', 'Procedimiento', 'Tipo', 'Estado',
                'Duración (min)', 'Costo talento humano', 'Costo sala', 'Costo equipos',
                'Costo insumos', 'Costo indirecto', 'Costo total', 'Valor facturado',
            ],
            // cursor(): no carga el histórico completo en memoria.
            $cirugias->cursor()->map(fn (Cirugia $cirugia): array => [
                $cirugia->id,
                $cirugia->fecha->toDateString(),
                $cirugia->paciente !== null
                    ? "{$cirugia->paciente->apellidos}, {$cirugia->paciente->nombres}"
                    : null,
                $cirugia->procedimientoPrincipal()?->codigo_cups,
                $cirugia->procedimientoPrincipal()?->nombre,
                $cirugia->tipo,
                $cirugia->estado,
                $cirugia->duracionMinutos(),
                $cirugia->costo?->costo_recurso_humano,
                $cirugia->costo?->costo_sala,
                $cirugia->costo?->costo_equipos,
                $cirugia->costo?->costo_insumos,
                $cirugia->costo?->costo_indirecto,
                $cirugia->costo?->costo_total,
                $cirugia->facturacion?->valor_facturado,
            ]),
        );
    }

    /** Costo promedio, variabilidad y margen por procedimiento. */
    public function indicadores(Request $request, KpiService $kpis): StreamedResponse
    {
        $periodo = Periodo::desdeRequest($request);
        $kpis->enPeriodo($periodo);

        // Se cruzan los tres agregados por procedimiento en una sola tabla:
        // es la vista que se lleva a comité.
        $variabilidad = collect($kpis->variabilidad()['por_procedimiento'])
            ->keyBy(fn (array $fila): int => $fila['procedimiento']['id']);
        $margen = collect($kpis->margen()['por_procedimiento'])
            ->keyBy(fn (array $fila): int => $fila['procedimiento']['id']);

        $filas = collect($kpis->costos()['por_procedimiento'])
            ->map(function (array $fila) use ($variabilidad, $margen): array {
                $id = $fila['procedimiento']['id'];

                return [
                    $fila['procedimiento']['codigo_cups'],
                    $fila['procedimiento']['nombre'],
                    $fila['n'],
                    $fila['costo_promedio'],
                    $fila['costo_minimo'],
                    $fila['costo_maximo'],
                    $variabilidad[$id]['desviacion'] ?? null,
                    $variabilidad[$id]['coeficiente_variacion'] ?? null,
                    $variabilidad[$id]['nivel_variabilidad'] ?? null,
                    $margen[$id]['facturado_promedio'] ?? null,
                    $margen[$id]['margen_vs_facturado'] ?? null,
                    $margen[$id]['tarifa_referencia'] ?? null,
                    $margen[$id]['margen_vs_referencia'] ?? null,
                ];
            });

        return ExportadorCsv::descargar(
            ExportadorCsv::nombre('indicadores', $periodo->vacio() ? null : $periodo->etiqueta()),
            [
                'CUPS', 'Procedimiento', 'n', 'Costo promedio', 'Costo mínimo', 'Costo máximo',
                'Desviación', 'Coef. variación', 'Variabilidad', 'Facturado promedio',
                'Margen vs facturado', 'Tarifa referencia SOAT', 'Margen vs referencia',
            ],
            $filas,
        );
    }

    /** Procedimientos pendientes de completar (bandeja de supervisión). */
    public function pendientes(): StreamedResponse
    {
        $cirugias = Cirugia::query()
            ->with(['paciente', 'procedimientos'])
            ->where(fn (Builder $q) => $q
                ->whereNull('hora_fin')
                ->orWhere('estado', '!=', EstadoCirugia::Realizada->value))
            ->orderBy('fecha');

        return ExportadorCsv::descargar(
            ExportadorCsv::nombre('pendientes'),
            ['ID', 'Fecha', 'Paciente', 'Procedimiento', 'Estado', 'Falta hora de fin'],
            $cirugias->cursor()->map(fn (Cirugia $cirugia): array => [
                $cirugia->id,
                $cirugia->fecha->toDateString(),
                $cirugia->paciente !== null
                    ? "{$cirugia->paciente->apellidos}, {$cirugia->paciente->nombres}"
                    : null,
                $cirugia->procedimientoPrincipal()?->nombre,
                $cirugia->estado,
                $cirugia->hora_fin === null ? 'sí' : 'no',
            ]),
        );
    }
}
