<?php

namespace App\Enums;

enum EstadoCirugia: string
{
    case Programada = 'programada';
    case EnProceso = 'en_proceso';
    /**
     * Salió de sala pero sigue en recuperación: el acto quirúrgico terminó y
     * el ciclo no. Existe para no tener que fingir un egreso que aún no ha
     * ocurrido con tal de poder cerrar el registro.
     */
    case EnRecuperacion = 'en_recuperacion';
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
