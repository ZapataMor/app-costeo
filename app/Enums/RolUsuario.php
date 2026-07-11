<?php

namespace App\Enums;

enum RolUsuario: string
{
    case SuperAdmin = 'super_admin';
    case AdminHospital = 'admin_hospital';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
