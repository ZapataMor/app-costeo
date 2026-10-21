<?php

namespace App\Enums;

enum RolUsuario: string
{
    case SuperAdmin = 'super_admin';
    case AdminHospital = 'admin_hospital';
    case Digitador = 'digitador';

    /**
     * Personal quirúrgico con cuenta propia (cirujano, anestesiólogo,
     * enfermería…), vinculado a su registro de recurso humano. Participa en
     * la Capa 4: escribe lecciones, discute en las comunidades de práctica y
     * consulta las mejores prácticas. No ve costos individuales ajenos.
     */
    case Profesional = 'profesional';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
