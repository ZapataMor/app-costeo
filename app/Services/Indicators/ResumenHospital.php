<?php

namespace App\Services\Indicators;

use App\Enums\EstadoCirugia;
use App\Enums\EstadoLeccion;
use App\Enums\EstadoRecomendacion;
use App\Http\Controllers\Cirugias\CirugiaController;
use App\Models\Cirugia;
use App\Models\LeccionAprendida;
use App\Models\Recomendacion;
use App\Services\Alertas\BandejaAlertas;
use App\Support\Periodo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Portada del administrador del hospital: «¿cómo vamos?» en una pantalla.
 *
 * Antes el administrador entraba a Parámetros, que es configuración, y para
 * saber si el mes iba bien tenía que recorrer cuatro tableros. Aquí van los
 * cuatro indicadores que deciden el mes, comparados con el anterior, y la
 * lista corta de lo que pide acción.
 *
 * Corre en rutas con `hospital.contexto`, así que los modelos quedan
 * acotados al hospital activo por el HospitalScope.
 */
class ResumenHospital
{
    /** Como mucho cinco cosas: una lista más larga deja de leerse. */
    public const MAXIMO_ATENCION = 5;

    public function __construct(private readonly BandejaAlertas $bandeja) {}

    /**
     * Mes de la cirugía realizada más reciente. Con la captura por lotes
     * «este mes» puede estar vacío y la portada saldría en blanco.
     */
    public function mesReferencia(): CarbonInterface
    {
        $ultima = Cirugia::query()
            ->where('estado', EstadoCirugia::Realizada->value)
            ->whereNotNull('hora_fin')
            ->max('fecha');

        return ($ultima !== null ? Carbon::parse((string) $ultima) : Carbon::now())->startOfMonth();
    }

    /**
     * Los cuatro indicadores del mes de referencia y del anterior.
     *
     * @return array<string, mixed>
     */
    public function indicadores(int $hospitalId): array
    {
        $mes = $this->mesReferencia();
        $mesAnterior = $mes->copy()->subMonthNoOverflow();

        $actual = (new KpiService)->enPeriodo(self::periodoDelMes($mes));
        $anterior = (new KpiService)->enPeriodo(self::periodoDelMes($mesAnterior));

        $costosActual = $actual->costos()['global'];
        $costosAnterior = $anterior->costos()['global'];
        $completitudActual = $actual->completitud();
        $completitudAnterior = $anterior->completitud();

        return [
            'mes' => $mes->format('Y-m'),
            'mes_anterior' => $mesAnterior->format('Y-m'),
            'resultado' => [
                'actual' => $actual->resultado(),
                'anterior' => $anterior->resultado(),
            ],
            'costo_promedio' => [
                'actual' => $costosActual['costo_promedio'],
                'anterior' => $costosAnterior['costo_promedio'],
                'n' => $costosActual['n_cirugias_costeadas'],
            ],
            'completitud' => [
                'actual' => $completitudActual['completitud_global'],
                'anterior' => $completitudAnterior['completitud_global'],
                'completas' => $completitudActual['completas'],
                'total' => $completitudActual['total_cirugias_realizadas'],
            ],
            'alertas' => $this->bandeja->resumen($hospitalId),
        ];
    }

    /**
     * Lo que necesita atención, de lo más urgente a lo menos. Solo datos:
     * el texto lo arma la página con el formato común de cifras.
     *
     * @return list<array<string, mixed>>
     */
    public function atencion(int $hospitalId): array
    {
        $items = [];

        $alertas = $this->bandeja->resumen($hospitalId);

        if ($alertas['prioritarios'] > 0) {
            $items[] = [
                'clave' => 'alertas',
                'tono' => 'alerta',
                'href' => '/alertas',
                'casos' => $alertas['prioritarios'],
                'sobrecostos' => $alertas['sobrecostos'],
                'exceso' => $alertas['exceso'],
                'criticas' => $alertas['criticas'],
            ];
        }

        $perdida = $this->mayorPerdida();

        if ($perdida !== null) {
            $items[] = ['clave' => 'perdida', 'tono' => 'alerta', ...$perdida];
        }

        $pendientes = Cirugia::query()
            ->where(fn ($q) => CirugiaController::filtroPendientes($q))
            ->count();

        if ($pendientes > 0) {
            $items[] = [
                'clave' => 'pendientes',
                'tono' => 'atencion',
                'href' => '/cirugias?vista=pendientes',
                'n' => $pendientes,
            ];
        }

        $enRevision = LeccionAprendida::query()
            ->where('estado', EstadoLeccion::EnRevision->value)
            ->count();

        if ($enRevision > 0) {
            $items[] = [
                'clave' => 'lecciones',
                'tono' => 'atencion',
                'href' => '/conocimiento/lecciones?vista=revision',
                'n' => $enRevision,
            ];
        }

        // Solo el conteo: el ahorro estimado mezcla ingreso, capacidad y caja
        // (#28) y sumarlo aquí daría una cifra engañosa.
        $nuevas = Recomendacion::query()
            ->where('estado', EstadoRecomendacion::Nueva->value)
            ->count();

        if ($nuevas > 0) {
            $items[] = [
                'clave' => 'recomendaciones',
                'tono' => 'neutro',
                'href' => '/conocimiento/recomendaciones',
                'n' => $nuevas,
            ];
        }

        return array_slice($items, 0, self::MAXIMO_ATENCION);
    }

    /**
     * Procedimiento que más plata pierde en los últimos tres meses: margen
     * promedio por cirugía × número de cirugías facturadas.
     *
     * @return array<string, mixed>|null
     */
    protected function mayorPerdida(): ?array
    {
        $mes = $this->mesReferencia();
        $periodo = new Periodo($mes->copy()->subMonthsNoOverflow(2)->startOfMonth(), $mes->copy()->endOfMonth());

        $peor = collect((new KpiService)->enPeriodo($periodo)->margen()['por_procedimiento'])
            ->filter(fn (array $fila): bool => $fila['margen_vs_facturado'] !== null && $fila['margen_vs_facturado'] < 0)
            ->map(fn (array $fila): array => $fila + ['perdida_total' => $fila['margen_vs_facturado'] * $fila['n']])
            ->sortBy('perdida_total')
            ->first();

        if ($peor === null) {
            return null;
        }

        return [
            'href' => '/costeo/procedimientos/'.$peor['procedimiento']['id'],
            'procedimiento' => $peor['procedimiento'],
            'n' => $peor['n'],
            'margen_promedio' => $peor['margen_vs_facturado'],
            'margen_pct' => $peor['margen_vs_facturado_pct'],
            'perdida_total' => round((float) $peor['perdida_total'], 2),
        ];
    }

    protected static function periodoDelMes(CarbonInterface $mes): Periodo
    {
        return new Periodo($mes->copy()->startOfMonth(), $mes->copy()->endOfMonth());
    }
}
