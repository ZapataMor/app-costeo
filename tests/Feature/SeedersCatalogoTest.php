<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Insumo;
use App\Models\PlantillaInsumo;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RecursoHumano;
use App\Models\SalaOperatoria;
use App\Models\User;
use App\Support\HospitalContext;
use Database\Seeders\CatalogoQuirurgicoSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedersCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    protected function sembrarTodo(): void
    {
        $this->seed(HospitalSeeder::class);
        $this->seed(UsuarioSeeder::class);
        $this->seed(CatalogoQuirurgicoSeeder::class);
    }

    public function test_el_catalogo_deja_cada_hospital_listo_para_registrar_cirugias(): void
    {
        $this->sembrarTodo();

        foreach (Hospital::all() as $hospital) {
            HospitalContext::set($hospital->id);

            $this->assertGreaterThan(0, SalaOperatoria::count(), "{$hospital->nombre} sin salas.");
            $this->assertGreaterThan(0, ProcedimientoQuirurgico::count(), "{$hospital->nombre} sin procedimientos.");
            $this->assertGreaterThan(0, Insumo::count(), "{$hospital->nombre} sin insumos.");

            // Los cinco roles quirúrgicos deben estar cubiertos.
            $roles = RecursoHumano::query()->pluck('rol')->unique()->sort()->values()->all();
            $this->assertSame(
                ['anestesiologo', 'ayudante', 'circulante', 'cirujano', 'instrumentador'],
                $roles,
                "{$hospital->nombre} no cubre los cinco roles quirúrgicos.",
            );
        }
    }

    public function test_los_seeders_son_idempotentes(): void
    {
        $this->sembrarTodo();

        $conteos = [
            Hospital::count(),
            User::count(),
            SalaOperatoria::withoutGlobalScopes()->count(),
            RecursoHumano::withoutGlobalScopes()->count(),
            ProcedimientoQuirurgico::withoutGlobalScopes()->count(),
            Insumo::withoutGlobalScopes()->count(),
            PlantillaInsumo::count(),
        ];

        $this->sembrarTodo();

        $this->assertSame($conteos, [
            Hospital::count(),
            User::count(),
            SalaOperatoria::withoutGlobalScopes()->count(),
            RecursoHumano::withoutGlobalScopes()->count(),
            ProcedimientoQuirurgico::withoutGlobalScopes()->count(),
            Insumo::withoutGlobalScopes()->count(),
            PlantillaInsumo::count(),
        ], 'Reejecutar los seeders duplicó registros.');
    }

    public function test_el_seeder_de_usuarios_aplica_la_contrasena_configurada(): void
    {
        config(['seeding.user_password' => 'password']);

        $this->sembrarTodo();

        $this->assertGreaterThan(0, User::count());

        foreach (User::all() as $usuario) {
            $this->assertTrue(
                Hash::check('password', $usuario->password),
                "El usuario {$usuario->email} no quedó con la contraseña configurada.",
            );
        }
    }

    public function test_sin_contrasena_configurada_cada_usuario_recibe_una_aleatoria(): void
    {
        // Es el caso de producción sin SEED_USER_PASSWORD: nadie debe quedar
        // con una contraseña adivinable, y no deben repetirse entre usuarios.
        config(['seeding.user_password' => null]);

        $this->sembrarTodo();

        $hashes = [];

        foreach (User::all() as $usuario) {
            $this->assertFalse(
                Hash::check('password', $usuario->password),
                "El usuario {$usuario->email} quedó con una contraseña adivinable.",
            );
            $hashes[] = $usuario->password;
        }

        $this->assertSame($hashes, array_unique($hashes), 'Dos usuarios comparten contraseña.');
    }

    public function test_reejecutar_el_seeder_de_usuarios_no_cambia_la_contrasena(): void
    {
        $this->sembrarTodo();

        $usuario = User::where('email', 'superadmin@demo.test')->firstOrFail();
        $hashOriginal = $usuario->password;

        $this->seed(UsuarioSeeder::class);

        $this->assertSame($hashOriginal, $usuario->fresh()->password);
    }

    public function test_cada_hospital_solo_ve_su_propio_catalogo(): void
    {
        $this->sembrarTodo();

        $hospitales = Hospital::all();
        $this->assertGreaterThanOrEqual(2, $hospitales->count());

        foreach ($hospitales as $hospital) {
            HospitalContext::set($hospital->id);

            $ajenos = Insumo::query()->where('hospital_id', '!=', $hospital->id)->count();
            $this->assertSame(0, $ajenos, "El scope de {$hospital->nombre} filtró insumos de otro hospital.");
        }
    }
}
