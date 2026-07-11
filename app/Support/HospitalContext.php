<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve el hospital (tenant) activo de la petición actual.
 *
 * Por defecto es el hospital del usuario autenticado; los seeders,
 * comandos y tests pueden fijarlo explícitamente con set().
 */
class HospitalContext
{
    protected static ?int $hospitalId = null;

    public static function set(?int $hospitalId): void
    {
        static::$hospitalId = $hospitalId;
    }

    public static function id(): ?int
    {
        if (static::$hospitalId !== null) {
            return static::$hospitalId;
        }

        /** @var User|null $user */
        $user = Auth::user();

        // Un super_admin solo tiene hospital activo cuando lo fija
        // explícitamente (switcher → middleware → set()); su hospital_id
        // personal nunca activa el scope.
        if ($user === null || $user->isSuperAdmin()) {
            return null;
        }

        return $user->hospital_id;
    }

    public static function clear(): void
    {
        static::$hospitalId = null;
    }
}
