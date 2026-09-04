<?php

namespace App\Enums;

/**
 * Inductor de la bolsa: cómo se reparte el monto mensual entre cirugías.
 *
 * Lista cerrada. El denominador de las bases por minuto es la capacidad
 * SUMADA del hospital, no la de un solo recurso: la bolsa mensual cubre
 * todos los quirófanos (o todo el personal quirúrgico), así que dividir
 * entre la capacidad de uno solo inflaría la tasa tantas veces como
 * recursos activos haya.
 */
enum BaseAsignacionCif: string
{
    /** Tasa × minutos de ocupación de sala de la cirugía. */
    case MinutoQuirofano = 'minuto_quirofano';

    /** Tasa × minutos de participación del equipo quirúrgico. */
    case MinutoPersonal = 'minuto_personal';

    /** Porcentaje sobre el costo directo de la cirugía. */
    case PorcentajeDirecto = 'porcentaje_directo';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * `porcentaje_directo` se configura con un porcentaje y sin monto; las
     * bases por minuto, al revés. Cada una prohíbe el campo de la otra.
     */
    public function usaPorcentaje(): bool
    {
        return $this === self::PorcentajeDirecto;
    }

    public function usaMontoMensual(): bool
    {
        return ! $this->usaPorcentaje();
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::MinutoQuirofano => 'Por minuto de quirófano',
            self::MinutoPersonal => 'Por minuto de personal quirúrgico',
            self::PorcentajeDirecto => 'Porcentaje del costo directo',
        };
    }
}
