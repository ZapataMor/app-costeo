<?php

namespace App\Services\Conocimiento;

use App\Enums\ClasificacionDesviacion;
use App\Enums\EstadoCirugia;
use App\Models\ProcedimientoQuirurgico;
use App\Support\Estadistica;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Mejores prácticas derivadas de los datos de un procedimiento.
 *
 * «Mejor desempeño» = cuartil de menor costo **sin** complicaciones y con
 * estancia no mayor a la mediana. Un caso barato que se complicó no es una
 * buena práctica, es un riesgo; por eso el resultado clínico es condición.
 *
 * Compara ese grupo con el resto: tiempo de sala, costo por componente e
 * insumo por insumo, que es donde suele estar la diferencia aprovechable.
 *
 * El benchmarking de profesionales es **anónimo** (Profesional A, B…): la
 * tesis pide un enfoque no punitivo. Solo quien consulta ve marcada su propia
 * fila, si es uno de ellos.
 */
class AnalizadorMejoresPracticas
{
    public const MINIMO_CASOS = 10;

    /**
     * @return array<string, mixed>
     */
    public function analizar(ProcedimientoQuirurgico $procedimiento, ?int $recursoHumanoPropio = null): array
    {
        $casos = $this->casos($procedimiento);

        if ($casos->count() < self::MINIMO_CASOS) {
            return ['suficiente' => false, 'n' => $casos->count(), 'minimo' => self::MINIMO_CASOS];
        }

        $costos = $casos->pluck('costo_total')->map(fn ($v): float => (float) $v)->values()->all();
        $p25 = Estadistica::percentil($costos, 0.25);
        $estancias = $casos->whereNotNull('dias_estancia')->pluck('dias_estancia')->map(fn ($v): float => (float) $v)->values()->all();
        $estanciaMediana = $estancias === [] ? null : Estadistica::percentil($estancias, 0.5);

        $mejores = $casos->filter(fn (stdClass $c): bool => (float) $c->costo_total <= $p25
            && $c->con_resultado
            && ! $c->complicada
            && ($estanciaMediana === null || (float) $c->dias_estancia <= $estanciaMediana));
        $resto = $casos->reject(fn (stdClass $c): bool => $mejores->contains('id', $c->id));

        return [
            'suficiente' => true,
            'n' => $casos->count(),
            'desde' => $casos->min('fecha'),
            'hasta' => $casos->max('fecha'),
            'grupos' => [
                'mejores' => $this->perfil($mejores),
                'resto' => $this->perfil($resto),
            ],
            'insumos' => $this->diferenciasDeInsumos($procedimiento, $mejores, $resto),
            'clasificacion' => $this->distribucionClasificacion($casos),
            'profesionales' => $this->benchmarkProfesionales($procedimiento, $casos, $recursoHumanoPropio),
            'tendencia' => $this->tendencia($casos),
        ];
    }

    /** @return Collection<int, stdClass> */
    protected function casos(ProcedimientoQuirurgico $procedimiento): Collection
    {
        return DB::table('cirugias')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->join('costos_cirugia', 'costos_cirugia.cirugia_id', '=', 'cirugias.id')
            ->leftJoin('resultados_clinicos', 'resultados_clinicos.cirugia_id', '=', 'cirugias.id')
            ->where('cirugias.hospital_id', $procedimiento->hospital_id)
            ->where('cirugia_procedimiento.procedimiento_quirurgico_id', $procedimiento->id)
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('cirugias.hora_fin')
            ->get([
                'cirugias.id', 'cirugias.fecha', 'cirugias.hora_inicio', 'cirugias.hora_fin',
                'costos_cirugia.costo_total', 'costos_cirugia.costo_recurso_humano', 'costos_cirugia.costo_sala',
                'costos_cirugia.costo_equipos', 'costos_cirugia.costo_insumos', 'costos_cirugia.costo_indirecto',
                'costos_cirugia.clasificacion_desviacion',
                'resultados_clinicos.id as resultado_id',
                'resultados_clinicos.complicacion_intraoperatoria', 'resultados_clinicos.complicacion_postoperatoria',
                'resultados_clinicos.dias_estancia',
            ])
            ->map(function (stdClass $c): stdClass {
                $c->duracion = (float) CarbonImmutable::parse($c->hora_inicio)->diffInMinutes(CarbonImmutable::parse($c->hora_fin));
                $c->con_resultado = $c->resultado_id !== null;
                $c->complicada = (bool) $c->complicacion_intraoperatoria || (bool) $c->complicacion_postoperatoria;

                return $c;
            });
    }

    /**
     * @param  Collection<int, stdClass>  $grupo
     * @return array<string, float|int|null>
     */
    protected function perfil(Collection $grupo): array
    {
        $media = fn (string $campo): ?float => $grupo->isEmpty() ? null : round((float) $grupo->avg(fn (stdClass $c): float => (float) $c->{$campo}), 2);
        $conResultado = $grupo->where('con_resultado', true);

        return [
            'n' => $grupo->count(),
            'costo_total' => $media('costo_total'),
            'recurso_humano' => $media('costo_recurso_humano'),
            'sala' => $media('costo_sala'),
            'equipos' => $media('costo_equipos'),
            'insumos' => $media('costo_insumos'),
            'indirecto' => $media('costo_indirecto'),
            'duracion' => $media('duracion'),
            'estancia' => $conResultado->isEmpty() ? null : round((float) $conResultado->avg(fn (stdClass $c): float => (float) $c->dias_estancia), 1),
            'tasa_complicaciones' => $conResultado->isEmpty() ? null : round($conResultado->where('complicada', true)->count() / $conResultado->count(), 4),
        ];
    }

    /**
     * Insumos que el grupo de mejor desempeño usa en menor cantidad, con lo
     * que cuesta la diferencia por cirugía.
     *
     * @param  Collection<int, stdClass>  $mejores
     * @param  Collection<int, stdClass>  $resto
     * @return list<array<string, mixed>>
     */
    protected function diferenciasDeInsumos(ProcedimientoQuirurgico $procedimiento, Collection $mejores, Collection $resto): array
    {
        if ($mejores->isEmpty() || $resto->isEmpty()) {
            return [];
        }

        $consumo = DB::table('consumo_insumos')
            ->join('insumos', 'insumos.id', '=', 'consumo_insumos.insumo_id')
            ->whereIn('consumo_insumos.cirugia_id', $mejores->pluck('id')->merge($resto->pluck('id'))->all())
            ->get(['consumo_insumos.cirugia_id', 'consumo_insumos.insumo_id', 'insumos.nombre', 'insumos.unidad', 'consumo_insumos.cantidad', 'consumo_insumos.costo_unitario_registrado']);

        $idsMejores = $mejores->pluck('id')->flip();
        $porInsumo = $consumo->groupBy('insumo_id');
        $filas = [];

        foreach ($porInsumo as $insumoId => $lineas) {
            /** @var stdClass $primera */
            $primera = $lineas->first();
            $enMejores = $lineas->filter(fn (stdClass $l): bool => $idsMejores->has($l->cirugia_id));
            $enResto = $lineas->reject(fn (stdClass $l): bool => $idsMejores->has($l->cirugia_id));

            // Promedio por cirugía del grupo (las que no lo usaron cuentan cero).
            $cantidadMejores = (float) $enMejores->sum('cantidad') / $mejores->count();
            $cantidadResto = (float) $enResto->sum('cantidad') / $resto->count();
            $precio = (float) $lineas->avg('costo_unitario_registrado');
            $diferencia = ($cantidadResto - $cantidadMejores) * $precio;

            if ($diferencia <= 0) {
                continue;
            }

            $filas[] = [
                'insumo_id' => (int) $insumoId,
                'insumo' => $primera->nombre,
                'unidad' => $primera->unidad,
                'cantidad_mejores' => round($cantidadMejores, 2),
                'cantidad_resto' => round($cantidadResto, 2),
                'diferencia_por_cirugia' => round($diferencia, 2),
            ];
        }

        usort($filas, fn (array $a, array $b): int => $b['diferencia_por_cirugia'] <=> $a['diferencia_por_cirugia']);

        return array_slice($filas, 0, 8);
    }

    /**
     * @param  Collection<int, stdClass>  $casos
     * @return list<array{clasificacion: string, n: int}>
     */
    protected function distribucionClasificacion(Collection $casos): array
    {
        return array_map(fn (ClasificacionDesviacion $c): array => [
            'clasificacion' => $c->value,
            'n' => $casos->where('clasificacion_desviacion', $c->value)->count(),
        ], ClasificacionDesviacion::cases());
    }

    /**
     * Cirujanos con al menos 3 casos, anónimos y ordenados por índice de
     * costo (1,00 = promedio del procedimiento).
     *
     * @param  Collection<int, stdClass>  $casos
     * @return list<array<string, mixed>>
     */
    protected function benchmarkProfesionales(ProcedimientoQuirurgico $procedimiento, Collection $casos, ?int $propio): array
    {
        $porCirugia = $casos->keyBy('id');
        $promedio = (float) $casos->avg(fn (stdClass $c): float => (float) $c->costo_total);
        $duracionPromedio = (float) $casos->avg('duracion');

        $cirujanos = DB::table('miembros_equipo_quirurgico')
            ->whereIn('cirugia_id', $casos->pluck('id')->all())
            ->where('rol', 'cirujano')
            ->get(['cirugia_id', 'recurso_humano_id'])
            ->groupBy('recurso_humano_id');

        $filas = [];
        foreach ($cirujanos as $recursoId => $participaciones) {
            $suyos = $participaciones->map(fn (stdClass $p): ?stdClass => $porCirugia->get($p->cirugia_id))->filter();

            if ($suyos->count() < 3) {
                continue;
            }

            $conResultado = $suyos->where('con_resultado', true);
            $filas[] = [
                'recurso_humano_id' => (int) $recursoId,
                'n' => $suyos->count(),
                'indice_costo' => $promedio > 0 ? round((float) $suyos->avg(fn (stdClass $c): float => (float) $c->costo_total) / $promedio, 3) : null,
                'indice_duracion' => $duracionPromedio > 0 ? round((float) $suyos->avg('duracion') / $duracionPromedio, 3) : null,
                'tasa_complicaciones' => $conResultado->isEmpty() ? null : round($conResultado->where('complicada', true)->count() / $conResultado->count(), 4),
            ];
        }

        usort($filas, fn (array $a, array $b): int => $a['indice_costo'] <=> $b['indice_costo']);

        // Anonimizar después de ordenar: la letra no dice nada de la persona.
        return array_map(function (array $fila, int $i) use ($propio): array {
            $esPropio = $propio !== null && $fila['recurso_humano_id'] === $propio;
            unset($fila['recurso_humano_id']);

            return ['etiqueta' => 'Profesional '.$this->letra($i), 'es_propio' => $esPropio] + $fila;
        }, $filas, array_keys($filas));
    }

    /**
     * Costo promedio y desviación mensual del procedimiento.
     *
     * @param  Collection<int, stdClass>  $casos
     * @return list<array{mes: string, n: int, costo_promedio: float}>
     */
    protected function tendencia(Collection $casos): array
    {
        return array_values($casos
            ->groupBy(fn (stdClass $c): string => substr((string) $c->fecha, 0, 7))
            ->sortKeys()
            ->map(fn (Collection $g, string $mes): array => [
                'mes' => $mes,
                'n' => $g->count(),
                'costo_promedio' => round((float) $g->avg(fn (stdClass $c): float => (float) $c->costo_total), 2),
            ])
            ->all());
    }

    protected function letra(int $i): string
    {
        $letras = range('A', 'Z');

        return $i < 26 ? $letras[$i] : $letras[intdiv($i, 26) - 1].$letras[$i % 26];
    }
}
