<?php

namespace Tests\Feature\Conocimiento;

use App\Enums\EstadoLeccion;
use App\Models\AlertaConocimiento;
use App\Models\Hospital;
use App\Models\LeccionAprendida;
use App\Models\User;
use App\Support\HospitalContext;
use Inertia\Testing\AssertableInertia as Assert;

class LeccionesAprendidasTest extends ConocimientoTestCase
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function datos(array $extra = []): array
    {
        return $extra + [
            'tipo' => 'leccion',
            'titulo' => 'Abrir trócares solo al confirmar la vía',
            'categoria' => 'costos',
            'contexto' => 'Se abrían todos los trócares antes de confirmar la vía laparoscópica.',
            'leccion' => 'Abrirlos uno a uno evita perder el set completo en las conversiones.',
            'procedimiento_quirurgico_id' => $this->procedimiento->id,
        ];
    }

    public function test_el_ciclo_borrador_revision_publicacion(): void
    {
        $profesional = $this->profesional();

        $this->actingAs($profesional)->post('/conocimiento/lecciones', $this->datos())->assertRedirect();
        $leccion = LeccionAprendida::query()->firstOrFail();
        $this->assertSame(EstadoLeccion::Borrador, $leccion->estado);
        $this->assertSame($profesional->id, $leccion->autor_id);

        $this->actingAs($profesional)->post("/conocimiento/lecciones/{$leccion->id}/enviar")->assertRedirect();
        $this->assertSame(EstadoLeccion::EnRevision, $leccion->fresh()?->estado);

        // El profesional no publica: esa ruta es del administrador.
        $this->actingAs($profesional)->post("/conocimiento/lecciones/{$leccion->id}/publicar")->assertForbidden();

        $this->actingAs($this->admin)->post("/conocimiento/lecciones/{$leccion->id}/publicar")->assertRedirect();
        $leccion->refresh();
        $this->assertSame(EstadoLeccion::Publicada, $leccion->estado);
        $this->assertNotNull($leccion->publicada_en);
        $this->assertSame($this->admin->id, $leccion->revisado_por);
    }

    public function test_devolver_regresa_el_borrador_con_el_motivo(): void
    {
        $profesional = $this->profesional();
        $this->actingAs($profesional)->post('/conocimiento/lecciones', $this->datos());
        $leccion = LeccionAprendida::query()->firstOrFail();
        $this->actingAs($profesional)->post("/conocimiento/lecciones/{$leccion->id}/enviar");

        $this->actingAs($this->admin)->post("/conocimiento/lecciones/{$leccion->id}/devolver", ['motivo' => 'Falta el periodo'])
            ->assertRedirect();

        $leccion->refresh();
        $this->assertSame(EstadoLeccion::Borrador, $leccion->estado);
        $this->assertSame('Falta el periodo', $leccion->motivo_devolucion);
    }

    public function test_un_borrador_ajeno_no_se_ve_ni_se_edita(): void
    {
        $autor = $this->profesional();
        $otro = $this->profesional($this->recursos['anestesiologo']);
        $this->actingAs($autor)->post('/conocimiento/lecciones', $this->datos());
        $leccion = LeccionAprendida::query()->firstOrFail();

        $this->actingAs($otro)->get("/conocimiento/lecciones/{$leccion->id}")->assertForbidden();
        $this->actingAs($otro)->get("/conocimiento/lecciones/{$leccion->id}/edit")->assertForbidden();
        $this->actingAs($this->admin)->get("/conocimiento/lecciones/{$leccion->id}")->assertOk();
    }

    public function test_compartir_con_la_red_la_hace_visible_a_otro_hospital_sin_el_autor(): void
    {
        $this->actingAs($this->admin)->post('/conocimiento/lecciones', $this->datos());
        $leccion = LeccionAprendida::query()->firstOrFail();
        $this->actingAs($this->admin)->post("/conocimiento/lecciones/{$leccion->id}/publicar");
        $this->actingAs($this->admin)->post("/conocimiento/lecciones/{$leccion->id}/compartir", ['compartir' => true]);

        $otroHospital = Hospital::factory()->create();
        $adminOtro = User::factory()->create(['hospital_id' => $otroHospital->id]);
        HospitalContext::set($otroHospital->id);

        $this->actingAs($adminOtro)->get('/conocimiento/lecciones?vista=red')
            ->assertInertia(fn (Assert $page) => $page
                ->where('lecciones.data.0.id', $leccion->id)
                ->where('lecciones.data.0.autor', null)
                ->where('lecciones.data.0.hospital', $this->hospital->nombre));

        $this->actingAs($adminOtro)->get("/conocimiento/lecciones/red/{$leccion->id}")->assertOk();
        // Por la ruta normal sigue siendo de otro tenant.
        $this->actingAs($adminOtro)->get("/conocimiento/lecciones/{$leccion->id}")->assertNotFound();
    }

    public function test_solo_una_leccion_publicada_se_comparte(): void
    {
        $this->actingAs($this->admin)->post('/conocimiento/lecciones', $this->datos());
        $leccion = LeccionAprendida::query()->firstOrFail();

        $this->actingAs($this->admin)->post("/conocimiento/lecciones/{$leccion->id}/compartir", ['compartir' => true])
            ->assertSessionHasErrors('leccion');
        $this->assertFalse($leccion->fresh()?->compartida_red);
    }

    public function test_la_leccion_nace_prellenada_desde_una_alerta(): void
    {
        $this->cirugia(insumos: 4);
        $alerta = AlertaConocimiento::query()->firstOrFail();

        $this->actingAs($this->admin)->get("/conocimiento/lecciones/create?alerta_conocimiento={$alerta->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('conocimiento/lecciones/form')
                ->where('inicial.alerta_conocimiento_id', $alerta->id)
                ->where('inicial.cirugias.0', $alerta->cirugia_id));
    }

    public function test_valorar_una_leccion_publicada(): void
    {
        $this->actingAs($this->admin)->post('/conocimiento/lecciones', $this->datos());
        $leccion = LeccionAprendida::query()->firstOrFail();
        $this->actingAs($this->admin)->post("/conocimiento/lecciones/{$leccion->id}/publicar");

        $lector = $this->profesional();
        $this->actingAs($lector)->post("/conocimiento/lecciones/{$leccion->id}/valorar");
        $this->assertSame(1, $leccion->valoraciones()->count());

        $this->actingAs($lector)->post("/conocimiento/lecciones/{$leccion->id}/valorar");
        $this->assertSame(0, $leccion->valoraciones()->count());
    }
}
