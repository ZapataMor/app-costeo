<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Hospital;
use App\Models\Paciente;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstitucionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Hospital $hospital;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->hospital = Hospital::factory()->create(['nombre' => 'Hospital Uno']);
        $this->admin = User::factory()->create(['hospital_id' => $this->hospital->id]);
    }

    protected function tearDown(): void
    {
        HospitalContext::clear();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    protected function datosInstitucion(array $extra = []): array
    {
        return [
            'nombre' => 'Clínica Nueva',
            'nit' => '900123456-7',
            'nivel_complejidad' => 'alta',
            'municipio' => 'Riohacha',
            'departamento' => 'La Guajira',
            'horas_dia' => 10,
            'dias_mes' => 24,
            'minutos_efectivos_hora' => 50,
            'factor_indirecto' => 0.1,
            ...$extra,
        ];
    }

    public function test_el_super_admin_lista_las_instituciones_con_conteos(): void
    {
        User::factory()->digitador()->create(['hospital_id' => $this->hospital->id]);
        Hospital::factory()->create(['nombre' => 'Hospital Dos']);

        $this->actingAs($this->superAdmin)
            ->get('/instituciones')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('instituciones/index')
                ->has('instituciones.data', 2)
                ->where('instituciones.data.0.nombre', 'Hospital Dos')
                ->where('instituciones.data.1.nombre', 'Hospital Uno')
                ->where('instituciones.data.1.usuarios', 2)
                ->where('instituciones.data.1.cirugias', 0)
                ->has('nivelesComplejidad', 3));
    }

    public function test_el_listado_busca_por_nombre_nit_o_municipio(): void
    {
        Hospital::factory()->create(['nombre' => 'Hospital Dos', 'nit' => '777777777-7']);

        $this->actingAs($this->superAdmin)
            ->get('/instituciones?q=777777')
            ->assertInertia(fn (Assert $page) => $page
                ->has('instituciones.data', 1)
                ->where('instituciones.data.0.nombre', 'Hospital Dos')
                ->where('filtros.q', '777777'));
    }

    public function test_el_super_admin_crea_una_institucion(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/instituciones', $this->datosInstitucion())
            ->assertRedirect();

        $creada = Hospital::where('nit', '900123456-7')->firstOrFail();
        $this->assertSame('Clínica Nueva', $creada->nombre);
        $this->assertSame('alta', $creada->nivel_complejidad);
        $this->assertSame(10 * 24 * 50, $creada->minutosDisponiblesMes());
        $this->assertEqualsWithDelta(0.1, (float) $creada->factor_indirecto, 0.0001);

        $this->assertDatabaseHas('registros_actividad', [
            'user_id' => $this->superAdmin->id,
            'accion' => 'creó institución',
            'auditable_id' => $creada->id,
            'hospital_id' => $creada->id,
        ]);
    }

    public function test_valida_nit_unico_y_rangos_de_capacidad(): void
    {
        $this->actingAs($this->superAdmin)
            ->from('/instituciones/create')
            ->post('/instituciones', $this->datosInstitucion([
                'nit' => $this->hospital->nit,
                'nivel_complejidad' => 'mediana',
                'horas_dia' => 30,
                'factor_indirecto' => 1.5,
            ]))
            ->assertRedirect('/instituciones/create')
            ->assertSessionHasErrors(['nit', 'nivel_complejidad', 'horas_dia', 'factor_indirecto']);
    }

    public function test_el_detalle_muestra_la_institucion_y_sus_usuarios(): void
    {
        $ajeno = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id]);

        $this->actingAs($this->superAdmin)
            ->get("/instituciones/{$this->hospital->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('instituciones/show')
                ->where('institucion.id', $this->hospital->id)
                ->where('institucion.minutos_disponibles_mes', $this->hospital->minutosDisponiblesMes())
                ->has('usuarios', 1)
                ->where('usuarios.0.id', $this->admin->id)
                ->where('usuarios.0.role', 'admin_hospital')
                ->where('rolesAsignables', ['admin_hospital', 'digitador']));

        $this->assertNotSame($ajeno->id, $this->admin->id);
    }

    public function test_el_super_admin_actualiza_una_institucion(): void
    {
        $this->actingAs($this->superAdmin)
            ->put("/instituciones/{$this->hospital->id}", $this->datosInstitucion([
                'nit' => $this->hospital->nit, // conservar el propio NIT no choca con unique
                'nombre' => 'Hospital Uno Renombrado',
            ]))
            ->assertRedirect("/instituciones/{$this->hospital->id}");

        $this->assertSame('Hospital Uno Renombrado', $this->hospital->fresh()->nombre);
        $this->assertDatabaseHas('registros_actividad', ['accion' => 'actualizó institución']);
    }

    public function test_elimina_una_institucion_vacia_junto_con_sus_usuarios(): void
    {
        $this->actingAs($this->superAdmin)
            ->delete("/instituciones/{$this->hospital->id}")
            ->assertRedirect('/instituciones');

        $this->assertDatabaseMissing('hospitales', ['id' => $this->hospital->id]);
        $this->assertDatabaseMissing('users', ['id' => $this->admin->id]);
        $this->assertDatabaseHas('registros_actividad', ['accion' => 'eliminó institución']);
    }

    public function test_no_elimina_una_institucion_con_datos_registrados(): void
    {
        Paciente::factory()->create(['hospital_id' => $this->hospital->id]);

        $this->actingAs($this->superAdmin)
            ->from('/instituciones')
            ->delete("/instituciones/{$this->hospital->id}")
            ->assertRedirect('/instituciones');

        $this->assertDatabaseHas('hospitales', ['id' => $this->hospital->id]);
    }

    public function test_el_super_admin_crea_un_administrador_y_un_digitador_para_la_institucion(): void
    {
        foreach (['admin_hospital', 'digitador'] as $rol) {
            $this->actingAs($this->superAdmin)
                ->post("/instituciones/{$this->hospital->id}/usuarios", [
                    'name' => "Usuario {$rol}",
                    'email' => "{$rol}@hospital.test",
                    'role' => $rol,
                    'password' => 'password1234',
                    'password_confirmation' => 'password1234',
                ])
                ->assertRedirect("/instituciones/{$this->hospital->id}");

            $creado = User::where('email', "{$rol}@hospital.test")->firstOrFail();
            $this->assertSame(RolUsuario::from($rol), $creado->role);
            $this->assertSame($this->hospital->id, $creado->hospital_id);
            $this->assertTrue($creado->activo);
            $this->assertNotNull($creado->email_verified_at);
        }

        $this->assertDatabaseHas('registros_actividad', [
            'accion' => 'creó usuario',
            'hospital_id' => $this->hospital->id,
        ]);
    }

    public function test_no_permite_crear_un_super_admin_desde_una_institucion(): void
    {
        $this->actingAs($this->superAdmin)
            ->post("/instituciones/{$this->hospital->id}/usuarios", [
                'name' => 'Intruso',
                'email' => 'intruso@hospital.test',
                'role' => 'super_admin',
                'password' => 'password1234',
                'password_confirmation' => 'password1234',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'intruso@hospital.test']);
    }

    public function test_actualiza_un_usuario_y_conserva_la_contrasena_si_va_en_blanco(): void
    {
        $hashAnterior = $this->admin->password;

        $this->actingAs($this->superAdmin)
            ->put("/instituciones/{$this->hospital->id}/usuarios/{$this->admin->id}", [
                'name' => 'Admin Renombrado',
                'email' => $this->admin->email,
                'role' => 'digitador',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect("/instituciones/{$this->hospital->id}");

        $actualizado = $this->admin->fresh();
        $this->assertSame('Admin Renombrado', $actualizado->name);
        $this->assertSame(RolUsuario::Digitador, $actualizado->role);
        $this->assertSame($hashAnterior, $actualizado->password);

        $this->actingAs($this->superAdmin)
            ->put("/instituciones/{$this->hospital->id}/usuarios/{$this->admin->id}", [
                'name' => 'Admin Renombrado',
                'email' => $this->admin->email,
                'role' => 'admin_hospital',
                'password' => 'nueva-clave-1234',
                'password_confirmation' => 'nueva-clave-1234',
            ])
            ->assertRedirect();

        $this->assertNotSame($hashAnterior, $this->admin->fresh()->password);
    }

    public function test_activa_y_desactiva_un_usuario_de_la_institucion(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch("/instituciones/{$this->hospital->id}/usuarios/{$this->admin->id}/activo")
            ->assertRedirect("/instituciones/{$this->hospital->id}");

        $this->assertFalse($this->admin->fresh()->activo);
        $this->assertDatabaseHas('registros_actividad', ['accion' => 'desactivó usuario']);

        $this->actingAs($this->superAdmin)
            ->patch("/instituciones/{$this->hospital->id}/usuarios/{$this->admin->id}/activo");

        $this->assertTrue($this->admin->fresh()->activo);
    }

    public function test_no_toca_usuarios_de_otra_institucion_ni_super_admins(): void
    {
        $otro = Hospital::factory()->create();
        $ajeno = User::factory()->create(['hospital_id' => $otro->id]);

        $this->actingAs($this->superAdmin)
            ->patch("/instituciones/{$this->hospital->id}/usuarios/{$ajeno->id}/activo")
            ->assertNotFound();

        $this->actingAs($this->superAdmin)
            ->delete("/instituciones/{$this->hospital->id}/usuarios/{$this->superAdmin->id}")
            ->assertNotFound();

        $this->assertTrue($ajeno->fresh()->activo);
    }

    public function test_elimina_un_usuario_sin_actividad_pero_no_uno_con_historial(): void
    {
        $sinActividad = User::factory()->digitador()->create(['hospital_id' => $this->hospital->id]);

        $this->actingAs($this->superAdmin)
            ->delete("/instituciones/{$this->hospital->id}/usuarios/{$sinActividad->id}")
            ->assertRedirect("/instituciones/{$this->hospital->id}");

        $this->assertDatabaseMissing('users', ['id' => $sinActividad->id]);

        // Iniciar sesión deja rastro en el historial: ya no se puede borrar.
        $this->actingAs($this->admin)->get('/parametros');
        $this->post('/logout');
        $this->actingAs($this->admin)->get('/parametros');

        $this->actingAs($this->superAdmin)
            ->from("/instituciones/{$this->hospital->id}")
            ->delete("/instituciones/{$this->hospital->id}/usuarios/{$this->admin->id}")
            ->assertRedirect("/instituciones/{$this->hospital->id}");

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_los_demas_roles_no_acceden_al_modulo(): void
    {
        $digitador = User::factory()->digitador()->create(['hospital_id' => $this->hospital->id]);

        $this->actingAs($this->admin)->get('/instituciones')->assertForbidden();
        $this->actingAs($digitador)->get('/instituciones')->assertForbidden();

        $this->actingAs($this->admin)
            ->post('/instituciones', $this->datosInstitucion())
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post("/instituciones/{$this->hospital->id}/usuarios", [
                'name' => 'X',
                'email' => 'x@hospital.test',
                'role' => 'digitador',
                'password' => 'password1234',
                'password_confirmation' => 'password1234',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('hospitales', ['nit' => '900123456-7']);
    }
}
