<?php

namespace App\Enums;

enum RolQuirurgico: string
{
    case Cirujano = 'cirujano';
    case Ayudante = 'ayudante';
    case Anestesiologo = 'anestesiologo';
    case Instrumentador = 'instrumentador';
    case Circulante = 'circulante';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
