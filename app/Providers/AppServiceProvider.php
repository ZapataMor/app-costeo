<?php

namespace App\Providers;

use App\Models\User;
use App\Support\HospitalContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Autorización por rol (multi-hospital).
     */
    protected function configureGates(): void
    {
        // Solo el super_admin puede cambiar de hospital con el switcher.
        Gate::define('elegir-hospital', fn (User $user): bool => $user->isSuperAdmin());

        // Crear/editar datos exige un hospital activo: el admin siempre lo
        // tiene (middleware); el super_admin debe seleccionar uno primero.
        Gate::define('operar-hospital', fn (User $user): bool => HospitalContext::id() !== null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
