<?php

namespace App\Enums;

enum NivelComplejidad: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
