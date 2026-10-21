<?php

namespace App\Http\Controllers\Conocimiento;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conocimiento\GuardarProfesionalRequest;
use App\Models\Hospital;
use App\Models\RecursoHumano;
use App\Models\RegistroActividad;
use App\Models\User;
use App\Services\Conocimiento\GestorComunidades;
use App\Support\HospitalContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cuentas del personal quirúrgico (rol `profesional`).
 *
 * Cada cuenta se ata a un registro de recurso humano: es lo que permite
 * marcar «su» fila en el benchmarking anónimo y sumarla a la comunidad de su
 * especialidad sin que nadie la configure a mano.
 */
class ProfesionalController extends Controller
{
    public function index(): Response
    {
        $profesionales = User::query()
            ->where('hospital_id', HospitalContext::id())
            ->where('role', RolUsuario::Profesional->value)
            ->with('recursoHumano:id,nombre,rol,especialidad')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'activo' => $u->activo,
                'recurso' => $u->recursoHumano?->only(['id', 'nombre', 'rol', 'especialidad']),
            ])
            ->all();

        return Inertia::render('conocimiento/profesionales/index', ['profesionales' => $profesionales]);
    }

    public function create(): Response
    {
        $conCuenta = User::query()->whereNotNull('recurso_humano_id')->pluck('recurso_humano_id');

        return Inertia::render('conocimiento/profesionales/create', [
            'recursos' => RecursoHumano::query()
                ->where('activo', true)
                ->whereNotIn('id', $conCuenta)
                ->orderBy('rol')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'rol', 'especialidad'])
                ->map(fn (RecursoHumano $r): array => $r->only(['id', 'nombre', 'rol', 'especialidad']))
                ->all(),
        ]);
    }

    public function store(GuardarProfesionalRequest $request, GestorComunidades $comunidades): RedirectResponse
    {
        $datos = $request->validated();

        $usuario = new User;
        $usuario->name = $datos['name'];
        $usuario->email = $datos['email'];
        $usuario->password = $datos['password'];
        $usuario->hospital_id = HospitalContext::id();
        $usuario->recurso_humano_id = (int) $datos['recurso_humano_id'];
        $usuario->role = RolUsuario::Profesional;
        $usuario->activo = true;
        $usuario->email_verified_at = now();
        $usuario->save();

        // Entra de una vez a las comunidades que le corresponden.
        $comunidades->asegurar(Hospital::query()->findOrFail(HospitalContext::id()));

        RegistroActividad::registrar('creó', "Creó la cuenta del profesional {$usuario->name}", $usuario);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Cuenta creada para {$usuario->name}."]);

        return redirect()->route('conocimiento.profesionales.index');
    }

    public function toggleActivo(User $usuario): RedirectResponse
    {
        abort_unless($usuario->hospital_id === HospitalContext::id() && $usuario->isProfesional(), 404);

        $usuario->activo = ! $usuario->activo;
        $usuario->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $usuario->activo ? 'Cuenta activada.' : 'Cuenta desactivada.']);

        return back();
    }
}
