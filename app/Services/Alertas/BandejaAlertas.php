<?php

namespace App\Services\Alertas;

use App\Enums\EstadoAlerta;
use App\Enums\SeveridadAlerta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Una sola bandeja para las dos fuentes de alertas.
 *
 * El menú tenía dos contadores (sobrecostos por cirugía en Costeo y alertas
 * de las reglas en Conocimiento) para dos pantallas casi iguales, y el
 * administrador no sabía cuál atender. Aquí se agrupan por procedimiento
 * —y por mes, si se pide—, de modo que cientos de alertas sueltas se leen
 * como unas decenas de casos.
 *
 * Los montos no se suman entre fuentes: el exceso de un sobrecosto y el
 * impacto de una regla miden cosas distintas y a veces la misma cirugía
 * (#27, #28), así que cada grupo los muestra por separado.
 *
 * Las consultas son crudas y filtran el hospital de forma explícita: el
 * contador del menú se resuelve en las props compartidas, donde el contexto
 * de hospital puede no estar puesto.
 */
class BandejaAlertas
{
    /** Clave del grupo de las alertas sin procedimiento (sala, mes del hospital). */
    public const SIN_PROCEDIMIENTO = 'sin';

    /**
     * Pendientes agrupados por procedimiento, lo más grave primero.
     *
     * @return list<array{clave: string, procedimiento: array{id: int, codigo_cups: string, nombre: string}|null, sobrecostos: array{pendientes: int, exceso: float}, reglas: array{pendientes: int, criticas: int, impacto: float}, meses: list<string>, prioritario: bool}>
     */
    public function grupos(int $hospitalId, ?string $mes = null): array
    {
        $alertas = $this->pendientes($hospitalId);

        if ($mes !== null) {
            $alertas = array_values(array_filter($alertas, fn (array $a): bool => $a['mes'] === $mes));
        }

        $procedimientos = DB::table('procedimientos_quirurgicos')
            ->where('hospital_id', $hospitalId)
            ->whereIn('id', array_values(array_unique(array_filter(array_column($alertas, 'procedimiento_id')))))
            ->get(['id', 'codigo_cups', 'nombre'])
            ->keyBy('id');

        return array_values(collect($alertas)
            ->groupBy(fn (array $a): string => $a['procedimiento_id'] === null ? self::SIN_PROCEDIMIENTO : (string) $a['procedimiento_id'])
            ->map(function (Collection $grupo, string $clave) use ($procedimientos): array {
                $sobrecostos = $grupo->where('fuente', 'sobrecosto');
                $reglas = $grupo->where('fuente', 'regla');
                $procedimiento = $procedimientos->get((int) $clave);
                $criticas = $reglas->where('severidad', SeveridadAlerta::Critica->value)->count();

                return [
                    'clave' => $clave,
                    'procedimiento' => $procedimiento === null ? null : [
                        'id' => (int) $procedimiento->id,
                        'codigo_cups' => (string) $procedimiento->codigo_cups,
                        'nombre' => (string) $procedimiento->nombre,
                    ],
                    'sobrecostos' => [
                        'pendientes' => $sobrecostos->count(),
                        'exceso' => round((float) $sobrecostos->sum('monto'), 2),
                    ],
                    'reglas' => [
                        'pendientes' => $reglas->count(),
                        'criticas' => $criticas,
                        'impacto' => round((float) $reglas->sum('monto'), 2),
                    ],
                    'meses' => self::mesesDe($grupo->all()),
                    // Un sobrecosto sin explicar o una regla crítica piden
                    // acción; las advertencias e informativas pueden esperar.
                    'prioritario' => $sobrecostos->isNotEmpty() || $criticas > 0,
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => $b['prioritario'] <=> $a['prioritario'],
                fn (array $a, array $b): int => $b['sobrecostos']['exceso'] <=> $a['sobrecostos']['exceso'],
                fn (array $a, array $b): int => $b['reglas']['impacto'] <=> $a['reglas']['impacto'],
            ])
            ->all());
    }

    /**
     * Totales de cabecera, sin filtro de mes.
     *
     * @return array{casos: int, prioritarios: int, sobrecostos: int, exceso: float, reglas: int, criticas: int, impacto: float}
     */
    public function resumen(int $hospitalId): array
    {
        $grupos = collect($this->grupos($hospitalId));

        return [
            'casos' => $grupos->count(),
            'prioritarios' => $grupos->where('prioritario', true)->count(),
            'sobrecostos' => (int) $grupos->sum('sobrecostos.pendientes'),
            'exceso' => round((float) $grupos->sum('sobrecostos.exceso'), 2),
            'reglas' => (int) $grupos->sum('reglas.pendientes'),
            'criticas' => (int) $grupos->sum('reglas.criticas'),
            'impacto' => round((float) $grupos->sum('reglas.impacto'), 2),
        ];
    }

    /**
     * Meses con alertas pendientes, del más reciente al más antiguo.
     *
     * @return list<string>
     */
    public function meses(int $hospitalId): array
    {
        return array_reverse(self::mesesDe($this->pendientes($hospitalId)));
    }

    /**
     * Casos (procedimientos o grupo sin procedimiento) con algo prioritario
     * por revisar: el número del menú. Contar casos y no alertas sueltas es
     * lo que evita un contador de cientos que nadie puede vaciar.
     */
    public function casosPrioritarios(int $hospitalId): int
    {
        $sobrecostos = DB::table('alertas_sobrecosto')
            ->where('hospital_id', $hospitalId)
            ->where('estado', EstadoAlerta::Pendiente->value)
            ->distinct()
            ->pluck('procedimiento_quirurgico_id');

        $reglas = DB::table('alertas_conocimiento')
            ->where('hospital_id', $hospitalId)
            ->where('estado', EstadoAlerta::Pendiente->value)
            ->where('severidad', SeveridadAlerta::Critica->value)
            ->distinct()
            ->pluck('procedimiento_quirurgico_id');

        return $sobrecostos
            ->merge($reglas)
            ->map(fn ($id): string => $id === null ? self::SIN_PROCEDIMIENTO : (string) $id)
            ->unique()
            ->count();
    }

    /**
     * Las dos fuentes en una sola forma: procedimiento, mes, monto y
     * severidad. El mes de un sobrecosto es el de su cirugía; el de una regla
     * es el periodo evaluado o, en las de una cirugía, el de la cirugía.
     *
     * @return list<array{fuente: string, procedimiento_id: int|null, mes: string|null, monto: float, severidad: string|null}>
     */
    protected function pendientes(int $hospitalId): array
    {
        $alertas = [];

        $sobrecostos = DB::table('alertas_sobrecosto')
            ->join('cirugias', 'cirugias.id', '=', 'alertas_sobrecosto.cirugia_id')
            ->where('alertas_sobrecosto.hospital_id', $hospitalId)
            ->where('alertas_sobrecosto.estado', EstadoAlerta::Pendiente->value)
            ->get([
                'alertas_sobrecosto.procedimiento_quirurgico_id as procedimiento_id',
                'alertas_sobrecosto.exceso as monto',
                'cirugias.fecha',
            ]);

        foreach ($sobrecostos as $fila) {
            $alertas[] = [
                'fuente' => 'sobrecosto',
                'procedimiento_id' => (int) $fila->procedimiento_id,
                'mes' => self::mes($fila->fecha),
                'monto' => (float) $fila->monto,
                'severidad' => null,
            ];
        }

        $reglas = DB::table('alertas_conocimiento')
            ->leftJoin('cirugias', 'cirugias.id', '=', 'alertas_conocimiento.cirugia_id')
            ->where('alertas_conocimiento.hospital_id', $hospitalId)
            ->where('alertas_conocimiento.estado', EstadoAlerta::Pendiente->value)
            ->get([
                'alertas_conocimiento.procedimiento_quirurgico_id as procedimiento_id',
                'alertas_conocimiento.impacto_cop as monto',
                'alertas_conocimiento.severidad',
                'alertas_conocimiento.periodo',
                'alertas_conocimiento.created_at',
                'cirugias.fecha',
            ]);

        foreach ($reglas as $fila) {
            $alertas[] = [
                'fuente' => 'regla',
                'procedimiento_id' => $fila->procedimiento_id === null ? null : (int) $fila->procedimiento_id,
                'mes' => is_string($fila->periodo) ? $fila->periodo : (self::mes($fila->fecha) ?? self::mes($fila->created_at)),
                'monto' => (float) ($fila->monto ?? 0),
                'severidad' => (string) $fila->severidad,
            ];
        }

        return $alertas;
    }

    /**
     * Meses distintos de un grupo de alertas, del más antiguo al más reciente.
     *
     * @param  iterable<array{mes: string|null}>  $alertas
     * @return list<string>
     */
    protected static function mesesDe(iterable $alertas): array
    {
        $meses = [];

        foreach ($alertas as $alerta) {
            if ($alerta['mes'] !== null) {
                $meses[$alerta['mes']] = true;
            }
        }

        $meses = array_keys($meses);
        sort($meses);

        return array_map(strval(...), $meses);
    }

    /** «2026-05-14» o «2026-05-14 08:00:00» → «2026-05». */
    protected static function mes(mixed $fecha): ?string
    {
        return is_string($fecha) && strlen($fecha) >= 7 ? substr($fecha, 0, 7) : null;
    }
}
