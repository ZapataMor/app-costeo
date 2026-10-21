<?php

namespace App\Http\Controllers\Conocimiento;

use App\Enums\CategoriaLeccion;
use App\Enums\CausaSobrecosto;
use App\Enums\EstadoLeccion;
use App\Enums\TipoLeccion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conocimiento\GuardarLeccionRequest;
use App\Models\AlertaConocimiento;
use App\Models\AlertaSobrecosto;
use App\Models\HiloComunidad;
use App\Models\LeccionAprendida;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RegistroActividad;
use App\Models\User;
use App\Services\Conocimiento\GestorLecciones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Repositorio de lecciones aprendidas y mejores prácticas.
 *
 * Pestañas: publicadas (todo el hospital), mías (lo que escribí), por
 * revisar (solo quien gestiona) y red (lo que otros hospitales compartieron,
 * sin personas ni cirugías).
 */
class LeccionAprendidaController extends Controller
{
    public function __construct(protected GestorLecciones $gestor) {}

    public function index(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $vista = (string) $request->query('vista', 'publicadas');
        $vistas = ['publicadas', 'mias', 'red', ...($usuario->gestionaConocimiento() ? ['revision'] : [])];
        $vista = in_array($vista, $vistas, true) ? $vista : 'publicadas';

        $consulta = $vista === 'red'
            ? LeccionAprendida::query()->deLaRed()->with('hospital:id,nombre')
            : LeccionAprendida::query()->with('autor:id,name');

        match ($vista) {
            'publicadas' => $consulta->where('estado', EstadoLeccion::Publicada->value),
            'mias' => $consulta->where('autor_id', $usuario->id),
            'revision' => $consulta->where('estado', EstadoLeccion::EnRevision->value),
            default => null,
        };

        $q = trim((string) $request->query('q', ''));
        $categoria = (string) $request->query('categoria', '');
        $tipo = (string) $request->query('tipo', '');
        $procedimiento = (int) $request->query('procedimiento', 0);

        $consulta
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $s) => $s
                ->where('titulo', 'like', "%{$q}%")
                ->orWhere('leccion', 'like', "%{$q}%")
                ->orWhere('contexto', 'like', "%{$q}%")))
            ->when(in_array($categoria, CategoriaLeccion::values(), true), fn (Builder $b) => $b->where('categoria', $categoria))
            ->when(in_array($tipo, TipoLeccion::values(), true), fn (Builder $b) => $b->where('tipo', $tipo))
            ->when($procedimiento > 0 && $vista !== 'red', fn (Builder $b) => $b->where('procedimiento_quirurgico_id', $procedimiento))
            ->with('procedimiento:id,codigo_cups,nombre')
            ->withCount('valoraciones');

        $lecciones = $consulta
            ->orderByDesc($vista === 'publicadas' || $vista === 'red' ? 'publicada_en' : 'updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (LeccionAprendida $l): array => $this->resumen($l, $vista === 'red'));

        return Inertia::render('conocimiento/lecciones/index', [
            'lecciones' => $lecciones,
            'vista' => $vista,
            'vistas' => $vistas,
            'filtros' => ['q' => $q, 'categoria' => $categoria, 'tipo' => $tipo, 'procedimiento' => $procedimiento ?: null],
            'conteos' => [
                'revision' => $usuario->gestionaConocimiento()
                    ? LeccionAprendida::query()->where('estado', EstadoLeccion::EnRevision->value)->count()
                    : 0,
                'mias_borrador' => LeccionAprendida::query()->where('autor_id', $usuario->id)->where('estado', EstadoLeccion::Borrador->value)->count(),
            ],
            ...$this->catalogos(),
        ]);
    }

    /**
     * Formulario de alta. Puede nacer prellenado desde una alerta de
     * sobrecosto, una alerta de conocimiento o un hilo de comunidad: es el
     * puente entre «detectamos algo» y «aprendimos algo».
     */
    public function create(Request $request): Response
    {
        $inicial = ['tipo' => TipoLeccion::Leccion->value, 'categoria' => CategoriaLeccion::Costos->value, 'cirugias' => []];

        if ($id = (int) $request->query('alerta_sobrecosto')) {
            $alerta = AlertaSobrecosto::query()->with('procedimiento')->find($id);
            if ($alerta !== null) {
                $inicial += [
                    'alerta_sobrecosto_id' => $alerta->id,
                    'procedimiento_quirurgico_id' => $alerta->procedimiento_quirurgico_id,
                    'causa' => $alerta->causa?->value,
                    'impacto_estimado_cop' => (float) $alerta->exceso,
                    'titulo' => 'Sobrecosto en '.($alerta->procedimiento->nombre ?? 'cirugía'),
                    'contexto' => sprintf(
                        'La cirugía #%d costó $%s, %s%% por encima de lo esperado ($%s). %s',
                        $alerta->cirugia_id,
                        number_format((float) $alerta->costo_total, 0, ',', '.'),
                        number_format((float) $alerta->exceso_pct * 100, 1, ',', '.'),
                        number_format((float) $alerta->costo_esperado, 0, ',', '.'),
                        $alerta->causa_detalle ?? '',
                    ),
                ];
                $inicial['cirugias'] = [$alerta->cirugia_id];
            }
        }

        if ($id = (int) $request->query('alerta_conocimiento')) {
            $alerta = AlertaConocimiento::query()->find($id);
            if ($alerta !== null) {
                $inicial = [
                    'alerta_conocimiento_id' => $alerta->id,
                    'procedimiento_quirurgico_id' => $alerta->procedimiento_quirurgico_id,
                    'causa' => $alerta->causa?->value,
                    'impacto_estimado_cop' => $alerta->impacto_cop !== null ? (float) $alerta->impacto_cop : null,
                    'titulo' => $alerta->titulo,
                    'contexto' => $alerta->descripcion.($alerta->comentario ? "\n\n".$alerta->comentario : ''),
                    'cirugias' => $alerta->cirugia_id !== null ? [$alerta->cirugia_id] : [],
                ] + $inicial;
            }
        }

        if ($id = (int) $request->query('hilo')) {
            $hilo = HiloComunidad::query()->with(['respuestas' => fn ($q) => $q->where('es_solucion', true)])->find($id);
            if ($hilo !== null) {
                $solucion = $hilo->respuestas->first();
                $inicial = [
                    'titulo' => $hilo->titulo,
                    'contexto' => $hilo->cuerpo,
                    'leccion' => $solucion?->cuerpo,
                    'hilo_id' => $hilo->id,
                    'procedimiento_quirurgico_id' => $hilo->enlazable_tipo === 'procedimiento' ? $hilo->enlazable_id : null,
                ] + $inicial;
            }
        }

        return Inertia::render('conocimiento/lecciones/form', [
            'leccion' => null,
            'inicial' => $inicial,
            ...$this->catalogos(),
        ]);
    }

    public function store(GuardarLeccionRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $leccion = new LeccionAprendida(collect($datos)->except('cirugias')->all());
        $leccion->forceFill(['autor_id' => $request->user()?->id, 'estado' => EstadoLeccion::Borrador])->save();
        $leccion->cirugias()->sync($datos['cirugias'] ?? []);

        // Un hilo convertido en lección queda enlazado a ella.
        if ($hiloId = (int) $request->input('hilo_id')) {
            HiloComunidad::query()->whereKey($hiloId)->update(['leccion_aprendida_id' => $leccion->id]);
        }

        RegistroActividad::registrar('creó', "Creó la lección «{$leccion->titulo}»", $leccion);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lección guardada como borrador. Envíela a revisión cuando esté lista.']);

        return redirect()->route('conocimiento.lecciones.show', $leccion);
    }

    public function show(Request $request, LeccionAprendida $leccion): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($this->gestor->puedeVer($leccion, $usuario), 403);

        $leccion->load(['autor:id,name', 'revisor:id,name', 'procedimiento:id,codigo_cups,nombre', 'cirugias:id,fecha', 'alertaSobrecosto:id,cirugia_id,exceso', 'alertaConocimiento:id,titulo'])
            ->loadCount('valoraciones');

        return Inertia::render('conocimiento/lecciones/show', [
            'leccion' => [
                ...$this->resumen($leccion, false),
                'contexto' => $leccion->contexto,
                'leccion' => $leccion->leccion,
                'recomendacion' => $leccion->recomendacion,
                'revisor' => $leccion->revisor?->name,
                'revisado_en' => $leccion->revisado_en?->toDateTimeString(),
                'motivo_devolucion' => $leccion->motivo_devolucion,
                'cirugias' => $leccion->cirugias->map(fn ($c): array => ['id' => $c->id, 'fecha' => $c->fecha->toDateString()])->all(),
                'alerta_sobrecosto' => $leccion->alertaSobrecosto?->only(['id', 'cirugia_id']),
                'alerta_conocimiento' => $leccion->alertaConocimiento?->only(['id', 'titulo']),
                'valorada' => $leccion->valoraciones()->whereKey($usuario->id)->exists(),
                'hilos' => HiloComunidad::query()
                    ->where(fn ($q) => $q->where('leccion_aprendida_id', $leccion->id)
                        ->orWhere(fn ($s) => $s->where('enlazable_tipo', 'leccion')->where('enlazable_id', $leccion->id)))
                    ->get(['id', 'titulo', 'comunidad_practica_id'])
                    ->map(fn (HiloComunidad $h): array => $h->only(['id', 'titulo', 'comunidad_practica_id']))
                    ->all(),
            ],
            'permisos' => [
                'editar' => $this->gestor->puedeEditar($leccion, $usuario),
                'enviar' => $leccion->estado === EstadoLeccion::Borrador && ($leccion->autor_id === $usuario->id || $usuario->gestionaConocimiento()),
                'gestionar' => $usuario->gestionaConocimiento(),
            ],
        ]);
    }

    /**
     * Lección de otro hospital, publicada en la red: sin autor, sin
     * cirugías, sin alertas. Solo el conocimiento y de dónde viene.
     */
    public function showRed(int $leccion): Response
    {
        // Sin route model binding: el scope del tenant escondería justo las
        // lecciones de otros hospitales, que son las que se quieren ver.
        $leccion = LeccionAprendida::query()->deLaRed()->with('hospital:id,nombre')->withCount('valoraciones')->findOrFail($leccion);

        return Inertia::render('conocimiento/lecciones/show', [
            'leccion' => [
                ...$this->resumen($leccion, true),
                'contexto' => $leccion->contexto,
                'leccion' => $leccion->leccion,
                'recomendacion' => $leccion->recomendacion,
                'revisor' => null,
                'revisado_en' => null,
                'motivo_devolucion' => null,
                'cirugias' => [],
                'alerta_sobrecosto' => null,
                'alerta_conocimiento' => null,
                'valorada' => false,
                'hilos' => [],
            ],
            'permisos' => ['editar' => false, 'enviar' => false, 'gestionar' => false],
        ]);
    }

    public function edit(Request $request, LeccionAprendida $leccion): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($this->gestor->puedeEditar($leccion, $usuario), 403, 'Solo se edita un borrador propio.');

        return Inertia::render('conocimiento/lecciones/form', [
            'leccion' => ['id' => $leccion->id],
            'inicial' => [
                ...$leccion->only([
                    'titulo', 'contexto', 'leccion', 'recomendacion', 'especialidad',
                    'procedimiento_quirurgico_id', 'alerta_sobrecosto_id', 'alerta_conocimiento_id', 'etiquetas',
                ]),
                'tipo' => $leccion->tipo->value,
                'categoria' => $leccion->categoria->value,
                'causa' => $leccion->causa?->value,
                'impacto_estimado_cop' => $leccion->impacto_estimado_cop !== null ? (float) $leccion->impacto_estimado_cop : null,
                'cirugias' => $leccion->cirugias()->pluck('cirugias.id')->all(),
            ],
            ...$this->catalogos(),
        ]);
    }

    public function update(GuardarLeccionRequest $request, LeccionAprendida $leccion): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($this->gestor->puedeEditar($leccion, $usuario), 403, 'Solo se edita un borrador propio.');

        $datos = $request->validated();
        $leccion->update(collect($datos)->except('cirugias')->all());
        $leccion->cirugias()->sync($datos['cirugias'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador actualizado.']);

        return redirect()->route('conocimiento.lecciones.show', $leccion);
    }

    public function enviar(Request $request, LeccionAprendida $leccion): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $this->gestor->enviarARevision($leccion, $usuario);

        return $this->listo('Lección enviada a revisión.');
    }

    public function publicar(Request $request, LeccionAprendida $leccion): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $this->gestor->publicar($leccion, $usuario);
        RegistroActividad::registrar('publicó', "Publicó la lección «{$leccion->titulo}»", $leccion);

        return $this->listo('Lección publicada en el repositorio del hospital.');
    }

    public function devolver(Request $request, LeccionAprendida $leccion): RedirectResponse
    {
        $motivo = (string) $request->validate(['motivo' => ['required', 'string', 'max:2000']])['motivo'];
        /** @var User $usuario */
        $usuario = $request->user();
        $this->gestor->devolver($leccion, $usuario, $motivo);

        return $this->listo('Lección devuelta al autor con observaciones.');
    }

    public function archivar(Request $request, LeccionAprendida $leccion): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $this->gestor->archivar($leccion, $usuario);

        return $this->listo('Lección archivada.');
    }

    public function compartir(Request $request, LeccionAprendida $leccion): RedirectResponse
    {
        $compartir = (bool) $request->validate(['compartir' => ['required', 'boolean']])['compartir'];
        /** @var User $usuario */
        $usuario = $request->user();
        $this->gestor->compartirEnRed($leccion, $usuario, $compartir);
        RegistroActividad::registrar('actualizó', ($compartir ? 'Compartió con la red' : 'Retiró de la red')." la lección «{$leccion->titulo}»", $leccion);

        return $this->listo($compartir ? 'Lección compartida con la red de hospitales.' : 'Lección retirada de la red.');
    }

    /** Marca (o desmarca) la lección como útil para quien la lee. */
    public function valorar(Request $request, LeccionAprendida $leccion): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($leccion->estado === EstadoLeccion::Publicada, 403);

        $leccion->valoraciones()->toggle([$usuario->id]);

        return back();
    }

    protected function listo(string $mensaje): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);

        return back();
    }

    /** @return array<string, mixed> */
    protected function resumen(LeccionAprendida $leccion, bool $deLaRed): array
    {
        return [
            'id' => $leccion->id,
            'tipo' => $leccion->tipo->value,
            'tipo_etiqueta' => $leccion->tipo->etiqueta(),
            'titulo' => $leccion->titulo,
            'extracto' => mb_strimwidth($leccion->leccion, 0, 220, '…'),
            'categoria' => $leccion->categoria->value,
            'categoria_etiqueta' => $leccion->categoria->etiqueta(),
            'causa_etiqueta' => $leccion->causa?->etiqueta(),
            'procedimiento' => $leccion->procedimiento?->only(['id', 'codigo_cups', 'nombre']),
            'especialidad' => $leccion->especialidad,
            'impacto_estimado_cop' => $leccion->impacto_estimado_cop !== null ? (float) $leccion->impacto_estimado_cop : null,
            'estado' => $leccion->estado->value,
            'estado_etiqueta' => $leccion->estado->etiqueta(),
            // En la red no viaja la persona: solo la institución.
            'autor' => $deLaRed ? null : $leccion->autor?->name,
            'hospital' => $deLaRed ? $leccion->hospital->nombre : null,
            'compartida_red' => $leccion->compartida_red,
            'etiquetas' => $leccion->etiquetas ?? [],
            'publicada_en' => $leccion->publicada_en?->toDateString(),
            'actualizada_en' => $leccion->updated_at?->toDateTimeString(),
            'utiles' => (int) $leccion->getAttribute('valoraciones_count'),
            'de_la_red' => $deLaRed,
        ];
    }

    /** @return array<string, mixed> */
    protected function catalogos(): array
    {
        return [
            'categorias' => CategoriaLeccion::catalogo(),
            'causas' => CausaSobrecosto::catalogo(),
            'tipos' => array_map(fn (TipoLeccion $t): array => ['valor' => $t->value, 'etiqueta' => $t->etiqueta()], TipoLeccion::cases()),
            'procedimientos' => ProcedimientoQuirurgico::query()->orderBy('nombre')->get(['id', 'codigo_cups', 'nombre', 'especialidad'])
                ->map(fn (ProcedimientoQuirurgico $p): array => $p->only(['id', 'codigo_cups', 'nombre', 'especialidad']))
                ->all(),
        ];
    }
}
