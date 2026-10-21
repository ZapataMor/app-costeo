<?php

namespace App\Http\Controllers\Instituciones;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioInstitucionRequest;
use App\Http\Requests\UpdateUsuarioInstitucionRequest;
use App\Models\Hospital;
use App\Models\RegistroActividad;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Usuarios de una institución, gestionados por el super_admin desde el
 * detalle de la institución: administradores del hospital y digitadores.
 *
 * El binding de User no lleva HospitalScope, así que cada acción comprueba
 * que el usuario pertenece a la institución de la URL antes de tocarlo.
 */
class UsuarioInstitucionController extends Controller
{
    public function store(StoreUsuarioInstitucionRequest $request, Hospital $hospital): RedirectResponse
    {
        $data = $request->validated();

        $usuario = new User;
        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
        $usuario->password = $data['password'];
        $usuario->hospital_id = $hospital->id;
        $usuario->role = RolUsuario::from($data['role']);
        $usuario->activo = true;
        $usuario->email_verified_at = now();
        $usuario->save();

        RegistroActividad::registrar(
            'creó usuario',
            "{$request->user()->name} creó al usuario «{$usuario->name}» ({$this->etiquetaRol($usuario)}) en «{$hospital->nombre}»",
            auditable: $usuario,
            hospitalId: $hospital->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario creado.']);

        return redirect()->route('instituciones.show', $hospital);
    }

    public function update(UpdateUsuarioInstitucionRequest $request, Hospital $hospital, User $usuario): RedirectResponse
    {
        $this->verificarPertenencia($hospital, $usuario);

        $data = $request->validated();

        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
        $usuario->role = RolUsuario::from($data['role']);

        if (! empty($data['password'])) {
            $usuario->password = $data['password'];
        }

        $usuario->save();

        RegistroActividad::registrar(
            'actualizó usuario',
            "{$request->user()->name} actualizó al usuario «{$usuario->name}» de «{$hospital->nombre}»",
            auditable: $usuario,
            hospitalId: $hospital->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado.']);

        return redirect()->route('instituciones.show', $hospital);
    }

    public function toggleActivo(Hospital $hospital, User $usuario): RedirectResponse
    {
        $this->verificarPertenencia($hospital, $usuario);

        $usuario->activo = ! $usuario->activo;
        $usuario->save();

        RegistroActividad::registrar(
            $usuario->activo ? 'activó usuario' : 'desactivó usuario',
            sprintf(
                '%s %s al usuario «%s» de «%s»',
                auth()->user()->name,
                $usuario->activo ? 'activó' : 'desactivó',
                $usuario->name,
                $hospital->nombre,
            ),
            auditable: $usuario,
            hospitalId: $hospital->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $usuario->activo ? 'Usuario activado.' : 'Usuario desactivado.',
        ]);

        return redirect()->route('instituciones.show', $hospital);
    }

    /**
     * Eliminar solo tiene sentido para cuentas sin actividad (p. ej. un
     * alta equivocada). Una cuenta que ya registró algo se desactiva, para
     * que el historial siga apuntando a alguien.
     */
    public function destroy(Hospital $hospital, User $usuario): RedirectResponse
    {
        $this->verificarPertenencia($hospital, $usuario);

        if (RegistroActividad::query()->where('user_id', $usuario->id)->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: el usuario ya tiene actividad registrada. Puedes desactivarlo.',
            ]);

            return back();
        }

        $nombre = $usuario->name;
        $usuario->delete();

        RegistroActividad::registrar(
            'eliminó usuario',
            auth()->user()->name." eliminó al usuario «{$nombre}» de «{$hospital->nombre}»",
            hospitalId: $hospital->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario eliminado.']);

        return redirect()->route('instituciones.show', $hospital);
    }

    protected function verificarPertenencia(Hospital $hospital, User $usuario): void
    {
        abort_unless(
            $usuario->hospital_id === $hospital->id && ! $usuario->isSuperAdmin(),
            404,
        );
    }

    protected function etiquetaRol(User $usuario): string
    {
        return match ($usuario->role) {
            RolUsuario::AdminHospital => 'administrador',
            RolUsuario::Digitador => 'digitador',
            RolUsuario::SuperAdmin => 'super administrador',
            RolUsuario::Profesional => 'profesional',
        };
    }
}
