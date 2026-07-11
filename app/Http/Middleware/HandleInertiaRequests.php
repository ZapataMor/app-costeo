<?php

namespace App\Http\Middleware;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'hospital' => [
                'activo' => fn (): ?array => $this->hospitalActivo($request),
                'disponibles' => fn (): array => $this->hospitalesDisponibles($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Hospital activo del usuario: el propio para un admin_hospital,
     * el seleccionado en sesión (switcher) para un super_admin.
     *
     * @return array{id: int, nombre: string}|null
     */
    protected function hospitalActivo(Request $request): ?array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $hospitalId = $user->isSuperAdmin()
            ? $request->session()->get(SetHospitalContext::SESSION_KEY)
            : $user->hospital_id;

        if ($hospitalId === null) {
            return null;
        }

        return Hospital::query()->find($hospitalId)?->only(['id', 'nombre']);
    }

    /**
     * Hospitales para el switcher (solo super_admin).
     *
     * @return list<array{id: int, nombre: string}>
     */
    protected function hospitalesDisponibles(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            return [];
        }

        return Hospital::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Hospital $h): array => $h->only(['id', 'nombre']))
            ->all();
    }
}
