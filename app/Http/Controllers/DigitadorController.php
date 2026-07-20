<?php

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use App\Http\Requests\StoreDigitadorRequest;
use App\Models\RegistroActividad;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de digitadores por parte del administrador de un hospital.
 * Cada digitador queda atado al hospital activo del administrador y solo
 * puede registrar procedimientos.
 */
class DigitadorController extends Controller
{
    public function index(): Response
    {
        $digitadores = User::query()
            ->where('role', RolUsuario::Digitador->value)
            ->where('hospital_id', HospitalContext::id())
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'activo', 'created_at'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'activo' => $u->activo,
                'created_at' => $u->created_at?->toDateString(),
            ]);

        return Inertia::render('digitadores/index', [
            'digitadores' => $digitadores,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('digitadores/create');
    }

    public function store(StoreDigitadorRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $digitador = new User;
        $digitador->name = $data['name'];
        $digitador->email = $data['email'];
        $digitador->password = $data['password'];
        $digitador->hospital_id = HospitalContext::id();
        $digitador->role = RolUsuario::Digitador;
        $digitador->activo = true;
        $digitador->email_verified_at = now();
        $digitador->save();

        RegistroActividad::registrar(
            'creó digitador',
            "{$request->user()->name} creó al digitador «{$digitador->name}»",
            auditable: $digitador,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Digitador creado.']);

        return redirect()->route('digitadores.index');
    }

    /**
     * Activa o desactiva un digitador del propio hospital. El binding no
     * aplica el HospitalScope (es un User), así que validamos el hospital.
     */
    public function toggleActivo(User $usuario): RedirectResponse
    {
        abort_unless(
            $usuario->isDigitador() && $usuario->hospital_id === HospitalContext::id(),
            403,
        );

        $usuario->activo = ! $usuario->activo;
        $usuario->save();

        RegistroActividad::registrar(
            $usuario->activo ? 'activó digitador' : 'desactivó digitador',
            sprintf(
                '%s %s al digitador «%s»',
                auth()->user()->name,
                $usuario->activo ? 'activó' : 'desactivó',
                $usuario->name,
            ),
            auditable: $usuario,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $usuario->activo ? 'Digitador activado.' : 'Digitador desactivado.',
        ]);

        return redirect()->route('digitadores.index');
    }
}
