<?php

namespace App\Services\Conocimiento;

use App\Enums\ClasificacionDesviacion;
use App\Enums\EstadoAlerta;
use App\Enums\EstadoCirugia;
use App\Enums\TipoReglaConocimiento;
use App\Models\AlertaConocimiento;
use App\Models\Cirugia;
use App\Models\CostoCirugia;
use App\Models\Hospital;
use App\Models\ReglaConocimiento;
use App\Models\SalaOperatoria;
use App\Models\Scopes\HospitalScope;
use App\Services\Indicators\KpiService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Motor de reglas de la Capa 4: convierte patrones del histórico en alertas
 * con impacto en pesos.
 *
 * Dos alcances:
 *   - por cirugía, al costear cada caso (desviación, tiempo, consumo);
 *   - por periodo, sobre un mes cerrado (margen, sala, complicaciones,
 *     cancelación, completitud), porque solo significan con volumen.
 *
 * Cada hallazgo tiene una clave estable (tipo + objeto + mes): reevaluar
 * actualiza la alerta pendiente en vez de duplicarla, y nunca toca una que
 * alguien ya revisó. Si el patrón desaparece (se corrigió el registro), la
 * alerta pendiente se retira.
 */
class MotorReglas
{
    public function __construct(protected CatalogoReglas $catalogo) {}

    // ── Por cirugía ───────────────────────────────────────────────────────

    public function evaluarCirugia(CostoCirugia $costo): void
    {
        $cirugia = $costo->cirugia;

        if ($cirugia === null || $cirugia->estado !== EstadoCirugia::Realizada->value) {
            return;
        }

        $reglas = $this->catalogo->delHospital($cirugia->hospital_id);
        $cirugia->loadMissing(['procedimientos.plantillaInsumos', 'consumos.insumo', 'hospital']);

        $this->desviacionEstandar($reglas[TipoReglaConocimiento::DesviacionEstandar->value], $cirugia, $costo);
        $this->tiempoProlongado($reglas[TipoReglaConocimiento::TiempoProlongado->value], $cirugia, $costo);
        $this->consumoFueraProtocolo($reglas[TipoReglaConocimiento::ConsumoFueraProtocolo->value], $cirugia);
    }

    protected function desviacionEstandar(ReglaConocimiento $regla, Cirugia $cirugia, CostoCirugia $costo): void
    {
        $clave = "desviacion:cirugia:{$cirugia->id}";
        $desviacion = (float) $costo->desviacion;

        $cumple = $regla->activa
            && $costo->clasificacion_desviacion === ClasificacionDesviacion::SobrecostoAlto
            && $desviacion >= $regla->parametro('exceso_minimo_cop');

        if (! $cumple) {
            $this->retirar($cirugia->hospital_id, $clave);

            return;
        }

        $procedimiento = $cirugia->procedimientoPrincipal();
        $pct = (float) $costo->desviacion_pct;

        $this->registrar($regla, $clave, [
            'titulo' => sprintf('%s: %s sobre el estándar', $procedimiento->nombre ?? 'Cirugía', $this->porcentaje($pct)),
            'descripcion' => sprintf(
                'La cirugía #%d del %s costó %s frente a %s de su protocolo (+%s).',
                $cirugia->id,
                $cirugia->fecha->format('d/m/Y'),
                $this->cop((float) $costo->costo_total),
                $this->cop((float) $costo->costo_estandar),
                $this->cop($desviacion),
            ),
            'cirugia_id' => $cirugia->id,
            'procedimiento_quirurgico_id' => $procedimiento?->id,
            'valor' => $pct,
            'umbral' => $cirugia->hospital->umbral_sobrecosto_alto,
            'impacto_cop' => $desviacion,
            'datos' => [
                'costo_total' => (float) $costo->costo_total,
                'costo_estandar' => (float) $costo->costo_estandar,
                'componentes' => $this->componentesDeLaDesviacion($costo),
            ],
        ]);
    }

    protected function tiempoProlongado(ReglaConocimiento $regla, Cirugia $cirugia, CostoCirugia $costo): void
    {
        $clave = "tiempo:cirugia:{$cirugia->id}";
        $real = $cirugia->duracionMinutos() ?? 0;
        $estimado = (int) $cirugia->procedimientos->sum('duracion_estimada_minutos');
        $limite = $estimado * (1 + $regla->parametro('exceso_pct') / 100);

        if (! $regla->activa || $estimado <= 0 || $real <= $limite) {
            $this->retirar($cirugia->hospital_id, $clave);

            return;
        }

        // El minuto extra vale lo que cuesta un minuto de esta sala y de
        // este equipo humano en este caso.
        $costoMinuto = $real > 0
            ? ((float) $costo->costo_sala + (float) $costo->costo_recurso_humano + (float) $costo->costo_equipos) / $real
            : 0.0;
        $extra = $real - $estimado;
        $procedimiento = $cirugia->procedimientoPrincipal();

        $this->registrar($regla, $clave, [
            'titulo' => sprintf('%s: %d min de sala frente a %d estimados', $procedimiento->nombre ?? 'Cirugía', $real, $estimado),
            'descripcion' => sprintf(
                'La cirugía #%d del %s ocupó la sala %d minutos más de lo estimado (%s).',
                $cirugia->id,
                $cirugia->fecha->format('d/m/Y'),
                $extra,
                $this->porcentaje($extra / $estimado),
            ),
            'cirugia_id' => $cirugia->id,
            'procedimiento_quirurgico_id' => $procedimiento?->id,
            'sala_operatoria_id' => $cirugia->sala_operatoria_id,
            'valor' => round($real / $estimado - 1, 4),
            'umbral' => $regla->parametro('exceso_pct') / 100,
            'impacto_cop' => round($extra * $costoMinuto, 2),
            'datos' => ['minutos_reales' => $real, 'minutos_estimados' => $estimado],
        ]);
    }

    protected function consumoFueraProtocolo(ReglaConocimiento $regla, Cirugia $cirugia): void
    {
        $clave = "consumo:cirugia:{$cirugia->id}";
        $enProtocolo = $cirugia->procedimientos
            ->flatMap(fn ($p) => $p->plantillaInsumos->pluck('insumo_id'))
            ->unique()
            ->flip();

        $fuera = $cirugia->consumos->filter(fn ($c): bool => ! $enProtocolo->has($c->insumo_id));
        $monto = (float) $fuera->sum('costo_total');

        // Sin protocolo de insumos no hay «fuera de protocolo».
        if (! $regla->activa || $enProtocolo->isEmpty() || $monto < $regla->parametro('monto_minimo_cop')) {
            $this->retirar($cirugia->hospital_id, $clave);

            return;
        }

        $procedimiento = $cirugia->procedimientoPrincipal();

        $this->registrar($regla, $clave, [
            'titulo' => sprintf('%s: %s en insumos fuera de protocolo', $procedimiento->nombre ?? 'Cirugía', $this->cop($monto)),
            'descripcion' => sprintf(
                'La cirugía #%d del %s consumió %d insumo(s) que el protocolo no contempla.',
                $cirugia->id,
                $cirugia->fecha->format('d/m/Y'),
                $fuera->count(),
            ),
            'cirugia_id' => $cirugia->id,
            'procedimiento_quirurgico_id' => $procedimiento?->id,
            'valor' => $monto,
            'umbral' => $regla->parametro('monto_minimo_cop'),
            'impacto_cop' => $monto,
            'datos' => [
                'insumos' => $fuera->map(fn ($c): array => [
                    'insumo_id' => $c->insumo_id,
                    'nombre' => $c->insumo?->nombre,
                    'cantidad' => (float) $c->cantidad,
                    'costo' => (float) $c->costo_total,
                ])->values()->all(),
            ],
        ]);
    }

    // ── Por periodo ───────────────────────────────────────────────────────

    /**
     * Evalúa las reglas agregadas de un mes (formato Y-m). Solo meses
     * cerrados: la utilización o la completitud de un mes a medias siempre
     * parecen malas.
     */
    public function evaluarMes(Hospital $hospital, string $mes): void
    {
        $inicio = CarbonImmutable::createFromFormat('Y-m-d', "{$mes}-01")?->startOfDay();

        if ($inicio === null || $inicio->endOfMonth()->greaterThanOrEqualTo(now()->startOfDay())) {
            return;
        }

        $fin = $inicio->endOfMonth();
        $reglas = $this->catalogo->delHospital($hospital);

        $this->margenNegativo($reglas[TipoReglaConocimiento::MargenNegativo->value], $hospital, $mes, $inicio, $fin);
        $this->salaSubutilizada($reglas[TipoReglaConocimiento::SalaSubutilizada->value], $hospital, $mes, $inicio, $fin);
        $this->tasaComplicaciones($reglas[TipoReglaConocimiento::TasaComplicaciones->value], $hospital, $mes, $inicio, $fin);
        $this->tasaCancelacion($reglas[TipoReglaConocimiento::TasaCancelacion->value], $hospital, $mes, $inicio, $fin);

        // La facturación llega con semanas de rezago: un mes recién cerrado
        // siempre aparece incompleto.
        if ($fin->lessThan(now()->subDays(30))) {
            $this->completitudBaja($reglas[TipoReglaConocimiento::CompletitudBaja->value], $hospital, $mes, $inicio, $fin);
        }
    }

    protected function margenNegativo(ReglaConocimiento $regla, Hospital $hospital, string $mes, CarbonImmutable $inicio, CarbonImmutable $fin): void
    {
        $filas = DB::table('costos_cirugia')
            ->join('cirugias', 'cirugias.id', '=', 'costos_cirugia.cirugia_id')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->join('procedimientos_quirurgicos', 'procedimientos_quirurgicos.id', '=', 'cirugia_procedimiento.procedimiento_quirurgico_id')
            ->where('cirugias.hospital_id', $hospital->id)
            ->where('cirugias.estado', EstadoCirugia::Realizada->value)
            ->whereBetween('cirugias.fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNotNull('procedimientos_quirurgicos.tarifa_soat')
            ->groupBy('procedimientos_quirurgicos.id', 'procedimientos_quirurgicos.nombre', 'procedimientos_quirurgicos.tarifa_soat')
            ->selectRaw('procedimientos_quirurgicos.id, procedimientos_quirurgicos.nombre, procedimientos_quirurgicos.tarifa_soat, count(*) as n, avg(costos_cirugia.costo_total) as costo')
            ->get();

        foreach ($filas as $fila) {
            $clave = "margen:procedimiento:{$fila->id}:{$mes}";
            $referencia = (float) $fila->tarifa_soat * KpiService::FACTOR_REFERENCIA_SOAT;
            $costo = (float) $fila->costo;
            $perdidaPct = $referencia > 0 ? ($costo - $referencia) / $referencia : 0.0;

            if (! $regla->activa || $fila->n < $regla->parametro('minimo_casos') || $perdidaPct < $regla->parametro('perdida_minima_pct') / 100) {
                $this->retirar($hospital->id, $clave);

                continue;
            }

            $this->registrar($regla, $clave, [
                'titulo' => sprintf('%s: costo %s sobre la tarifa en %s', $fila->nombre, $this->porcentaje($perdidaPct), $this->mes($inicio)),
                'descripcion' => sprintf(
                    '%d cirugías con costo promedio de %s frente a una tarifa de referencia de %s (SOAT −25 %%). Pérdida del mes: %s.',
                    $fila->n,
                    $this->cop($costo),
                    $this->cop($referencia),
                    $this->cop(($costo - $referencia) * $fila->n),
                ),
                'procedimiento_quirurgico_id' => $fila->id,
                'periodo' => $mes,
                'valor' => round($perdidaPct, 4),
                'umbral' => $regla->parametro('perdida_minima_pct') / 100,
                'impacto_cop' => round(($costo - $referencia) * $fila->n, 2),
                'datos' => ['n' => (int) $fila->n, 'costo_promedio' => round($costo, 2), 'tarifa_referencia' => round($referencia, 2)],
            ]);
        }
    }

    protected function salaSubutilizada(ReglaConocimiento $regla, Hospital $hospital, string $mes, CarbonImmutable $inicio, CarbonImmutable $fin): void
    {
        $capacidad = $hospital->minutosDisponiblesEntre($inicio, $fin);

        if ($capacidad <= 0) {
            return;
        }

        $salas = SalaOperatoria::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->where('activa', true)
            ->get();

        foreach ($salas as $sala) {
            $clave = "sala:{$sala->id}:{$mes}";
            $usados = (int) Cirugia::query()->withoutGlobalScope(HospitalScope::class)
                ->where('hospital_id', $hospital->id)
                ->where('sala_operatoria_id', $sala->id)
                ->where('estado', EstadoCirugia::Realizada->value)
                ->whereNotNull('hora_fin')
                ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
                ->get(['hora_inicio', 'hora_fin'])
                ->sum(fn (Cirugia $c): int => $c->duracionMinutos() ?? 0);

            $utilizacion = $usados / $capacidad;

            if (! $regla->activa || $utilizacion >= $regla->parametro('utilizacion_minima_pct') / 100) {
                $this->retirar($hospital->id, $clave);

                continue;
            }

            $ociosos = $capacidad - $usados;

            $this->registrar($regla, $clave, [
                'titulo' => sprintf('%s: %s de utilización en %s', $sala->nombre, $this->porcentaje($utilizacion, false), $this->mes($inicio)),
                'descripcion' => sprintf(
                    'La sala se usó %s de %s minutos disponibles. La capacidad ociosa, valorada a su costo/hora, suma %s.',
                    number_format($usados, 0, ',', '.'),
                    number_format($capacidad, 0, ',', '.'),
                    $this->cop($ociosos * (float) $sala->costo_hora / 60),
                ),
                'sala_operatoria_id' => $sala->id,
                'periodo' => $mes,
                'valor' => round($utilizacion, 4),
                'umbral' => $regla->parametro('utilizacion_minima_pct') / 100,
                'impacto_cop' => round($ociosos * (float) $sala->costo_hora / 60, 2),
                'datos' => ['minutos_usados' => $usados, 'minutos_disponibles' => $capacidad],
            ]);
        }
    }

    protected function tasaComplicaciones(ReglaConocimiento $regla, Hospital $hospital, string $mes, CarbonImmutable $inicio, CarbonImmutable $fin): void
    {
        $filas = DB::table('resultados_clinicos')
            ->join('cirugias', 'cirugias.id', '=', 'resultados_clinicos.cirugia_id')
            ->join('cirugia_procedimiento', function ($join): void {
                $join->on('cirugia_procedimiento.cirugia_id', '=', 'cirugias.id')->where('cirugia_procedimiento.es_principal', true);
            })
            ->join('procedimientos_quirurgicos', 'procedimientos_quirurgicos.id', '=', 'cirugia_procedimiento.procedimiento_quirurgico_id')
            ->leftJoin('costos_cirugia', 'costos_cirugia.cirugia_id', '=', 'cirugias.id')
            ->where('cirugias.hospital_id', $hospital->id)
            ->whereBetween('cirugias.fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->groupBy('procedimientos_quirurgicos.id', 'procedimientos_quirurgicos.nombre')
            ->selectRaw('procedimientos_quirurgicos.id, procedimientos_quirurgicos.nombre, count(*) as n, '
                .'sum(case when resultados_clinicos.complicacion_intraoperatoria = 1 or resultados_clinicos.complicacion_postoperatoria = 1 then 1 else 0 end) as complicadas, '
                .'sum(case when (resultados_clinicos.complicacion_intraoperatoria = 1 or resultados_clinicos.complicacion_postoperatoria = 1) and costos_cirugia.desviacion > 0 then costos_cirugia.desviacion else 0 end) as sobrecosto')
            ->get();

        foreach ($filas as $fila) {
            $clave = "complicaciones:procedimiento:{$fila->id}:{$mes}";
            $tasa = $fila->n > 0 ? $fila->complicadas / $fila->n : 0.0;

            if (! $regla->activa || $fila->n < $regla->parametro('minimo_casos') || $tasa <= $regla->parametro('tasa_maxima_pct') / 100) {
                $this->retirar($hospital->id, $clave);

                continue;
            }

            $this->registrar($regla, $clave, [
                'titulo' => sprintf('%s: %s de complicaciones en %s', $fila->nombre, $this->porcentaje($tasa, false), $this->mes($inicio)),
                'descripcion' => sprintf(
                    '%d de %d cirugías con resultado clínico registrado tuvieron complicación intra o postoperatoria. Sobrecosto asociado: %s.',
                    $fila->complicadas,
                    $fila->n,
                    $this->cop((float) $fila->sobrecosto),
                ),
                'procedimiento_quirurgico_id' => $fila->id,
                'periodo' => $mes,
                'valor' => round($tasa, 4),
                'umbral' => $regla->parametro('tasa_maxima_pct') / 100,
                'impacto_cop' => round((float) $fila->sobrecosto, 2),
                'datos' => ['n' => (int) $fila->n, 'complicadas' => (int) $fila->complicadas],
            ]);
        }
    }

    protected function tasaCancelacion(ReglaConocimiento $regla, Hospital $hospital, string $mes, CarbonImmutable $inicio, CarbonImmutable $fin): void
    {
        $clave = "cancelacion:{$mes}";
        $programadas = Cirugia::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->where('tipo', 'programada')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
        $total = (clone $programadas)->count();
        $canceladas = (clone $programadas)->where('estado', EstadoCirugia::Cancelada->value)->count();
        $tasa = $total > 0 ? $canceladas / $total : 0.0;

        if (! $regla->activa || $total === 0 || $tasa <= $regla->parametro('tasa_maxima_pct') / 100) {
            $this->retirar($hospital->id, $clave);

            return;
        }

        $this->registrar($regla, $clave, [
            'titulo' => sprintf('Cancelación de programadas: %s en %s', $this->porcentaje($tasa, false), $this->mes($inicio)),
            'descripcion' => sprintf('%d de %d cirugías programadas se cancelaron. Cada cancelación deja una franja de sala sin uso y un paciente sin atender.', $canceladas, $total),
            'periodo' => $mes,
            'valor' => round($tasa, 4),
            'umbral' => $regla->parametro('tasa_maxima_pct') / 100,
            'datos' => ['programadas' => $total, 'canceladas' => $canceladas],
        ]);
    }

    protected function completitudBaja(ReglaConocimiento $regla, Hospital $hospital, string $mes, CarbonImmutable $inicio, CarbonImmutable $fin): void
    {
        $clave = "completitud:{$mes}";
        $realizadas = Cirugia::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->where('estado', EstadoCirugia::Realizada->value)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
        $total = (clone $realizadas)->count();

        if ($total === 0) {
            $this->retirar($hospital->id, $clave);

            return;
        }

        $conResultado = (clone $realizadas)->has('resultadoClinico')->count();
        $conFactura = (clone $realizadas)->has('facturacion')->count();
        $completitud = min($conResultado, $conFactura) / $total;

        if (! $regla->activa || $completitud >= $regla->parametro('completitud_minima_pct') / 100) {
            $this->retirar($hospital->id, $clave);

            return;
        }

        $this->registrar($regla, $clave, [
            'titulo' => sprintf('Completitud de captura: %s en %s', $this->porcentaje($completitud, false), $this->mes($inicio)),
            'descripcion' => sprintf(
                'De %d cirugías realizadas, %d tienen resultado clínico y %d facturación. Sin esos datos no se puede medir margen ni calidad.',
                $total,
                $conResultado,
                $conFactura,
            ),
            'periodo' => $mes,
            'valor' => round($completitud, 4),
            'umbral' => $regla->parametro('completitud_minima_pct') / 100,
            'datos' => ['realizadas' => $total, 'con_resultado' => $conResultado, 'con_facturacion' => $conFactura],
        ]);
    }

    // ── Histórico completo ────────────────────────────────────────────────

    /**
     * Evalúa todo: cada cirugía costeada y cada mes cerrado con actividad.
     *
     * @return array{cirugias: int, meses: int}
     */
    public function evaluarHistorico(Hospital $hospital): array
    {
        $cirugias = 0;

        CostoCirugia::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->with(['cirugia.procedimientos.plantillaInsumos', 'cirugia.consumos.insumo', 'cirugia.hospital'])
            ->chunkById(200, function ($costos) use (&$cirugias): void {
                foreach ($costos as $costo) {
                    $this->evaluarCirugia($costo);
                    $cirugias++;
                }
            });

        $meses = $this->mesesConActividad($hospital);

        foreach ($meses as $mes) {
            $this->evaluarMes($hospital, $mes);
        }

        ReglaConocimiento::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->update(['evaluada_en' => now()]);

        return ['cirugias' => $cirugias, 'meses' => count($meses)];
    }

    /** @return list<string> */
    public function mesesConActividad(Hospital $hospital): array
    {
        $rango = Cirugia::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospital->id)
            ->selectRaw('min(fecha) as desde, max(fecha) as hasta')
            ->first();

        if ($rango === null || $rango->getAttribute('desde') === null) {
            return [];
        }

        $meses = [];
        $cursor = CarbonImmutable::parse((string) $rango->getAttribute('desde'))->startOfMonth();
        $hasta = CarbonImmutable::parse((string) $rango->getAttribute('hasta'))->startOfMonth();

        while ($cursor->lessThanOrEqualTo($hasta)) {
            $meses[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        return $meses;
    }

    // ── Persistencia ──────────────────────────────────────────────────────

    /**
     * Crea o actualiza la alerta pendiente de esa clave. Una alerta ya
     * revisada o descartada no se toca: la decisión de alguien vale más que
     * la reevaluación.
     *
     * @param  array<string, mixed>  $atributos
     */
    protected function registrar(ReglaConocimiento $regla, string $clave, array $atributos): void
    {
        $existente = AlertaConocimiento::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $regla->hospital_id)
            ->where('clave', $clave)
            ->first();

        if ($existente !== null && $existente->estado !== EstadoAlerta::Pendiente) {
            return;
        }

        $valores = $atributos + [
            'hospital_id' => $regla->hospital_id,
            'regla_conocimiento_id' => $regla->id,
            'tipo' => $regla->tipo,
            'severidad' => $regla->severidad,
            'clave' => $clave,
        ];

        if ($existente !== null) {
            $existente->update($valores);

            return;
        }

        AlertaConocimiento::query()->withoutGlobalScope(HospitalScope::class)->create($valores + [
            'estado' => EstadoAlerta::Pendiente,
        ]);
    }

    protected function retirar(int $hospitalId, string $clave): void
    {
        AlertaConocimiento::query()->withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospitalId)
            ->where('clave', $clave)
            ->where('estado', EstadoAlerta::Pendiente->value)
            ->delete();
    }

    /**
     * Qué componentes explican la desviación, de mayor a menor.
     *
     * @return list<array{componente: string, estandar: float, real: float, diferencia: float}>
     */
    protected function componentesDeLaDesviacion(CostoCirugia $costo): array
    {
        $estandar = $costo->detalle['estandar'] ?? null;

        if (! is_array($estandar)) {
            return [];
        }

        $filas = [];
        foreach (['recurso_humano' => 'costo_recurso_humano', 'sala' => 'costo_sala', 'equipos' => 'costo_equipos', 'insumos' => 'costo_insumos', 'indirecto' => 'costo_indirecto'] as $componente => $columna) {
            $real = (float) $costo->getAttribute($columna);
            $esperado = (float) ($estandar[$componente] ?? 0);
            $filas[] = ['componente' => $componente, 'estandar' => $esperado, 'real' => $real, 'diferencia' => round($real - $esperado, 2)];
        }

        usort($filas, fn (array $a, array $b): int => $b['diferencia'] <=> $a['diferencia']);

        return $filas;
    }

    protected function cop(float $valor): string
    {
        return '$'.number_format($valor, 0, ',', '.');
    }

    protected function porcentaje(float $valor, bool $signo = true): string
    {
        $texto = number_format($valor * 100, 1, ',', '.').' %';

        return $signo && $valor > 0 ? '+'.$texto : $texto;
    }

    protected function mes(CarbonImmutable $fecha): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return $meses[$fecha->month - 1].' '.$fecha->year;
    }
}
