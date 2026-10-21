<?php

namespace App\Enums;

enum SeveridadAlerta: string
{
    case Informativa = 'informativa';
    case Advertencia = 'advertencia';
    case Critica = 'critica';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Para ordenar la bandeja: lo crítico arriba. */
    public function peso(): int
    {
        return match ($this) {
            self::Critica => 3,
            self::Advertencia => 2,
            self::Informativa => 1,
        };
    }
}
