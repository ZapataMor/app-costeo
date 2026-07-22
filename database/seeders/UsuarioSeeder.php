<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\Hospital;
use App\Models\User;
use Database\Seeders\Concerns\InformaEnConsola;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Usuarios de la instalación: el super admin, y un administrador y varios
 * digitadores por hospital.
 *
 * Varios digitadores por hospital no es adorno: es lo que permite ver en el
 * explorador quién registró cada cirugía, que es la trazabilidad que pide el
 * módulo de registro.
 *
 * La contraseña no está escrita en este archivo, sale de `config/seeding.php`:
 * «password» fuera de producción, y en producción lo que diga
 * SEED_USER_PASSWORD (o una aleatoria por usuario, impresa una sola vez, si
 * esa variable no está definida).
 *
 * Idempotente: la clave es el correo. Si el usuario ya existe se actualizan
 * nombre, rol y hospital, pero NUNCA la contraseña — así reejecutar el seeder
 * no le revienta el acceso a alguien que ya la cambió.
 */
class UsuarioSeeder extends Seeder
{
    use InformaEnConsola;

    /**
     * `hospital_nit` en null = usuario global (super admin).
     *
     * @var list<array{name: string, email: string, role: RolUsuario, hospital_nit: string|null}>
     */
    public const USUARIOS = [
        [
            'name' => 'Super Administrador',
            'email' => 'superadmin@demo.test',
            'role' => RolUsuario::SuperAdmin,
            'hospital_nit' => null,
        ],
        [
            'name' => 'Admin San Rafael',
            'email' => 'admin@sanrafael.test',
            'role' => RolUsuario::AdminHospital,
            'hospital_nit' => '800100200-1',
        ],
        [
            'name' => 'Digitador San Rafael',
            'email' => 'digitador@sanrafael.test',
            'role' => RolUsuario::Digitador,
            'hospital_nit' => '800100200-1',
        ],
        [
            'name' => 'Yamile Barros [SEMILLA]',
            'email' => 'yamile.barros@sanrafael.test',
            'role' => RolUsuario::Digitador,
            'hospital_nit' => '800100200-1',
        ],
        [
            'name' => 'Nelson Curvelo [SEMILLA]',
            'email' => 'nelson.curvelo@sanrafael.test',
            'role' => RolUsuario::Digitador,
            'hospital_nit' => '800100200-1',
        ],
        [
            'name' => 'Admin Riohacha',
            'email' => 'admin@riohacha.test',
            'role' => RolUsuario::AdminHospital,
            'hospital_nit' => '800300400-2',
        ],
        [
            'name' => 'Digitador Riohacha',
            'email' => 'digitador@riohacha.test',
            'role' => RolUsuario::Digitador,
            'hospital_nit' => '800300400-2',
        ],
        [
            'name' => 'Marbel Choles [SEMILLA]',
            'email' => 'marbel.choles@riohacha.test',
            'role' => RolUsuario::Digitador,
            'hospital_nit' => '800300400-2',
        ],
    ];

    public function run(): void
    {
        /** @var array<string, int> $hospitales NIT → id */
        $hospitales = Hospital::query()->pluck('id', 'nit')->all();

        /** @var list<array{string, string}> $generadas */
        $generadas = [];

        foreach (self::USUARIOS as $datos) {
            $nit = $datos['hospital_nit'];

            if ($nit !== null && ! isset($hospitales[$nit])) {
                $this->advertir("Sin hospital con NIT {$nit}: se omite {$datos['email']}. Ejecuta HospitalSeeder primero.");

                continue;
            }

            $usuario = User::firstOrNew(['email' => $datos['email']]);
            $esNuevo = ! $usuario->exists;

            // `role`, `activo` y `hospital_id` no son mass-assignable en User.
            $usuario->forceFill([
                'name' => $datos['name'],
                'role' => $datos['role']->value,
                'hospital_id' => $nit === null ? null : $hospitales[$nit],
                'activo' => true,
                'email_verified_at' => $usuario->email_verified_at ?? now(),
            ]);

            if ($esNuevo) {
                $clave = $this->clave();
                // El cast 'hashed' del modelo se encarga del Hash::make.
                $usuario->password = $clave;
                $generadas[] = [$datos['email'], $clave];
            }

            $usuario->save();
        }

        $this->reportar($generadas);
    }

    protected function desdeConfig(): bool
    {
        return filled(config('seeding.user_password'));
    }

    /**
     * Contraseña de configuración, o una aleatoria fuerte por usuario.
     */
    protected function clave(): string
    {
        $configurada = config('seeding.user_password');

        return filled($configurada) ? (string) $configurada : Str::password(20);
    }

    /**
     * @param  list<array{string, string}>  $generadas
     */
    protected function reportar(array $generadas): void
    {
        if ($generadas === []) {
            $this->informar('Usuarios ya existentes: no se modificó ninguna contraseña.');

            return;
        }

        $this->advertir(
            $this->desdeConfig()
                ? 'Usuarios creados con la contraseña de config/seeding.php:'
                : 'Contraseñas generadas — cópialas AHORA, no se vuelven a mostrar:',
        );
        $this->tabla(['Correo', 'Contraseña'], $generadas);
    }
}
