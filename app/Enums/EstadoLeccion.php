<?php

namespace App\Enums;

/**
 * Ciclo de una lección aprendida.
 *
 * Nadie publica lo que escribió: el autor la manda a revisión y la valida
 * el administrador del hospital (el curador del conocimiento, en términos de
 * Kerschberg). Es lo que evita que el repositorio se llene de opiniones sin
 * respaldo y lo que permite confiar en él.
 */
enum EstadoLeccion: string
{
    case Borrador = 'borrador';
    case EnRevision = 'en_revision';
    case Publicada = 'publicada';
    case Archivada = 'archivada';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EnRevision => 'En revisión',
            self::Publicada => 'Publicada',
            self::Archivada => 'Archivada',
        };
    }

    /** El autor solo edita lo que todavía no está en el repositorio. */
    public function editable(): bool
    {
        return $this === self::Borrador;
    }
}
