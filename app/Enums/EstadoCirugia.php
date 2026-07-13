<?php

namespace App\Enums;

enum EstadoCirugia: string
{
    case Programada = 'programada';
    case EnProceso = 'en_proceso';
    case Realizada = 'realizada';
    case Cancelada = 'cancelada';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
