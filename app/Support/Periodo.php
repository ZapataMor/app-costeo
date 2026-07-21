<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Ventana temporal de los indicadores.
 *
 * Los KPIs se calculaban sobre toda la historia del hospital, así que no se
 * podía preguntar «¿cómo vamos este trimestre?» ni comparar periodos. Este
 * objeto acota las consultas por `cirugias.fecha`.
 *
 * Un periodo sin extremos equivale a «toda la historia» (el comportamiento
 * anterior), que sigue siendo el valor por defecto.
 */
readonly class Periodo
{
    public function __construct(
        public ?Carbon $desde = null,
        public ?Carbon $hasta = null,
    ) {}

    /** Lee `?desde=` y `?hasta=` (Y-m-d) de la petición. */
    public static function desdeRequest(Request $request): self
    {
        return new self(
            self::fecha($request->query('desde'))?->startOfDay(),
            self::fecha($request->query('hasta'))?->endOfDay(),
        );
    }

    /** Atajos de uso frecuente para el selector del dashboard. */
    public static function ultimosMeses(int $meses): self
    {
        return new self(now()->subMonths($meses)->startOfDay(), now()->endOfDay());
    }

    public function vacio(): bool
    {
        return $this->desde === null && $this->hasta === null;
    }

    /** @return array{desde: string|null, hasta: string|null} */
    public function aArray(): array
    {
        return [
            'desde' => $this->desde?->toDateString(),
            'hasta' => $this->hasta?->toDateString(),
        ];
    }

    /** Etiqueta legible para encabezados y exportaciones. */
    public function etiqueta(): string
    {
        return match (true) {
            $this->vacio() => 'Toda la historia',
            $this->desde !== null && $this->hasta !== null => sprintf(
                '%s a %s',
                $this->desde->format('d/m/Y'),
                $this->hasta->format('d/m/Y'),
            ),
            $this->desde !== null => 'Desde '.$this->desde->format('d/m/Y'),
            default => 'Hasta '.$this->hasta->format('d/m/Y'),
        };
    }

    protected static function fecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (Throwable) {
            return null;
        }
    }
}
