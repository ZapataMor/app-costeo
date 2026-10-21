<?php

namespace Tests\Feature\Conocimiento;

use App\Models\ComunidadPractica;
use App\Models\HiloComunidad;
use App\Models\LeccionAprendida;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

class ComunidadesYProfesionalesTest extends ConocimientoTestCase
{
    public function test_hay_una_comunidad_general_y_una_por_especialidad_con_membresia_derivada(): void
    {
        $cirujana = $this->profesional();

        $this->actingAs($cirujana)->get('/conocimiento/comunidades')->assertOk();

        $general = ComunidadPractica::query()->where('nombre', 'Comunidad general de cirugía')->firstOrFail();
        $gineco = ComunidadPractica::query()->where('especialidad', 'Ginecobstetricia')->firstOrFail();

        $this->assertTrue($general->miembros()->whereKey($cirujana->id)->exists());
        $this->assertTrue($gineco->miembros()->whereKey($cirujana->id)->exists());
        $this->assertTrue($gineco->miembros()->whereKey($this->admin->id)->wherePivot('rol', 'moderador')->exists());
    }

    public function test_un_miembro_abre_un_hilo_responde_y_lo_convierte_en_leccion(): void
    {
        $cirujana = $this->profesional();
        $this->actingAs($cirujana)->get('/conocimiento/comunidades');
        $comunidad = ComunidadPractica::query()->where('especialidad', 'Ginecobstetricia')->firstOrFail();

        $this->actingAs($cirujana)->post("/conocimiento/comunidades/{$comunidad->id}/hilos", [
            'titulo' => 'Cesáreas fuera de estándar',
            'cuerpo' => 'La mitad de las cesáreas sale en sobrecosto. ¿Revisamos el estándar?',
            'enlazable_tipo' => 'procedimiento',
            'enlazable_id' => $this->procedimiento->id,
        ])->assertRedirect();
        $hilo = HiloComunidad::query()->firstOrFail();

        $this->actingAs($this->admin)->post("/conocimiento/hilos/{$hilo->id}/respuestas", ['cuerpo' => 'Ajustemos a 80 minutos.'])->assertRedirect();
        $respuesta = $hilo->respuestas()->firstOrFail();

        // Quien preguntó marca la solución.
        $this->actingAs($cirujana)->patch("/conocimiento/hilos/{$hilo->id}/respuestas/{$respuesta->id}/solucion")->assertRedirect();
        $this->assertTrue($respuesta->fresh()?->es_solucion);

        $this->actingAs($cirujana)->get("/conocimiento/lecciones/create?hilo={$hilo->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('inicial.hilo_id', $hilo->id)
                ->where('inicial.leccion', 'Ajustemos a 80 minutos.'));

        $this->actingAs($cirujana)->post('/conocimiento/lecciones', [
            'tipo' => 'leccion',
            'titulo' => 'Cesáreas fuera de estándar',
            'categoria' => 'protocolo',
            'contexto' => 'La mitad de las cesáreas sale en sobrecosto frente al estándar.',
            'leccion' => 'El estándar de 120 minutos no corresponde a la práctica actual.',
            'hilo_id' => $hilo->id,
        ])->assertRedirect();

        $this->assertSame(LeccionAprendida::query()->value('id'), $hilo->fresh()?->leccion_aprendida_id);
    }

    public function test_quien_no_es_miembro_no_responde(): void
    {
        $this->actingAs($this->admin)->get('/conocimiento/comunidades');
        $comunidad = ComunidadPractica::query()->where('especialidad', 'Ginecobstetricia')->firstOrFail();
        $hilo = $comunidad->hilos()->create([
            'hospital_id' => $this->hospital->id,
            'autor_id' => $this->admin->id,
            'titulo' => 'Hilo',
            'cuerpo' => 'Planteamiento del hilo',
            'ultima_actividad_en' => now(),
        ]);

        // Un ortopedista no entra a la comunidad de ginecobstetricia.
        $ortopedista = $this->recursos['cirujano']->replicate();
        $ortopedista->especialidad = 'Ortopedia y traumatología';
        $ortopedista->save();
        $externo = $this->profesional($ortopedista);

        $this->actingAs($externo)->post("/conocimiento/hilos/{$hilo->id}/respuestas", ['cuerpo' => 'Opino'])->assertForbidden();
    }

    public function test_el_administrador_crea_la_cuenta_del_profesional_y_este_entra_a_sus_modulos(): void
    {
        $this->actingAs($this->admin)->post('/conocimiento/profesionales', [
            'recurso_humano_id' => $this->recursos['anestesiologo']->id,
            'name' => 'Jorge Ipuana',
            'email' => 'jorge.ipuana@hospital.test',
            'password' => 'Clave-Segura-2026!',
            'password_confirmation' => 'Clave-Segura-2026!',
        ])->assertRedirect('/conocimiento/profesionales');

        $profesional = User::query()->where('email', 'jorge.ipuana@hospital.test')->firstOrFail();
        $this->assertTrue($profesional->isProfesional());
        $this->assertSame($this->recursos['anestesiologo']->id, $profesional->recurso_humano_id);

        // Un registro de recurso humano, una cuenta.
        $this->actingAs($this->admin)->post('/conocimiento/profesionales', [
            'recurso_humano_id' => $this->recursos['anestesiologo']->id,
            'name' => 'Otro',
            'email' => 'otro@hospital.test',
            'password' => 'Clave-Segura-2026!',
            'password_confirmation' => 'Clave-Segura-2026!',
        ])->assertSessionHasErrors('recurso_humano_id');

        $this->actingAs($profesional)->get('/')->assertRedirect(route('conocimiento.lecciones.index'));
        $this->actingAs($profesional)->get('/conocimiento/lecciones')->assertOk();
        $this->actingAs($profesional)->get('/conocimiento/mejores-practicas')->assertOk();
        $this->actingAs($profesional)->get('/conocimiento')->assertForbidden();
        $this->actingAs($profesional)->get('/costeo')->assertForbidden();
        $this->actingAs($profesional)->get('/parametros')->assertForbidden();
    }
}
