<?php

namespace App\Services\Conocimiento;

use App\Enums\RolUsuario;
use App\Models\ComunidadPractica;
use App\Models\Hospital;
use App\Models\ProcedimientoQuirurgico;
use App\Models\Scopes\HospitalScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene las comunidades de práctica de un hospital.
 *
 * Una por especialidad quirúrgica del portafolio, más una general de
 * cirugía. La membresía se deriva del personal: cada profesional entra a la
 * general y a la de su especialidad; el administrador del hospital modera
 * todas. Así nadie tiene que «armar» las comunidades para empezar a usarlas.
 */
class GestorComunidades
{
    public const GENERAL = 'Comunidad general de cirugía';

    public function asegurar(Hospital $hospital): void
    {
        DB::transaction(function () use ($hospital): void {
            $especialidades = ProcedimientoQuirurgico::query()->withoutGlobalScope(HospitalScope::class)
                ->where('hospital_id', $hospital->id)
                ->distinct()
                ->orderBy('especialidad')
                ->pluck('especialidad');

            $comunidades = [
                $this->comunidad($hospital, self::GENERAL, null, 'Casos, protocolos y lecciones que cruzan especialidades: anestesia, enfermería quirúrgica, esterilización, programación de salas.'),
            ];

            foreach ($especialidades as $especialidad) {
                $comunidades[] = $this->comunidad(
                    $hospital,
                    "Comunidad de {$especialidad}",
                    (string) $especialidad,
                    "Revisión de casos, protocolos y costos de {$especialidad}.",
                );
            }

            $usuarios = User::query()
                ->where('hospital_id', $hospital->id)
                ->whereIn('role', [RolUsuario::AdminHospital->value, RolUsuario::Profesional->value])
                ->where('activo', true)
                ->with('recursoHumano')
                ->get();

            foreach ($comunidades as $comunidad) {
                foreach ($usuarios as $usuario) {
                    $esAdmin = $usuario->isAdminHospital();
                    $esDeLaEspecialidad = $comunidad->especialidad === null
                        || $usuario->recursoHumano?->especialidad === $comunidad->especialidad
                        // Anestesia y enfermería sirven a todas las especialidades.
                        || in_array($usuario->recursoHumano?->rol, ['anestesiologo', 'instrumentador', 'circulante'], true);

                    if (! $esAdmin && ! $esDeLaEspecialidad) {
                        continue;
                    }

                    $comunidad->miembros()->syncWithoutDetaching([
                        $usuario->id => ['rol' => $esAdmin ? 'moderador' : 'miembro'],
                    ]);
                }
            }
        });
    }

    protected function comunidad(Hospital $hospital, string $nombre, ?string $especialidad, string $descripcion): ComunidadPractica
    {
        return ComunidadPractica::query()->withoutGlobalScope(HospitalScope::class)->firstOrCreate(
            ['hospital_id' => $hospital->id, 'nombre' => $nombre],
            ['especialidad' => $especialidad, 'descripcion' => $descripcion, 'activa' => true],
        );
    }

    public function puedeParticipar(User $usuario, ComunidadPractica $comunidad): bool
    {
        return $usuario->gestionaConocimiento()
            || $comunidad->miembros()->whereKey($usuario->id)->exists();
    }

    public function puedeModerar(User $usuario, ComunidadPractica $comunidad): bool
    {
        return $usuario->gestionaConocimiento()
            || $comunidad->miembros()->whereKey($usuario->id)->wherePivot('rol', 'moderador')->exists();
    }
}
