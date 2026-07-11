<?php

namespace App\Enums;

/**
 * Nivel de confiabilidad de un parámetro capturado (trazabilidad académica):
 * medido (dato observado), estimado (informado por el personal) o
 * supuesto (valor de trabajo sin respaldo directo).
 */
enum NivelConfiabilidad: string
{
    case Medido = 'medido';
    case Estimado = 'estimado';
    case Supuesto = 'supuesto';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
