<?php

namespace App\Enums;

enum TipoCirugia: string
{
    case Programada = 'programada';
    case Urgencia = 'urgencia';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
