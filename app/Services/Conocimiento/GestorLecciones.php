<?php

namespace App\Services\Conocimiento;

use App\Enums\EstadoLeccion;
use App\Models\LeccionAprendida;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Transiciones del ciclo de una lección aprendida.
 *
 *   borrador ──enviar──▶ en_revision ──publicar──▶ publicada ──archivar──▶ archivada
 *        ▲                    │
 *        └─────devolver───────┘
 *
 * Solo quien gestiona el conocimiento (admin del hospital) publica,
 * devuelve, archiva o comparte con la red; el autor solo edita su borrador
 * y lo envía. Una lección se comparte con la red únicamente si ya está
 * publicada y no cita cirugías concretas de pacientes.
 */
class GestorLecciones
{
    public function enviarARevision(LeccionAprendida $leccion, User $usuario): void
    {
        $this->exigir($leccion->estado === EstadoLeccion::Borrador, 'Solo un borrador se puede enviar a revisión.');
        $this->exigir($this->esAutorOGestor($leccion, $usuario), 'Solo el autor puede enviar su lección a revisión.');

        $leccion->forceFill([
            'estado' => EstadoLeccion::EnRevision,
            'motivo_devolucion' => null,
        ])->save();
    }

    public function publicar(LeccionAprendida $leccion, User $revisor): void
    {
        $this->exigir($revisor->gestionaConocimiento(), 'Solo el administrador del hospital publica lecciones.');
        $this->exigir(
            in_array($leccion->estado, [EstadoLeccion::EnRevision, EstadoLeccion::Borrador], true),
            'Esta lección ya no está pendiente de publicación.',
        );

        $leccion->forceFill([
            'estado' => EstadoLeccion::Publicada,
            'revisado_por' => $revisor->id,
            'revisado_en' => now(),
            'publicada_en' => now(),
            'motivo_devolucion' => null,
        ])->save();
    }

    public function devolver(LeccionAprendida $leccion, User $revisor, string $motivo): void
    {
        $this->exigir($revisor->gestionaConocimiento(), 'Solo el administrador del hospital devuelve lecciones.');
        $this->exigir($leccion->estado === EstadoLeccion::EnRevision, 'Solo se devuelve una lección en revisión.');

        $leccion->forceFill([
            'estado' => EstadoLeccion::Borrador,
            'revisado_por' => $revisor->id,
            'revisado_en' => now(),
            'motivo_devolucion' => $motivo,
        ])->save();
    }

    public function archivar(LeccionAprendida $leccion, User $revisor): void
    {
        $this->exigir($revisor->gestionaConocimiento(), 'Solo el administrador del hospital archiva lecciones.');

        $leccion->forceFill([
            'estado' => EstadoLeccion::Archivada,
            'compartida_red' => false,
        ])->save();
    }

    public function compartirEnRed(LeccionAprendida $leccion, User $revisor, bool $compartir): void
    {
        $this->exigir($revisor->gestionaConocimiento(), 'Solo el administrador del hospital comparte lecciones con la red.');
        $this->exigir(! $compartir || $leccion->estado === EstadoLeccion::Publicada, 'Solo una lección publicada se comparte con la red.');

        $leccion->forceFill([
            'compartida_red' => $compartir,
            'compartida_en' => $compartir ? now() : null,
        ])->save();
    }

    public function puedeEditar(LeccionAprendida $leccion, User $usuario): bool
    {
        return $leccion->estado->editable() && $this->esAutorOGestor($leccion, $usuario);
    }

    /**
     * Quién ve la lección: publicada → todo el hospital; borrador o revisión
     * → su autor y quien gestiona el conocimiento.
     */
    public function puedeVer(LeccionAprendida $leccion, User $usuario): bool
    {
        return $leccion->estado === EstadoLeccion::Publicada
            || $this->esAutorOGestor($leccion, $usuario);
    }

    protected function esAutorOGestor(LeccionAprendida $leccion, User $usuario): bool
    {
        return $usuario->gestionaConocimiento() || $leccion->autor_id === $usuario->id;
    }

    protected function exigir(bool $condicion, string $mensaje): void
    {
        if (! $condicion) {
            throw ValidationException::withMessages(['leccion' => $mensaje]);
        }
    }
}
