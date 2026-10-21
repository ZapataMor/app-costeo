<?php

namespace App\Enums;

/**
 * Seguimiento de una recomendación hasta que produce (o no) un ahorro.
 *
 * El generador solo reescribe las `nuevas`: en cuanto alguien la gestiona,
 * la evidencia queda congelada como estaba cuando se decidió.
 */
enum EstadoRecomendacion: string
{
    case Nueva = 'nueva';
    case Aceptada = 'aceptada';
    case Implementada = 'implementada';
    case Descartada = 'descartada';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Nueva => 'Nueva',
            self::Aceptada => 'Aceptada',
            self::Implementada => 'Implementada',
            self::Descartada => 'Descartada',
        };
    }
}
