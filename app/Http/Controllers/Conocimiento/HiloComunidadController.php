<?php

namespace App\Http\Controllers\Conocimiento;

use App\Http\Controllers\Controller;
use App\Models\AlertaConocimiento;
use App\Models\AlertaSobrecosto;
use App\Models\HiloComunidad;
use App\Models\LeccionAprendida;
use App\Models\ProcedimientoQuirurgico;
use App\Models\Recomendacion;
use App\Models\RespuestaHilo;
use App\Models\User;
use App\Services\Conocimiento\GestorComunidades;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Un hilo de discusión: respuestas, solución marcada y conversión en lección.
 */
class HiloComunidadController extends Controller
{
    public function __construct(protected GestorComunidades $gestor) {}

    public function show(Request $request, HiloComunidad $hilo): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $hilo->load(['comunidad', 'autor:id,name', 'respuestas.autor:id,name', 'leccion:id,titulo,estado']);
        $comunidad = $hilo->comunidad;

        return Inertia::render('conocimiento/comunidades/hilo', [
            'hilo' => [
                'id' => $hilo->id,
                'titulo' => $hilo->titulo,
                'cuerpo' => $hilo->cuerpo,
                'autor' => $hilo->autor?->name,
                'autor_id' => $hilo->autor_id,
                'creado_en' => $hilo->created_at?->toDateTimeString(),
                'fijado' => $hilo->fijado,
                'cerrado' => $hilo->cerrado,
                'comunidad' => ['id' => $comunidad->id, 'nombre' => $comunidad->nombre],
                'enlace' => $this->enlace($hilo),
                'leccion' => $hilo->leccion?->only(['id', 'titulo']),
                'respuestas' => $hilo->respuestas->sortBy('id')->values()->map(fn (RespuestaHilo $r): array => [
                    'id' => $r->id,
                    'cuerpo' => $r->cuerpo,
                    'autor' => $r->autor?->name,
                    'es_solucion' => $r->es_solucion,
                    'creado_en' => $r->created_at?->toDateTimeString(),
                ])->all(),
            ],
            'permisos' => [
                'responder' => ! $hilo->cerrado && $this->gestor->puedeParticipar($usuario, $comunidad),
                'moderar' => $this->gestor->puedeModerar($usuario, $comunidad),
                'marcar_solucion' => $hilo->autor_id === $usuario->id || $this->gestor->puedeModerar($usuario, $comunidad),
            ],
        ]);
    }

    public function responder(Request $request, HiloComunidad $hilo): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_if($hilo->cerrado, 403, 'El hilo está cerrado.');
        abort_unless($this->gestor->puedeParticipar($usuario, $hilo->comunidad), 403);

        $datos = $request->validate(['cuerpo' => ['required', 'string', 'min:2', 'max:10000']]);
        $hilo->respuestas()->create(['cuerpo' => $datos['cuerpo'], 'autor_id' => $usuario->id]);
        $hilo->update(['ultima_actividad_en' => now()]);

        return back();
    }

    /** La solución la marca quien preguntó o un moderador; solo hay una. */
    public function marcarSolucion(Request $request, HiloComunidad $hilo, RespuestaHilo $respuesta): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($respuesta->hilo_comunidad_id === $hilo->id, 404);
        abort_unless($hilo->autor_id === $usuario->id || $this->gestor->puedeModerar($usuario, $hilo->comunidad), 403);

        $marcar = ! $respuesta->es_solucion;
        $hilo->respuestas()->update(['es_solucion' => false]);
        $respuesta->update(['es_solucion' => $marcar]);

        return back();
    }

    public function moderar(Request $request, HiloComunidad $hilo): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($this->gestor->puedeModerar($usuario, $hilo->comunidad), 403);

        $datos = $request->validate(['fijado' => ['sometimes', 'boolean'], 'cerrado' => ['sometimes', 'boolean']]);
        $hilo->update($datos);

        return back();
    }

    /**
     * Resumen de lo enlazado, con su ruta, para pintar la tarjeta del hilo.
     *
     * @return array{tipo: string, titulo: string, url: string}|null
     */
    protected function enlace(HiloComunidad $hilo): ?array
    {
        $modelo = $hilo->enlazado();

        return match (true) {
            $modelo instanceof LeccionAprendida => ['tipo' => 'Lección', 'titulo' => $modelo->titulo, 'url' => route('conocimiento.lecciones.show', $modelo)],
            $modelo instanceof AlertaConocimiento => ['tipo' => 'Alerta', 'titulo' => $modelo->titulo, 'url' => route('conocimiento.alertas.index', ['estado' => 'todas'])],
            $modelo instanceof AlertaSobrecosto => ['tipo' => 'Sobrecosto', 'titulo' => 'Cirugía #'.$modelo->cirugia_id, 'url' => route('cirugias.show', $modelo->cirugia_id)],
            $modelo instanceof ProcedimientoQuirurgico => ['tipo' => 'Procedimiento', 'titulo' => $modelo->nombre, 'url' => route('conocimiento.mejores-practicas.show', $modelo)],
            $modelo instanceof Recomendacion => ['tipo' => 'Recomendación', 'titulo' => $modelo->titulo, 'url' => route('conocimiento.recomendaciones.index', ['estado' => 'todas'])],
            $modelo instanceof Model => null,
            default => null,
        };
    }
}
