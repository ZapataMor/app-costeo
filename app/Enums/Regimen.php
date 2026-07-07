<?php

namespace App\Enums;

enum Regimen: string
{
    case Contributivo = 'contributivo';
    case Subsidiado = 'subsidiado';
    case Especial = 'especial';
    case NoAsegurado = 'no_asegurado';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
