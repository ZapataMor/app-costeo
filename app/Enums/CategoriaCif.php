<?php

namespace App\Enums;

/**
 * Bolsa de costo indirecto: qué agrupa el concepto.
 *
 * Las tres primeras tienen un equivalente que HOY entra al costo directo,
 * así que activarlas exige marcar ese componente como derivado de CIF o el
 * costo se contaría dos veces. Las demás no solapan con nada.
 *
 * @see OrigenComponente
 */
enum CategoriaCif: string
{
    /** Inmueble, servicios públicos, mantenimiento, aseo, seguros. */
    case Infraestructura = 'infraestructura';

    /** Depreciación del equipamiento médico. */
    case DepreciacionEquipos = 'depreciacion_equipos';

    /** Administración imputada al personal asistencial. */
    case PersonalIndirecto = 'personal_indirecto';

    /** Dirección, facturación, sistemas: sin equivalente en el directo. */
    case Administracion = 'administracion';

    /** Lavandería, esterilización, residuos: sin equivalente en el directo. */
    case ServiciosGenerales = 'servicios_generales';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Columna de `hospitales` que marca el origen del componente directo
     * equivalente, o null si la categoría no solapa con ninguno.
     */
    public function columnaOrigen(): ?string
    {
        return match ($this) {
            self::Infraestructura => 'origen_infraestructura',
            self::DepreciacionEquipos => 'origen_depreciacion_equipos',
            self::PersonalIndirecto => 'origen_personal_indirecto',
            default => null,
        };
    }

    /** Categorías que sí pueden producir doble conteo. */
    public function solapaConElDirecto(): bool
    {
        return $this->columnaOrigen() !== null;
    }

    /** Nombre del componente directo que quedaría duplicado. */
    public function descripcionDelSolape(): ?string
    {
        return match ($this) {
            self::Infraestructura => 'el costo/hora de las salas operatorias',
            self::DepreciacionEquipos => 'el costo/hora de los equipos médicos',
            self::PersonalIndirecto => 'los costos indirectos mensuales del personal',
            default => null,
        };
    }
}
