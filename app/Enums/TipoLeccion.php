<?php

namespace App\Enums;

/**
 * Una lección nace de algo que salió mal (o distinto) y se aprendió; una
 * mejor práctica describe algo que sale bien y se quiere repetir. Viven en
 * el mismo repositorio porque se buscan igual: por procedimiento y causa.
 */
enum TipoLeccion: string
{
    case Leccion = 'leccion';
    case MejorPractica = 'mejor_practica';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Leccion => 'Lección aprendida',
            self::MejorPractica => 'Mejor práctica',
        };
    }
}
