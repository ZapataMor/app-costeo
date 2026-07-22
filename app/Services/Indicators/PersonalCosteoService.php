<?php

namespace App\Services\Indicators;

use App\Enums\EstadoCirugia;
use App\Enums\RolQuirurgico;
use App\Models\CostoCirugia;
use App\Models\RecursoHumano;
use App\Support\Estadistica;
use App\Support\Periodo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Costo del talento humano visto por persona, no por procedimiento.
 *
 * Responde dos preguntas distintas que conviene no mezclar:
 *
 *  - Costo propio: el costo TDABC de SUS minutos (costo/minuto × minutos de
 *    participación). Es lo que la persona le cuesta al hospital, y se calcula
 *    con la misma fórmula y los mismos snapshots que TdabcCostingService, de
 *    modo que los totales cuadran con `costos_cirugia.costo_recurso_humano`.
 *
 *  - Costo inducido: el costo total de las cirugías donde la persona fue
 *    cirujano (sala + insumos + equipos + todo el equipo + indirectos). Es lo
 *    que moviliza, no lo que cuesta. El mismo peso se le atribuye a cada
 *    cirujano de la cirugía, así que estas cifras NO son sumables entre
 *    personas: se leen persona a persona.
 *
 * El costo inducido bruto castiga a quien opera casos complejos, por eso se
 * acompaña del índice ajustado por case-mix: el costo de cada cirugía dividido
 * por el promedio de SU mismo procedimiento. 1,00 es «en el promedio»; 1,20 es
 * «20 % por encima de lo que cuesta ese procedimiento en este hospital».
 *
 * Todo queda acotado al hospital activo por el HospitalScope de RecursoHumano.
 */
class PersonalCosteoService
{
    /**
     * Mínimo de cirugías costeadas que debe tener un procedimiento para que
     * su promedio sirva de referencia. Con una sola cirugía el índice daría
     * 1,00 por construcción y ensuciaría la comparación.
     */
    public const MINIMO_PARA_COMPARAR = 2;

    protected Periodo $periodo;

    public function __construct()
    {
        $this->periodo = new Periodo;
    }

    public function enPeriodo(Periodo $periodo): static
    {
        $this->periodo = $periodo;

        return $this;
    }

    public function periodo(): Periodo
    {
        return $this->periodo;
    }

    /**
     * Ranking del personal por costo generado.
     *
     * La plantilla de un hospital es de decenas de personas, no de miles, así
     * que se devuelve completa: la vista reordena por cualquier columna sin
     * volver al servidor.
     *
     * @return list<array<string, mixed>>
     */
    public function ranking(string $q = '', string $rol = ''): array
    {
        $propios = $this->costosPropios();
        $inducidos = $this->costosInducidos();

        return RecursoHumano::query()
            ->when($q !== '', fn (Builder $query) => $query->where(
                fn (Builder $sub) => $sub
                    ->where('nombre', 'like', "%{$q}%")
                    ->orWhere('especialidad', 'like', "%{$q}%"),
            ))
            ->when($rol !== '', fn (Builder $query) => $query->where('rol', $rol))
            ->orderBy('nombre')
            ->get()
            ->map(fn (RecursoHumano $recurso): array => [
                ...$this->identidad($recurso),
                ...$this->propioDe($propios, $recurso->id),
                ...$this->inducidoDe($inducidos, $recurso->id),
            ])
            ->sortByDesc('costo_propio_total')
            ->values()
            ->all();
    }

    /**
     * Totales del hospital en el periodo, para dar escala a las cifras
     * individuales del ranking.
     *
     * @return array<string, mixed>
     */
    public function totales(): array
    {
        $propios = $this->costosPropios();

        return [
            'n_personas_con_actividad' => $propios->count(),
            'costo_propio_total' => round((float) $propios->sum('costo_propio'), 2),
            'minutos_total' => (int) $propios->sum('minutos_total'),
        ];
    }

    /**
     * Ficha individual de una persona, con las mismas métricas del ranking
     * más su estructura salarial vigente.
     *
     * @return array<string, mixed>
     */
    public function ficha(RecursoHumano $recurso): array
    {
        return [
            ...$this->identidad($recurso),
            ...$this->propioDe($this->costosPropios($recurso->id), $recurso->id),
            ...$this->inducidoDe($this->costosInducidos($recurso->id), $recurso->id),
            'costo_mensual_actual' => round($recurso->costoMensualTotal(), 2),
            'costo_por_minuto_actual' => round($recurso->costoPorMinuto(), 4),
        ];
    }

    /**
     * Minutos y costo propio abiertos por rol desempeñado y por fase del
     * ciclo: distingue a quien gasta sus minutos operando de quien los gasta
     * preparando o recuperando al paciente.
     *
     * @return array{por_rol: list<array<string, mixed>>, por_fase: list<array<string, mixed>>}
     */
    public function desgloses(RecursoHumano $recurso): array
    {
        $filas = $this->filasDeParticipacion($recurso->id);

        $agrupar = fn (string $campo): array => $filas
            ->groupBy($campo)
            ->map(fn (Collection $grupo, $valor): array => [
                'clave' => (string) $valor,
                'n_participaciones' => $grupo->count(),
                'minutos' => (int) $grupo->sum('minutos_participacion'),
                'costo_propio' => round((float) $grupo->sum('costo_propio'), 2),
            ])
            ->sortByDesc('costo_propio')
            ->values()
            ->all();

        return [
            'por_rol' => $agrupar('rol'),
            'por_fase' => $agrupar('fase'),
        ];
    }

    /**
     * Comparación contra el promedio de cada procedimiento operado como
     * cirujano: dónde está por encima y dónde por debajo del estándar del
     * propio hospital.
     *
     * @return list<array<string, mixed>>
     */
    public function porProcedimiento(RecursoHumano $recurso): array
    {
        $promedios = $this->promediosPorProcedimiento();

        return $this->cirugiasComoCirujano($recurso->id)
            ->groupBy(fn (stdClass $fila): int => (int) $fila->procedimiento_id)
            ->map(function (Collection $grupo, int $procedimientoId) use ($promedios): array {
                $referencia = $promedios->get($procedimientoId);
                $propio = Estadistica::media(
                    $grupo->map(fn (stdClass $f): float => (float) $f->costo_total)->values()->all(),
                );

                return [
                    'procedimiento' => [
                        'id' => $procedimientoId,
                        'codigo_cups' => $grupo->first()->codigo_cups,
                        'nombre' => $grupo->first()->procedimiento_nombre,
                    ],
                    'n' => $grupo->count(),
                    'costo_promedio_suyo' => round($propio, 2),
                    'costo_promedio_hospital' => $referencia !== null
                        ? round($referencia['costo_promedio'], 2)
                        : null,
                    'indice_costo' => $this->indice(
                        $propio,
                        $referencia['costo_promedio'] ?? null,
                        $referencia['n'] ?? 0,
                    ),
                ];
            })
            ->sortByDesc('n')
            ->values()
            ->all();
    }

    /**
     * Registro histórico de sus operaciones: una fila por participación, con
     * los minutos que estuvo, lo que costaron esos minutos y lo que costó la
     * cirugía completa.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function historial(RecursoHumano $recurso, int $porPagina = 15): LengthAwarePaginator
    {
        $promedios = $this->promediosPorProcedimiento();

        return $this->baseParticipaciones($recurso->id)
            ->leftJoin('costos_cirugia', 'costos_cirugia.cirugia_id', '=', 'cirugias.id')
            ->leftJoin('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')
                    ->where('cirugia_procedimiento.es_principal', true);
            })
            ->leftJoin(
                'procedimientos_quirurgicos',
                'procedimientos_quirurgicos.id',
                '=',
                'cirugia_procedimiento.procedimiento_quirurgico_id',
            )
            ->selectRaw(implode(', ', [
                'miembros_equipo_quirurgico.cirugia_id',
                'miembros_equipo_quirurgico.rol',
                'miembros_equipo_quirurgico.fase',
                'miembros_equipo_quirurgico.minutos_participacion',
                'cirugias.fecha',
                'costos_cirugia.costo_total',
                'procedimientos_quirurgicos.id as procedimiento_id',
                'procedimientos_quirurgicos.codigo_cups',
                'procedimientos_quirurgicos.nombre as procedimiento_nombre',
                $this->expresionCostoPropio().' as costo_propio',
                $this->expresionDuracion().' as duracion_cirugia',
            ]))
            ->orderByDesc('cirugias.fecha')
            ->orderByDesc('cirugias.id')
            ->toBase()
            ->paginate($porPagina)
            ->withQueryString()
            ->through(function (stdClass $fila) use ($promedios): array {
                $referencia = $promedios->get((int) $fila->procedimiento_id);
                $costoTotal = $fila->costo_total !== null ? (float) $fila->costo_total : null;

                return [
                    'cirugia_id' => (int) $fila->cirugia_id,
                    'fecha' => $fila->fecha,
                    'procedimiento' => $fila->procedimiento_id !== null ? [
                        'id' => (int) $fila->procedimiento_id,
                        'codigo_cups' => $fila->codigo_cups,
                        'nombre' => $fila->procedimiento_nombre,
                    ] : null,
                    'rol' => $fila->rol,
                    'fase' => $fila->fase,
                    'minutos' => (int) $fila->minutos_participacion,
                    'duracion_cirugia' => $fila->duracion_cirugia !== null
                        ? (int) round((float) $fila->duracion_cirugia)
                        : null,
                    'costo_propio' => round((float) $fila->costo_propio, 2),
                    'costo_total_cirugia' => $costoTotal,
                    'indice_costo' => $costoTotal !== null
                        ? $this->indice(
                            $costoTotal,
                            $referencia['costo_promedio'] ?? null,
                            $referencia['n'] ?? 0,
                        )
                        : null,
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function identidad(RecursoHumano $recurso): array
    {
        return [
            'id' => $recurso->id,
            'nombre' => $recurso->nombre,
            'rol' => $recurso->rol,
            'especialidad' => $recurso->especialidad,
            'activo' => $recurso->activo,
        ];
    }

    /**
     * @param  Collection<int, stdClass>  $propios
     * @return array<string, mixed>
     */
    protected function propioDe(Collection $propios, int $recursoId): array
    {
        $fila = $propios->get($recursoId);
        $nCirugias = (int) ($fila->n_cirugias ?? 0);
        $minutos = (int) ($fila->minutos_total ?? 0);
        $costo = round((float) ($fila->costo_propio ?? 0), 2);

        return [
            'n_cirugias' => $nCirugias,
            'n_participaciones' => (int) ($fila->n_participaciones ?? 0),
            'minutos_total' => $minutos,
            'minutos_promedio' => $nCirugias > 0 ? (int) round($minutos / $nCirugias) : null,
            'costo_propio_total' => $costo,
            'costo_propio_promedio' => $nCirugias > 0 ? round($costo / $nCirugias, 2) : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $inducidos
     * @return array<string, mixed>
     */
    protected function inducidoDe(Collection $inducidos, int $recursoId): array
    {
        $fila = $inducidos->get($recursoId, []);

        return [
            'n_como_cirujano' => $fila['n'] ?? 0,
            'costo_inducido_total' => $fila['costo_total'] ?? null,
            'costo_inducido_promedio' => $fila['costo_promedio'] ?? null,
            'indice_costo' => $fila['indice_costo'] ?? null,
            'indice_duracion' => $fila['indice_duracion'] ?? null,
            'n_comparables' => $fila['n_comparables'] ?? 0,
        ];
    }

    /**
     * Costo propio agregado por persona. La fórmula replica exactamente la de
     * TdabcCostingService —incluido el redondeo a dos decimales por línea—
     * para que los totales cuadren con el detalle de cada cirugía.
     *
     * @return Collection<int, stdClass>
     */
    protected function costosPropios(?int $recursoId = null): Collection
    {
        return $this->baseParticipaciones($recursoId)
            ->groupBy('recursos_humanos.id')
            ->selectRaw(implode(', ', [
                'recursos_humanos.id as recurso_humano_id',
                'count(distinct miembros_equipo_quirurgico.cirugia_id) as n_cirugias',
                'count(*) as n_participaciones',
                'sum(miembros_equipo_quirurgico.minutos_participacion) as minutos_total',
                'sum('.$this->expresionCostoPropio().') as costo_propio',
            ]))
            ->toBase()
            ->get()
            ->keyBy(fn (stdClass $fila): int => (int) $fila->recurso_humano_id);
    }

    /**
     * Una fila por participación, con su costo propio ya calculado.
     *
     * @return Collection<int, stdClass>
     */
    protected function filasDeParticipacion(int $recursoId): Collection
    {
        return $this->baseParticipaciones($recursoId)
            ->selectRaw(implode(', ', [
                'miembros_equipo_quirurgico.cirugia_id',
                'miembros_equipo_quirurgico.rol',
                'miembros_equipo_quirurgico.fase',
                'miembros_equipo_quirurgico.minutos_participacion',
                $this->expresionCostoPropio().' as costo_propio',
            ]))
            ->toBase()
            ->get();
    }

    /**
     * Costo inducido e índices ajustados por case-mix, solo sobre cirugías en
     * las que la persona figura como cirujano.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function costosInducidos(?int $recursoId = null): Collection
    {
        $promedios = $this->promediosPorProcedimiento();

        $comparable = fn (stdClass $fila): bool => ($promedios
            ->get((int) $fila->procedimiento_id)['n'] ?? 0) >= self::MINIMO_PARA_COMPARAR;

        return $this->cirugiasComoCirujano($recursoId)
            ->groupBy(fn (stdClass $fila): int => (int) $fila->recurso_humano_id)
            ->map(function (Collection $grupo) use ($promedios, $comparable): array {
                $costos = $grupo->map(fn (stdClass $f): float => (float) $f->costo_total)
                    ->values()->all();

                $comparables = $grupo->filter($comparable);

                $razonesCosto = $comparables
                    ->map(fn (stdClass $f): float => (float) $f->costo_total
                        / $promedios->get((int) $f->procedimiento_id)['costo_promedio'])
                    ->values()->all();

                $razonesDuracion = $comparables
                    ->filter(fn (stdClass $f): bool => (float) $f->duracion_minutos > 0
                        && ($promedios->get((int) $f->procedimiento_id)['duracion_promedio'] ?? 0) > 0)
                    ->map(fn (stdClass $f): float => (float) $f->duracion_minutos
                        / $promedios->get((int) $f->procedimiento_id)['duracion_promedio'])
                    ->values()->all();

                return [
                    'n' => $grupo->count(),
                    'costo_total' => round(array_sum($costos), 2),
                    'costo_promedio' => round(Estadistica::media($costos), 2),
                    'n_comparables' => count($razonesCosto),
                    'indice_costo' => $razonesCosto === []
                        ? null
                        : round(Estadistica::media($razonesCosto), 3),
                    'indice_duracion' => $razonesDuracion === []
                        ? null
                        : round(Estadistica::media($razonesDuracion), 3),
                ];
            });
    }

    /**
     * Cirugías costeadas donde la persona actuó como cirujano, con su
     * procedimiento principal y su duración real.
     *
     * @return Collection<int, stdClass>
     */
    protected function cirugiasComoCirujano(?int $recursoId = null): Collection
    {
        return $this->baseParticipaciones($recursoId)
            ->where('miembros_equipo_quirurgico.rol', RolQuirurgico::Cirujano->value)
            ->join('costos_cirugia', 'costos_cirugia.cirugia_id', '=', 'cirugias.id')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')
                    ->where('cirugia_procedimiento.es_principal', true);
            })
            ->join(
                'procedimientos_quirurgicos',
                'procedimientos_quirurgicos.id',
                '=',
                'cirugia_procedimiento.procedimiento_quirurgico_id',
            )
            ->selectRaw(implode(', ', [
                'recursos_humanos.id as recurso_humano_id',
                'cirugias.id as cirugia_id',
                'costos_cirugia.costo_total',
                'procedimientos_quirurgicos.id as procedimiento_id',
                'procedimientos_quirurgicos.codigo_cups',
                'procedimientos_quirurgicos.nombre as procedimiento_nombre',
                $this->expresionDuracion().' as duracion_minutos',
            ]))
            ->toBase()
            ->get();
    }

    /**
     * Promedio de costo y de duración de cada procedimiento en el periodo: la
     * referencia contra la que se ajusta por case-mix. Usa el mismo criterio
     * de agrupación que KpiService (procedimiento principal, solo cirugías
     * contabilizables) para que los números coincidan entre pantallas.
     *
     * @return Collection<int, array{n: int, costo_promedio: float, duracion_promedio: float|null}>
     */
    protected function promediosPorProcedimiento(): Collection
    {
        return $this->acotarAlPeriodo(
            CostoCirugia::query()
                ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
                ->where('cirugias.estado', EstadoCirugia::Realizada->value)
                ->whereNotNull('cirugias.hora_fin'),
        )
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')
                    ->where('cirugia_procedimiento.es_principal', true);
            })
            ->groupBy('cirugia_procedimiento.procedimiento_quirurgico_id')
            ->selectRaw(implode(', ', [
                'cirugia_procedimiento.procedimiento_quirurgico_id as procedimiento_id',
                'count(*) as n',
                'avg(costos_cirugia.costo_total) as costo_promedio',
                'avg('.$this->expresionDuracion().') as duracion_promedio',
            ]))
            ->toBase()
            ->get()
            ->mapWithKeys(fn (stdClass $fila): array => [
                (int) $fila->procedimiento_id => [
                    'n' => (int) $fila->n,
                    'costo_promedio' => (float) $fila->costo_promedio,
                    'duracion_promedio' => $fila->duracion_promedio !== null
                        ? (float) $fila->duracion_promedio
                        : null,
                ],
            ]);
    }

    /**
     * Base común: participaciones contabilizables (cirugía realizada y
     * terminada) del hospital activo, sin columnas seleccionadas para que
     * cada consumidor arme su propio `select`. El HospitalScope de
     * RecursoHumano acota la consulta al hospital en contexto.
     *
     * @return Builder<RecursoHumano>
     */
    protected function baseParticipaciones(?int $recursoId = null): Builder
    {
        return $this->acotarAlPeriodo(
            RecursoHumano::query()
                ->join(
                    'miembros_equipo_quirurgico',
                    'miembros_equipo_quirurgico.recurso_humano_id',
                    '=',
                    'recursos_humanos.id',
                )
                ->join('cirugias', 'cirugias.id', '=', 'miembros_equipo_quirurgico.cirugia_id')
                ->join('hospitales', 'hospitales.id', '=', 'recursos_humanos.hospital_id')
                ->where('cirugias.estado', EstadoCirugia::Realizada->value)
                ->whereNotNull('cirugias.hora_fin')
                ->when($recursoId !== null, fn (Builder $query) => $query
                    ->where('recursos_humanos.id', $recursoId)),
        );
    }

    /**
     * costo/minuto × minutos, con los snapshots congelados en el registro y el
     * redondeo por línea de TdabcCostingService. Los `coalesce` cubren las
     * cirugías anteriores al snapshot, que caen en las tarifas vigentes.
     */
    protected function expresionCostoPropio(): string
    {
        $costoMensual = 'coalesce(miembros_equipo_quirurgico.costo_mensual_registrado, '
            .'recursos_humanos.salario_mensual + recursos_humanos.prestaciones_mensuales '
            .'+ recursos_humanos.costos_indirectos_mensuales)';

        $minutosDisponibles = 'coalesce(cirugias.minutos_disponibles_mes_registrado, '
            .'hospitales.horas_dia * hospitales.dias_mes * 60)';

        return "round({$costoMensual} * miembros_equipo_quirurgico.minutos_participacion "
            ."/ {$minutosDisponibles}, 2)";
    }

    /** Duración real de la cirugía en minutos, en el dialecto activo. */
    protected function expresionDuracion(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => '(julianday(cirugias.hora_fin) - julianday(cirugias.hora_inicio)) * 1440',
            'pgsql' => 'extract(epoch from (cirugias.hora_fin - cirugias.hora_inicio)) / 60',
            default => 'timestampdiff(minute, cirugias.hora_inicio, cirugias.hora_fin)',
        };
    }

    /**
     * Índice ajustado por case-mix. Null cuando el procedimiento no tiene
     * cirugías suficientes para que su promedio signifique algo.
     */
    protected function indice(float $valor, ?float $referencia, int $n): ?float
    {
        if ($referencia === null || $referencia <= 0 || $n < self::MINIMO_PARA_COMPARAR) {
            return null;
        }

        return round($valor / $referencia, 3);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $consulta
     * @return Builder<TModel>
     */
    protected function acotarAlPeriodo(Builder $consulta): Builder
    {
        if ($this->periodo->desde !== null) {
            $consulta->whereDate('cirugias.fecha', '>=', $this->periodo->desde->toDateString());
        }

        if ($this->periodo->hasta !== null) {
            $consulta->whereDate('cirugias.fecha', '<=', $this->periodo->hasta->toDateString());
        }

        return $consulta;
    }
}
