<?php

namespace App\Http\Middleware;

use App\Enums\EstadoAlerta;
use App\Enums\EstadoLeccion;
use App\Enums\SeveridadAlerta;
use App\Models\AlertaConocimiento;
use App\Models\AlertaSobrecosto;
use App\Models\Hospital;
use App\Models\LeccionAprendida;
use App\Models\Scopes\HospitalScope;
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
            // Contador de sobrecostos sin revisar. Va en las props compartidas
            // —y no en la vista de alertas— justamente para que se vea desde
            // cualquier pantalla: una alerta que solo existe dentro de su
            // propia página nadie la descubre.
            'alertasPendientes' => fn (): int => $this->alertasPendientes($request),
            'conocimientoPendientes' => fn (): array => $this->conocimientoPendientes($request),
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
        $hospitalId = $this->hospitalActivoId($request);

        if ($hospitalId === null) {
            return null;
        }

        return Hospital::query()->find($hospitalId)?->only(['id', 'nombre']);
    }

    /**
     * Sobrecostos detectados y todavía sin causa en el hospital activo.
     *
     * El digitador queda fuera: no tiene acceso al módulo de costeo, y un
     * contador que no puede abrir solo sería ansiedad.
     */
    protected function alertasPendientes(Request $request): int
    {
        /** @var User|null $user */
        $user = $request->user();
        $hospitalId = $this->hospitalActivoId($request);

        if ($user === null || $user->isDigitador() || $user->isProfesional() || $hospitalId === null) {
            return 0;
        }

        // Filtro explícito en vez del scope global: las props compartidas se
        // resuelven al renderizar la respuesta y no todas las rutas pasan por
        // `hospital.contexto`, así que aquí el contexto puede no estar puesto.
        return AlertaSobrecosto::withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospitalId)
            ->pendientes()
            ->count();
    }

    /**
     * Pendientes de la Capa 4 para los contadores del menú: alertas críticas
     * y lecciones por validar (gestión), o borradores devueltos (profesional).
     *
     * @return array{alertas: int, revision: int, devueltas: int}
     */
    protected function conocimientoPendientes(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $hospitalId = $this->hospitalActivoId($request);
        $vacio = ['alertas' => 0, 'revision' => 0, 'devueltas' => 0];

        if ($user === null || $user->isDigitador() || $hospitalId === null) {
            return $vacio;
        }

        $devueltas = LeccionAprendida::withoutGlobalScope(HospitalScope::class)
            ->where('hospital_id', $hospitalId)
            ->where('autor_id', $user->id)
            ->where('estado', EstadoLeccion::Borrador->value)
            ->whereNotNull('motivo_devolucion')
            ->count();

        if (! $user->gestionaConocimiento()) {
            return ['devueltas' => $devueltas] + $vacio;
        }

        return [
            'alertas' => AlertaConocimiento::withoutGlobalScope(HospitalScope::class)
                ->where('hospital_id', $hospitalId)
                ->where('estado', EstadoAlerta::Pendiente->value)
                ->where('severidad', SeveridadAlerta::Critica->value)
                ->count(),
            'revision' => LeccionAprendida::withoutGlobalScope(HospitalScope::class)
                ->where('hospital_id', $hospitalId)
                ->where('estado', EstadoLeccion::EnRevision->value)
                ->count(),
            'devueltas' => $devueltas,
        ];
    }

    /** Id del hospital activo, con la misma regla por rol que el switcher. */
    protected function hospitalActivoId(Request $request): ?int
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $hospitalId = $user->isSuperAdmin()
            ? $request->session()->get(SetHospitalContext::SESSION_KEY)
            : $user->hospital_id;

        return $hospitalId !== null ? (int) $hospitalId : null;
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

        return array_values(Hospital::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Hospital $h): array => ['id' => $h->id, 'nombre' => $h->nombre])
            ->all());
    }
}
