<?php

namespace App\Services\Conocimiento;

use App\Enums\EstadoCirugia;
use App\Enums\EstadoRecomendacion;
use App\Enums\TipoRecomendacion;
use App\Models\Hospital;
use App\Models\Recomendacion;
use App\Models\Scopes\HospitalScope;
use App\Services\Indicators\KpiService;
use App\Support\Estadistica;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Deriva recomendaciones accionables del histórico reciente del hospital
 * (minería descriptiva: qué pasó y cuánto cuesta, no predicción).
 *
 * Ventana: los últimos seis meses con actividad. Cada recomendación trae su
 * ahorro mensual estimado —la cifra con la que un comité decide— y la
 * evidencia que lo sostiene, para que nadie tenga que creerle al sistema.
 *
 * Idempotente por clave. Solo reescribe las `nuevas`: una recomendación que
 * alguien aceptó, implementó o descartó conserva la evidencia con la que se
 * decidió. Las nuevas que el histórico ya no sostiene se retiran.
 */
class GeneradorRecomendaciones
{
    /** Meses de histórico analizados. */
    public const MESES_VENTANA = 6;

    /** Casos mínimos para que un promedio signifique algo. */
    public const MINIMO_CASOS = 10;

    /** Ahorro mensual mínimo para que valga la pena recomendar. */
    public const AHORRO_MINIMO = 100_000;

    private int $hospitalId;

    private CarbonImmutable $desde;

    private CarbonImmutable $hasta;

    private float $meses;

    /** @var array<string, true> */
    private array $generadas = [];

    /** @var Collection<int, stdClass>|null */
    private ?Collection $consumo = null;

    /** @var Collection<int, stdClass>|null */
    private ?Collection $costos = null;

    /**
     * @return array{generadas: int, ahorro_mensual: float}
     */
    public function generar(Hospital $hospital): array
    {
        $this->hospitalId = $hospital->id;
        $this->generadas = [];
        $this->consumo = null;
        $this->costos = null;

        $ultima = DB::table('cirugias')
            ->where('hospital_id', $hospital->id)
            ->where('estado', EstadoCirugia::Realizada->value)
            ->max('fecha');

        if ($ultima === null) {
            return ['generadas' => 0, 'ahorro_mensual' => 0.0];
        }

        $this->hasta = CarbonImmutable::parse((string) $ultima)->endOfDay();
        $this->desde = $this->hasta->subMonths(self::MESES_VENTANA)->startOfDay();
        $this->meses = max(1.0, $this->desde->diffInDays($this->hasta) / 30.4375);

        DB::transaction(function () use ($hospital): void {
            $this->estandarizarInsumos();
            $this->ajustarProtocolos();
            $this->renegociarTarifas();
            $this->reducirTiempos();
            $this->reasignarCapacidad($hospital);
            $this->revisionEntrePares();
            $this->retirarObsoletas();
        });

        $vigentes = Recomendacion::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->where('estado', EstadoRecomendacion::Nueva->value);

        return [
            'generadas' => count($this->generadas),
            'ahorro_mensual' => round((float) $vigentes->sum('ahorro_mensual_estimado'), 2),
        ];
    }

    // ── Insumos ───────────────────────────────────────────────────────────

    /**
     * Insumos del protocolo que se consumen sistemáticamente por encima de
     * lo previsto: el exceso promedio × precio × casos al mes.
     */
    protected function estandarizarInsumos(): void
    {
        foreach ($this->consumoPorProcedimientoEInsumo() as $fila) {
            if ($fila->cantidad_plantilla === null || (int) $fila->n_cirugias_procedimiento < self::MINIMO_CASOS) {
                continue;
            }

            $plantilla = (float) $fila->cantidad_plantilla;
            // Promedio sobre las cirugías del procedimiento: las que no lo
            // usaron cuentan como cero.
            $promedio = (float) $fila->cantidad_total / (int) $fila->n_cirugias_procedimiento;

            if ($plantilla <= 0 || $promedio <= $plantilla * 1.15) {
                continue;
            }

            $casosMes = (int) $fila->n_cirugias_procedimiento / $this->meses;
            $ahorro = ($promedio - $plantilla) * (float) $fila->precio * $casosMes;

            if ($ahorro < self::AHORRO_MINIMO) {
                continue;
            }

            $this->guardar(TipoRecomendacion::EstandarizarInsumo, "insumo:{$fila->procedimiento_id}:{$fila->insumo_id}", [
                'titulo' => sprintf('%s: llevar %s a lo previsto en el protocolo', $fila->procedimiento, $fila->insumo),
                'descripcion' => sprintf(
                    'El protocolo prevé %s %s por cirugía; en los últimos meses se consumen en promedio %s (%s más). Revisar con el equipo si el exceso es desperdicio, preparación anticipada o un protocolo desactualizado.',
                    $this->numero($plantilla),
                    $fila->unidad,
                    $this->numero($promedio),
                    $this->porcentaje($promedio / $plantilla - 1),
                ),
                'procedimiento_quirurgico_id' => $fila->procedimiento_id,
                'insumo_id' => $fila->insumo_id,
                'ahorro_mensual_estimado' => round($ahorro, 2),
                'evidencia' => [
                    'cantidad_protocolo' => $plantilla,
                    'cantidad_promedio' => round($promedio, 2),
                    'precio_unitario' => (float) $fila->precio,
                    'cirugias' => (int) $fila->n_cirugias_procedimiento,
                    'casos_mes' => round($casosMes, 1),
                ],
            ]);
        }
    }

    /**
     * Protocolos que no describen la práctica real: insumos que casi siempre
     * se usan y no están, o que están y casi nunca se usan. Ninguno ahorra
     * por sí mismo, pero un protocolo fiel es la base del costo estándar.
     */
    protected function ajustarProtocolos(): void
    {
        $porProcedimiento = $this->consumoPorProcedimientoEInsumo()->groupBy('procedimiento_id');

        foreach ($porProcedimiento as $procedimientoId => $filas) {
            /** @var stdClass $primera */
            $primera = $filas->first();
            $n = (int) $primera->n_cirugias_procedimiento;

            if ($n < self::MINIMO_CASOS) {
                continue;
            }

            $agregar = $filas->filter(fn (stdClass $f): bool => $f->cantidad_plantilla === null && (int) $f->n_cirugias_insumo / $n >= 0.6);
            $quitar = $filas->filter(fn (stdClass $f): bool => $f->cantidad_plantilla !== null && (int) $f->n_cirugias_insumo / $n < 0.3);

            if ($agregar->isEmpty() && $quitar->isEmpty()) {
                continue;
            }

            $describir = fn (Collection $grupo): string => $grupo
                ->map(fn (stdClass $f): string => sprintf('%s (%s de los casos)', $f->insumo, $this->porcentaje((int) $f->n_cirugias_insumo / $n, false)))
                ->implode('; ');

            $partes = [];
            if ($agregar->isNotEmpty()) {
                $partes[] = 'Incorporar: '.$describir($agregar).'.';
            }
            if ($quitar->isNotEmpty()) {
                $partes[] = 'Retirar o marcar como opcional: '.$describir($quitar).'.';
            }

            $this->guardar(TipoRecomendacion::AjustarProtocolo, "protocolo:{$procedimientoId}", [
                'titulo' => sprintf('%s: el protocolo no refleja la práctica real', $primera->procedimiento),
                'descripcion' => implode(' ', $partes).' Un protocolo fiel hace confiable el costo estándar y la preparación de la sala.',
                'procedimiento_quirurgico_id' => (int) $procedimientoId,
                'ahorro_mensual_estimado' => 0,
                'evidencia' => [
                    'cirugias' => $n,
                    'agregar' => $agregar->map(fn (stdClass $f): array => ['insumo_id' => (int) $f->insumo_id, 'insumo' => $f->insumo, 'frecuencia' => round((int) $f->n_cirugias_insumo / $n, 3)])->values()->all(),
                    'quitar' => $quitar->map(fn (stdClass $f): array => ['insumo_id' => (int) $f->insumo_id, 'insumo' => $f->insumo, 'frecuencia' => round((int) $f->n_cirugias_insumo / $n, 3)])->values()->all(),
                ],
            ]);
        }
    }

    /**
     * Consumo agregado de la ventana por procedimiento principal e insumo,
     * con la cantidad del protocolo (si la hay).
     *
     * @return Collection<int, stdClass>
     */
    protected function consumoPorProcedimientoEInsumo(): Collection
    {
        if ($this->consumo !== null) {
            return $this->consumo;
        }

        $casos = DB::table('cirugias')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->where('cirugias.hospital_id', $this->hospitalId)
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereBetween('cirugias.fecha', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->groupBy('cirugia_procedimiento.procedimiento_quirurgico_id')
            ->selectRaw('cirugia_procedimiento.procedimiento_quirurgico_id as procedimiento_id, count(*) as n')
            ->pluck('n', 'procedimiento_id');

        $filas = DB::table('consumo_insumos')
            ->join('cirugias', 'cirugias.id', '=', 'consumo_insumos.cirugia_id')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->join('procedimientos_quirurgicos', 'procedimientos_quirurgicos.id', '=', 'cirugia_procedimiento.procedimiento_quirurgico_id')
            ->join('insumos', 'insumos.id', '=', 'consumo_insumos.insumo_id')
            ->where('cirugias.hospital_id', $this->hospitalId)
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereBetween('cirugias.fecha', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->groupBy('procedimientos_quirurgicos.id', 'procedimientos_quirurgicos.nombre', 'insumos.id', 'insumos.nombre', 'insumos.unidad')
            ->selectRaw('procedimientos_quirurgicos.id as procedimiento_id, procedimientos_quirurgicos.nombre as procedimiento, '
                .'insumos.id as insumo_id, insumos.nombre as insumo, insumos.unidad, '
                .'count(distinct cirugias.id) as n_cirugias_insumo, sum(consumo_insumos.cantidad) as cantidad_total, '
                .'avg(consumo_insumos.costo_unitario_registrado) as precio')
            ->get();

        $plantilla = DB::table('plantilla_insumos')
            ->join('procedimientos_quirurgicos', 'procedimientos_quirurgicos.id', '=', 'plantilla_insumos.procedimiento_quirurgico_id')
            ->where('procedimientos_quirurgicos.hospital_id', $this->hospitalId)
            ->where('plantilla_insumos.opcional', false)
            ->groupBy('plantilla_insumos.procedimiento_quirurgico_id', 'plantilla_insumos.insumo_id')
            ->selectRaw('plantilla_insumos.procedimiento_quirurgico_id as p, plantilla_insumos.insumo_id as i, sum(plantilla_insumos.cantidad) as c')
            ->get()
            ->mapWithKeys(fn (stdClass $f): array => [$f->p.':'.$f->i => (float) $f->c]);

        foreach ($filas as $fila) {
            $fila->n_cirugias_procedimiento = (int) ($casos[$fila->procedimiento_id] ?? 0);
            $fila->cantidad_plantilla = $plantilla[$fila->procedimiento_id.':'.$fila->insumo_id] ?? null;
        }

        return $this->consumo = $filas;
    }

    // ── Tarifas y tiempos ─────────────────────────────────────────────────

    protected function renegociarTarifas(): void
    {
        foreach ($this->costosPorProcedimiento() as $fila) {
            if ($fila->tarifa_soat === null || (int) $fila->n < self::MINIMO_CASOS) {
                continue;
            }

            $referencia = (float) $fila->tarifa_soat * KpiService::FACTOR_REFERENCIA_SOAT;
            $costo = (float) $fila->costo_promedio;

            if ($referencia <= 0 || $costo < $referencia * 1.05) {
                continue;
            }

            $casosMes = (int) $fila->n / $this->meses;
            $perdida = ($costo - $referencia) * $casosMes;

            if ($perdida < self::AHORRO_MINIMO) {
                continue;
            }

            $this->guardar(TipoRecomendacion::RenegociarTarifa, "tarifa:{$fila->id}", [
                'titulo' => sprintf('%s: el costo real supera la tarifa en %s', $fila->nombre, $this->porcentaje($costo / $referencia - 1)),
                'descripcion' => sprintf(
                    'Con %d casos en la ventana, el costo promedio es %s frente a una tarifa de referencia de %s (SOAT −25 %%). Llevar esta evidencia a la negociación con las EPS o revisar si el protocolo incluye insumos que se facturan aparte. Pérdida estimada: %s al mes.',
                    (int) $fila->n,
                    $this->cop($costo),
                    $this->cop($referencia),
                    $this->cop($perdida),
                ),
                'procedimiento_quirurgico_id' => (int) $fila->id,
                'ahorro_mensual_estimado' => round($perdida, 2),
                'evidencia' => [
                    'cirugias' => (int) $fila->n,
                    'costo_promedio' => round($costo, 2),
                    'costo_mediano' => round($this->medianaCosto((int) $fila->id), 2),
                    'tarifa_soat' => (float) $fila->tarifa_soat,
                    'tarifa_referencia' => round($referencia, 2),
                    'casos_mes' => round($casosMes, 1),
                ],
            ]);
        }
    }

    /**
     * Procedimientos cuyo tiempo de sala mediano supera el estimado: cada
     * minuto extra vale el costo por minuto promedio de sala, personal y
     * equipos de ese procedimiento.
     */
    protected function reducirTiempos(): void
    {
        foreach ($this->costosPorProcedimiento() as $fila) {
            if ((int) $fila->n < self::MINIMO_CASOS || (int) $fila->duracion_estimada_minutos <= 0) {
                continue;
            }

            $duraciones = $this->duraciones((int) $fila->id);
            $mediana = Estadistica::percentil($duraciones, 0.5);
            $estimada = (int) $fila->duracion_estimada_minutos;

            if ($mediana <= $estimada * 1.15 || (float) $fila->minutos_promedio <= 0) {
                continue;
            }

            $costoMinuto = (float) $fila->capacidad_promedio / (float) $fila->minutos_promedio;
            $casosMes = (int) $fila->n / $this->meses;
            $ahorro = ($mediana - $estimada) * $costoMinuto * $casosMes;

            if ($ahorro < self::AHORRO_MINIMO) {
                continue;
            }

            $this->guardar(TipoRecomendacion::ReducirTiempo, "tiempo:{$fila->id}", [
                'titulo' => sprintf('%s: la mitad de los casos pasa de %d min de sala', $fila->nombre, (int) round($mediana)),
                'descripcion' => sprintf(
                    'La duración estimada es %d min y la mediana real %d min. Revisar alistamiento, inducción y recambio (o actualizar la duración estimada si el estándar está mal fijado). Cada minuto cuesta %s entre sala, personal y equipos.',
                    $estimada,
                    (int) round($mediana),
                    $this->cop($costoMinuto),
                ),
                'procedimiento_quirurgico_id' => (int) $fila->id,
                'ahorro_mensual_estimado' => round($ahorro, 2),
                'evidencia' => [
                    'cirugias' => (int) $fila->n,
                    'duracion_estimada' => $estimada,
                    'duracion_mediana' => round($mediana, 1),
                    'duracion_p75' => round(Estadistica::percentil($duraciones, 0.75), 1),
                    'costo_minuto' => round($costoMinuto, 2),
                    'casos_mes' => round($casosMes, 1),
                ],
            ]);
        }
    }

    /** @return Collection<int, stdClass> */
    protected function costosPorProcedimiento(): Collection
    {
        return $this->costos ??= DB::table('costos_cirugia')
            ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->join('procedimientos_quirurgicos', 'procedimientos_quirurgicos.id', '=', 'cirugia_procedimiento.procedimiento_quirurgico_id')
            ->where('cirugias.hospital_id', $this->hospitalId)
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereBetween('cirugias.fecha', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->groupBy('procedimientos_quirurgicos.id', 'procedimientos_quirurgicos.nombre', 'procedimientos_quirurgicos.tarifa_soat', 'procedimientos_quirurgicos.duracion_estimada_minutos')
            ->selectRaw('procedimientos_quirurgicos.id, procedimientos_quirurgicos.nombre, procedimientos_quirurgicos.tarifa_soat, '
                .'procedimientos_quirurgicos.duracion_estimada_minutos, count(*) as n, avg(costos_cirugia.costo_total) as costo_promedio, '
                .'avg(costos_cirugia.costo_sala + costos_cirugia.costo_recurso_humano + costos_cirugia.costo_equipos) as capacidad_promedio, '
                ."avg(json_extract(costos_cirugia.detalle, '$.sala.minutos')) as minutos_promedio")
            ->get();
    }

    /** @return list<float> */
    protected function duraciones(int $procedimientoId): array
    {
        return array_values(DB::table('cirugias')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->where('cirugias.hospital_id', $this->hospitalId)
            ->where('cirugia_procedimiento.procedimiento_quirurgico_id', $procedimientoId)
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('cirugias.hora_fin')
            ->whereBetween('cirugias.fecha', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->get(['cirugias.hora_inicio', 'cirugias.hora_fin'])
            ->map(fn (stdClass $c): float => (float) CarbonImmutable::parse($c->hora_inicio)->diffInMinutes(CarbonImmutable::parse($c->hora_fin)))
            ->all());
    }

    protected function medianaCosto(int $procedimientoId): float
    {
        $costos = DB::table('costos_cirugia')
            ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->where('cirugias.hospital_id', $this->hospitalId)
            ->where('cirugia_procedimiento.procedimiento_quirurgico_id', $procedimientoId)
            ->whereBetween('cirugias.fecha', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->pluck('costos_cirugia.costo_total')
            ->map(fn ($v): float => (float) $v)
            ->values()
            ->all();

        return Estadistica::percentil($costos, 0.5);
    }

    // ── Capacidad y profesionales ─────────────────────────────────────────

    /**
     * Salas con utilización baja sostenida en los tres últimos meses
     * cerrados: su capacidad ociosa valorada es lo que se recupera
     * consolidando la programación o reasignándola.
     */
    protected function reasignarCapacidad(Hospital $hospital): void
    {
        $fin = $this->hasta->startOfMonth()->subDay()->endOfDay();
        $inicio = $fin->subMonths(2)->startOfMonth();
        $capacidad = $hospital->minutosDisponiblesEntre($inicio, $fin);

        if ($capacidad <= 0) {
            return;
        }

        $salas = DB::table('salas_operatorias')
            ->where('hospital_id', $this->hospitalId)
            ->where('activa', true)
            ->get(['id', 'nombre', 'costo_hora']);

        foreach ($salas as $sala) {
            $usados = DB::table('cirugias')
                ->where('hospital_id', $this->hospitalId)
                ->where('sala_operatoria_id', $sala->id)
                ->where('estado', EstadoCirugia::Realizada->value)
                ->whereNotNull('hora_fin')
                ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
                ->get(['hora_inicio', 'hora_fin'])
                ->sum(fn (stdClass $c): float => (float) CarbonImmutable::parse($c->hora_inicio)->diffInMinutes(CarbonImmutable::parse($c->hora_fin)));

            $utilizacion = $usados / $capacidad;

            if ($utilizacion >= 0.30) {
                continue;
            }

            $ociosoMes = ($capacidad - $usados) / 3;
            $valor = $ociosoMes * (float) $sala->costo_hora / 60;

            $this->guardar(TipoRecomendacion::ReasignarCapacidad, "sala:{$sala->id}", [
                'titulo' => sprintf('%s: %s de utilización en los últimos tres meses', $sala->nombre, $this->porcentaje($utilizacion, false)),
                'descripcion' => sprintf(
                    'La sala deja ociosos unos %s minutos al mes, valorados en %s a su costo/hora. Consolidar la programación en menos franjas o asignar la sala a la especialidad con lista de espera recupera esa capacidad.',
                    number_format($ociosoMes, 0, ',', '.'),
                    $this->cop($valor),
                ),
                'sala_operatoria_id' => (int) $sala->id,
                'ahorro_mensual_estimado' => round($valor, 2),
                'evidencia' => [
                    'utilizacion' => round($utilizacion, 4),
                    'minutos_usados' => (int) $usados,
                    'minutos_disponibles' => $capacidad,
                    'desde' => $inicio->toDateString(),
                    'hasta' => $fin->toDateString(),
                ],
            ]);
        }
    }

    /**
     * Profesionales cuyo costo promedio en un procedimiento supera en un
     * 20 % o más el del hospital. Se plantea como revisión entre pares —no
     * como sanción—, con el nombre solo en la evidencia, que es interna.
     */
    protected function revisionEntrePares(): void
    {
        $filas = DB::table('miembros_equipo_quirurgico')
            ->join('cirugias', 'cirugias.id', '=', 'miembros_equipo_quirurgico.cirugia_id')
            ->join('costos_cirugia', 'costos_cirugia.cirugia_id', '=', 'cirugias.id')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->join('procedimientos_quirurgicos', 'procedimientos_quirurgicos.id', '=', 'cirugia_procedimiento.procedimiento_quirurgico_id')
            ->join('recursos_humanos', 'recursos_humanos.id', '=', 'miembros_equipo_quirurgico.recurso_humano_id')
            ->where('cirugias.hospital_id', $this->hospitalId)
            ->where('miembros_equipo_quirurgico.rol', 'cirujano')
            ->whereBetween('cirugias.fecha', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->groupBy('procedimientos_quirurgicos.id', 'procedimientos_quirurgicos.nombre', 'recursos_humanos.id', 'recursos_humanos.nombre')
            ->selectRaw('procedimientos_quirurgicos.id as procedimiento_id, procedimientos_quirurgicos.nombre as procedimiento, '
                .'recursos_humanos.id as recurso_id, recursos_humanos.nombre as recurso, count(distinct cirugias.id) as n, avg(costos_cirugia.costo_total) as costo')
            ->get();

        $promedios = $this->costosPorProcedimiento()->keyBy('id');

        foreach ($filas as $fila) {
            $referencia = $promedios->get($fila->procedimiento_id);

            if ($referencia === null || (int) $fila->n < 8 || (int) $referencia->n < self::MINIMO_CASOS) {
                continue;
            }

            $indice = (float) $fila->costo / (float) $referencia->costo_promedio;

            if ($indice < 1.20) {
                continue;
            }

            $casosMes = (int) $fila->n / $this->meses;
            $ahorro = ((float) $fila->costo - (float) $referencia->costo_promedio) * $casosMes;

            if ($ahorro < self::AHORRO_MINIMO) {
                continue;
            }

            $this->guardar(TipoRecomendacion::RevisionEntrePares, "pares:{$fila->procedimiento_id}:{$fila->recurso_id}", [
                'titulo' => sprintf('%s: un profesional opera %s por encima del promedio', $fila->procedimiento, $this->porcentaje($indice - 1)),
                'descripcion' => 'Proponer una revisión de casos entre pares en la comunidad de práctica: comparar tiempos, insumos y técnica con los equipos de menor costo y buen resultado. El objetivo es aprender, no sancionar.',
                'procedimiento_quirurgico_id' => (int) $fila->procedimiento_id,
                'recurso_humano_id' => (int) $fila->recurso_id,
                'ahorro_mensual_estimado' => round($ahorro, 2),
                'evidencia' => [
                    'profesional' => $fila->recurso,
                    'cirugias' => (int) $fila->n,
                    'costo_promedio_profesional' => round((float) $fila->costo, 2),
                    'costo_promedio_hospital' => round((float) $referencia->costo_promedio, 2),
                    'indice' => round($indice, 3),
                ],
            ]);
        }
    }

    // ── Persistencia ──────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function guardar(TipoRecomendacion $tipo, string $clave, array $atributos): void
    {
        $this->generadas[$clave] = true;

        $existente = Recomendacion::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $this->hospitalId)
            ->where('clave', $clave)
            ->first();

        if ($existente !== null && $existente->estado !== EstadoRecomendacion::Nueva) {
            return;
        }

        $valores = $atributos + [
            'hospital_id' => $this->hospitalId,
            'tipo' => $tipo,
            'clave' => $clave,
            'generada_en' => now(),
        ];

        if ($existente !== null) {
            $existente->update($valores);

            return;
        }

        Recomendacion::query()->withoutGlobalScope(HospitalScope::class)->create($valores);
    }

    protected function retirarObsoletas(): void
    {
        Recomendacion::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $this->hospitalId)
            ->where('estado', EstadoRecomendacion::Nueva->value)
            ->whereNotIn('clave', array_keys($this->generadas) ?: ['—'])
            ->delete();
    }

    protected function cop(float $valor): string
    {
        return '$'.number_format($valor, 0, ',', '.');
    }

    protected function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
    }

    protected function porcentaje(float $valor, bool $signo = true): string
    {
        $texto = number_format($valor * 100, 1, ',', '.').' %';

        return $signo && $valor > 0 ? '+'.$texto : $texto;
    }
}
