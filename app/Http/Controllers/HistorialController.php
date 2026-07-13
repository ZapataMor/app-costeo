<?php

namespace App\Http\Controllers;

use App\Models\RegistroActividad;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historial de actividad: bitácora de quién hizo qué y cuándo dentro
 * del aplicativo. El super_admin ve toda la actividad (o la del
 * hospital activo si seleccionó uno); un admin_hospital solo ve la de
 * su hospital.
 */
class HistorialController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $registros = RegistroActividad::query()
            ->with(['user:id,name,email', 'hospital:id,nombre'])
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where(
                fn ($sub) => $sub->where('hospital_id', $user->hospital_id)
                    ->orWhere('user_id', $user->id),
            ))
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (RegistroActividad $r): array => [
                'id' => $r->id,
                'usuario' => $r->user?->name ?? 'Usuario eliminado',
                'email' => $r->user?->email,
                'accion' => $r->accion,
                'descripcion' => $r->descripcion,
                'hospital' => $r->hospital?->nombre,
                'ip' => $r->ip,
                'fecha' => $r->created_at->timezone('America/Bogota')->format('d/m/Y'),
                'hora' => $r->created_at->timezone('America/Bogota')->format('h:i:s a'),
            ]);

        return Inertia::render('historial/index', [
            'registros' => $registros,
        ]);
    }
}
