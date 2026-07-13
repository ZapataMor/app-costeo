<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El motor TDABC solo costea cirugías realizadas: costear programadas,
 * en proceso o canceladas contaminaría los indicadores de Capa 3.
 */
class CirugiaNoCosteableException extends RuntimeException
{
    public static function porEstado(string $estado): self
    {
        return new self(
            "Solo se costean cirugías en estado «realizada»; esta cirugía está «{$estado}».",
        );
    }
}
