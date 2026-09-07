<?php

namespace App\Exceptions;

use App\Enums\BaseAsignacionCif;
use App\Models\Hospital;
use RuntimeException;

/**
 * Una bolsa CIF por minuto necesita capacidad contra la cual repartirse.
 *
 * Si el hospital se queda sin salas activas —o sin personal quirúrgico
 * activo— la tasa por minuto sería una división por cero. Preferimos fallar
 * con un mensaje que nombre el arreglo antes que costear una cirugía con la
 * bolsa en cero: eso subestimaría el costo sin que nada lo delate.
 */
class CapacidadCifNoDisponibleException extends RuntimeException
{
    public static function porBase(Hospital $hospital, BaseAsignacionCif $base, string $concepto): self
    {
        $recurso = $base === BaseAsignacionCif::MinutoPersonal
            ? 'personal quirúrgico activo'
            : 'salas operatorias activas';

        return new self(sprintf(
            'La bolsa «%s» se reparte %s, pero el hospital %s no tiene %s. '
            .'Reactiva al menos un registro o desactiva la categoría de la bolsa antes de seguir costeando.',
            $concepto,
            strtolower($base->etiqueta()),
            $hospital->nombre,
            $recurso,
        ));
    }
}
