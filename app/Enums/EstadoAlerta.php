<?php

namespace App\Enums;

/**
 * Ciclo de vida de una alerta de sobrecosto.
 *
 * `Pendiente` es lo que hace que la alerta persiga al usuario en vez de
 * esperarlo en un tablero; `Revisada` es la única transición que produce
 * conocimiento, porque exige una causa.
 */
enum EstadoAlerta: string
{
    case Pendiente = 'pendiente';
    case Revisada = 'revisada';
    /** Falso positivo: el caso no ameritaba revisión (baseline aún pobre). */
    case Descartada = 'descartada';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Revisada => 'Revisada',
            self::Descartada => 'Descartada',
        };
    }
}
