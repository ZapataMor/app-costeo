<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\EstadoCirugia;
use App\Models\Cirugia;
use App\Support\Periodo;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Periodo de las vistas de Costeo quirúrgico.
 *
 * Antes, sin `?desde=&hasta=`, todo se calculaba sobre «toda la historia»:
 * ningún indicador tenía con qué compararse y un mes malo quedaba diluido en
 * años de datos. Ahora el valor por defecto son los **últimos 3 meses
 * naturales con cirugías** —anclados a la última cirugía realizada, no a hoy,
 * para que un hospital que carga con retraso no vea la pantalla vacía— y
 * «toda la historia» se pide explícitamente con `?todo=1`.
 *
 * La API (`/api/v1/kpis/*`) no usa este trait: conserva su comportamiento.
 */
trait ResuelvePeriodoCosteo
{
    /** Meses del periodo por defecto. */
    protected int $mesesPorDefecto = 3;

    protected function periodoCosteo(Request $request): Periodo
    {
        if ($request->boolean('todo')) {
            return new Periodo;
        }

        $elegido = Periodo::desdeRequest($request);

        if (! $elegido->vacio()) {
            return $elegido;
        }

        $ultima = Cirugia::query()
            ->where('estado', EstadoCirugia::Realizada->value)
            ->max('fecha');

        // Si la última cirugía es de este mes, el periodo termina hoy: contar
        // el resto del mes inflaría la capacidad ociosa y las bolsas CIF.
        $fin = ($ultima !== null ? Carbon::parse($ultima) : now())->endOfMonth()->min(now()->endOfDay());
        $inicio = Carbon::parse($fin)->startOfMonth()->subMonthsNoOverflow($this->mesesPorDefecto - 1);

        return new Periodo(
            $inicio,
            Carbon::parse($fin),
            sprintf(
                'Últimos %d meses con cirugías (%s a %s)',
                $this->mesesPorDefecto,
                $this->mesCorto($inicio),
                $this->mesCorto($fin),
            ),
        );
    }

    /**
     * «ago 2026». A mano y no con `translatedFormat()`: el locale de la
     * aplicación puede no ser español y la etiqueta salía «Aug 2026».
     */
    protected function mesCorto(CarbonInterface $fecha): string
    {
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sept', 'oct', 'nov', 'dic'];

        return $meses[$fecha->month - 1].' '.$fecha->year;
    }

    /**
     * Props comunes del selector: fechas resueltas, etiqueta y si se está
     * viendo toda la historia.
     *
     * @return array<string, mixed>
     */
    protected function propsPeriodo(Periodo $periodo): array
    {
        return [
            'periodo' => $periodo->aArray(),
            'periodoEtiqueta' => $periodo->etiqueta(),
            'periodoAnteriorEtiqueta' => $periodo->anterior()?->etiqueta(),
        ];
    }
}
