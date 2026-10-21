<?php

namespace App\Services\Conocimiento;

use App\Enums\CausaSobrecosto;
use App\Enums\ClasificacionDesviacion;
use App\Enums\EstadoAlerta;
use App\Enums\EstadoLeccion;
use App\Enums\EstadoRecomendacion;
use App\Enums\SeveridadAlerta;
use App\Enums\TipoReglaConocimiento;
use App\Models\Hospital;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de la Capa 4: ¿el hospital está convirtiendo sus datos en
 * conocimiento y el conocimiento en ahorro?
 *
 * La tesis fija una meta explícita —de 2 a 10 lecciones aprendidas al mes—
 * y el resto de cifras responden a la pregunta del comité: cuánto se
 * detectó, cuánto se revisó, cuánto era evitable y cuánto se está
 * recuperando.
 */
class TableroConocimiento
{
    public const META_LECCIONES_MES = 10;

    /** @return array<string, mixed> */
    public function resumen(Hospital $hospital): array
    {
        $hasta = CarbonImmutable::now()->endOfMonth();
        $desde = $hasta->subMonths(5)->startOfMonth();

        return [
            'lecciones' => $this->lecciones($hospital, $desde, $hasta),
            'alertas' => $this->alertas($hospital),
            'sobrecostos' => $this->sobrecostos($hospital),
            'recomendaciones' => $this->recomendaciones($hospital),
            'desviacion' => $this->desviacion($hospital),
            'comunidades' => $this->comunidades($hospital),
            'procedimientos_criticos' => $this->procedimientosCriticos($hospital),
        ];
    }

    /** @return array<string, mixed> */
    protected function lecciones(Hospital $hospital, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $base = DB::table('lecciones_aprendidas')->where('hospital_id', $hospital->id);

        $porMes = (clone $base)
            ->where('estado', EstadoLeccion::Publicada->value)
            ->whereBetween('publicada_en', [$desde, $hasta])
            ->get(['publicada_en'])
            ->groupBy(fn ($f): string => substr((string) $f->publicada_en, 0, 7))
            ->map->count();

        $serie = [];
        for ($mes = $desde; $mes->lessThanOrEqualTo($hasta); $mes = $mes->addMonth()) {
            $clave = $mes->format('Y-m');
            $serie[] = ['mes' => $clave, 'publicadas' => (int) ($porMes[$clave] ?? 0)];
        }

        $porEstado = (clone $base)->groupBy('estado')->selectRaw('estado, count(*) as n')->pluck('n', 'estado');

        return [
            'meta_mensual' => self::META_LECCIONES_MES,
            'publicadas_mes_actual' => (int) ($porMes[$hasta->format('Y-m')] ?? 0),
            'por_mes' => $serie,
            'por_estado' => array_map(fn (EstadoLeccion $e): array => [
                'estado' => $e->value,
                'etiqueta' => $e->etiqueta(),
                'n' => (int) ($porEstado[$e->value] ?? 0),
            ], EstadoLeccion::cases()),
            'compartidas_red' => (clone $base)->where('compartida_red', true)->count(),
            'de_la_red' => DB::table('lecciones_aprendidas')
                ->where('compartida_red', true)
                ->where('estado', EstadoLeccion::Publicada->value)
                ->where('hospital_id', '!=', $hospital->id)
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    protected function alertas(Hospital $hospital): array
    {
        $filas = DB::table('alertas_conocimiento')
            ->where('hospital_id', $hospital->id)
            ->groupBy('tipo', 'severidad', 'estado')
            ->selectRaw('tipo, severidad, estado, count(*) as n, sum(coalesce(impacto_cop, 0)) as impacto')
            ->get();

        $pendientes = $filas->where('estado', EstadoAlerta::Pendiente->value);

        return [
            'pendientes' => (int) $pendientes->sum('n'),
            'impacto_pendiente' => round((float) $pendientes->sum('impacto'), 2),
            'por_severidad' => array_map(fn (SeveridadAlerta $s): array => [
                'severidad' => $s->value,
                'n' => (int) $pendientes->where('severidad', $s->value)->sum('n'),
            ], SeveridadAlerta::cases()),
            'por_tipo' => array_values(array_filter(array_map(fn (TipoReglaConocimiento $t): array => [
                'tipo' => $t->value,
                'nombre' => $t->nombre(),
                'pendientes' => (int) $pendientes->where('tipo', $t->value)->sum('n'),
                'revisadas' => (int) $filas->where('tipo', $t->value)->where('estado', '!=', EstadoAlerta::Pendiente->value)->sum('n'),
                'impacto_pendiente' => round((float) $pendientes->where('tipo', $t->value)->sum('impacto'), 2),
            ], TipoReglaConocimiento::cases()), fn (array $f): bool => $f['pendientes'] + $f['revisadas'] > 0)),
        ];
    }

    /**
     * Alertas de sobrecosto (estadísticas, Capa 3) vistas como conocimiento:
     * cuánto se revisó y cuánto de lo revisado era evitable.
     *
     * @return array<string, mixed>
     */
    protected function sobrecostos(Hospital $hospital): array
    {
        $filas = DB::table('alertas_sobrecosto')
            ->where('hospital_id', $hospital->id)
            ->groupBy('estado', 'causa')
            ->selectRaw('estado, causa, count(*) as n, sum(exceso) as exceso')
            ->get();

        $total = (int) $filas->sum('n');
        $revisadas = $filas->where('estado', EstadoAlerta::Revisada->value);
        $evitables = $revisadas->filter(fn ($f): bool => $f->causa !== null && CausaSobrecosto::from($f->causa)->evitable());

        return [
            'total' => $total,
            'revisadas' => (int) $revisadas->sum('n'),
            'tasa_revision' => $total > 0 ? round($filas->where('estado', '!=', EstadoAlerta::Pendiente->value)->sum('n') / $total, 4) : null,
            'exceso_evitable' => round((float) $evitables->sum('exceso'), 2),
            'exceso_no_evitable' => round((float) $revisadas->sum('exceso') - (float) $evitables->sum('exceso'), 2),
            'por_causa' => $revisadas->whereNotNull('causa')->map(fn ($f): array => [
                'causa' => $f->causa,
                'etiqueta' => CausaSobrecosto::from($f->causa)->etiqueta(),
                'evitable' => CausaSobrecosto::from($f->causa)->evitable(),
                'n' => (int) $f->n,
                'exceso' => round((float) $f->exceso, 2),
            ])->sortByDesc('exceso')->values()->all(),
        ];
    }

    /** @return list<array{estado: string, etiqueta: string, n: int, ahorro_mensual: float}> */
    protected function recomendaciones(Hospital $hospital): array
    {
        $filas = DB::table('recomendaciones')
            ->where('hospital_id', $hospital->id)
            ->groupBy('estado')
            ->selectRaw('estado, count(*) as n, sum(ahorro_mensual_estimado) as ahorro')
            ->pluck('ahorro', 'estado')
            ->all();
        $conteos = DB::table('recomendaciones')
            ->where('hospital_id', $hospital->id)
            ->groupBy('estado')
            ->selectRaw('estado, count(*) as n')
            ->pluck('n', 'estado')
            ->all();

        return array_map(fn (EstadoRecomendacion $e): array => [
            'estado' => $e->value,
            'etiqueta' => $e->etiqueta(),
            'n' => (int) ($conteos[$e->value] ?? 0),
            'ahorro_mensual' => round((float) ($filas[$e->value] ?? 0), 2),
        ], EstadoRecomendacion::cases());
    }

    /**
     * Distribución mensual de la clasificación real vs. estándar.
     *
     * @return list<array<string, int|string>>
     */
    protected function desviacion(Hospital $hospital): array
    {
        $filas = DB::table('costos_cirugia')
            ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
            ->where('costos_cirugia.hospital_id', $hospital->id)
            ->whereNotNull('costos_cirugia.clasificacion_desviacion')
            ->get(['cirugias.fecha', 'costos_cirugia.clasificacion_desviacion'])
            ->groupBy(fn ($f): string => substr((string) $f->fecha, 0, 7))
            ->sortKeys();

        $serie = [];
        foreach ($filas as $mes => $grupo) {
            $fila = ['mes' => (string) $mes];
            foreach (ClasificacionDesviacion::cases() as $c) {
                $fila[$c->value] = $grupo->where('clasificacion_desviacion', $c->value)->count();
            }
            $serie[] = $fila;
        }

        return $serie;
    }

    /** @return array<string, int> */
    protected function comunidades(Hospital $hospital): array
    {
        $hace30 = now()->subDays(30);

        return [
            'comunidades' => DB::table('comunidades_practica')->where('hospital_id', $hospital->id)->count(),
            'hilos_activos' => DB::table('hilos_comunidad')->where('hospital_id', $hospital->id)->where('ultima_actividad_en', '>=', $hace30)->count(),
            'respuestas_30_dias' => DB::table('respuestas_hilo')
                ->join('hilos_comunidad', 'hilos_comunidad.id', '=', 'respuestas_hilo.hilo_comunidad_id')
                ->where('hilos_comunidad.hospital_id', $hospital->id)
                ->where('respuestas_hilo.created_at', '>=', $hace30)
                ->count(),
            'participantes' => DB::table('comunidad_miembros')
                ->join('comunidades_practica', 'comunidades_practica.id', '=', 'comunidad_miembros.comunidad_practica_id')
                ->where('comunidades_practica.hospital_id', $hospital->id)
                ->distinct()
                ->count('comunidad_miembros.user_id'),
        ];
    }

    /**
     * Dónde se concentra el sobrecosto frente al estándar: los cinco
     * procedimientos con más exceso acumulado.
     *
     * @return list<array<string, mixed>>
     */
    protected function procedimientosCriticos(Hospital $hospital): array
    {
        return array_values(DB::table('costos_cirugia')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'costos_cirugia.cirugia_id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->join('procedimientos_quirurgicos', 'procedimientos_quirurgicos.id', '=', 'cirugia_procedimiento.procedimiento_quirurgico_id')
            ->where('costos_cirugia.hospital_id', $hospital->id)
            ->where('costos_cirugia.desviacion', '>', 0)
            ->groupBy('procedimientos_quirurgicos.id', 'procedimientos_quirurgicos.nombre')
            ->selectRaw('procedimientos_quirurgicos.id, procedimientos_quirurgicos.nombre, count(*) as n, sum(costos_cirugia.desviacion) as exceso')
            ->orderByDesc('exceso')
            ->limit(5)
            ->get()
            ->map(fn ($f): array => ['id' => (int) $f->id, 'nombre' => $f->nombre, 'n' => (int) $f->n, 'exceso' => round((float) $f->exceso, 2)])
            ->all());
    }
}
