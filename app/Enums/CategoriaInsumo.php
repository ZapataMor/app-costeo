<?php

namespace App\Enums;

enum CategoriaInsumo: string
{
    case Medicamento = 'medicamento';
    case Dispositivo = 'dispositivo';
    case Material = 'material';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
