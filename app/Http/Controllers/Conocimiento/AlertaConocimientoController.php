<?php

namespace App\Http\Controllers\Conocimiento;

use App\Enums\CausaSobrecosto;
use App\Enums\EstadoAlerta;
use App\Enums\SeveridadAlerta;
use App\Enums\TipoReglaConocimiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conocimiento\RevisarAlertaConocimientoRequest;
use App\Models\AlertaConocimiento;
use App\Models\RegistroActividad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bandeja de alertas de las reglas de conocimiento.
 *
 * Orden: severidad y luego impacto en pesos, para que el tiempo de revisión
 * se gaste primero donde más vale. Desde cada alerta se puede escribir la
 * lección que deja.
 */
class AlertaConocimientoController extends Controller
{
    public function index(Request $request): Response
    {
        $estado = (string) $request->query('estado', EstadoAlerta::Pendiente->value);
        $tipo = (string) $request->query('tipo', '');
        $severidad = (string) $request->query('severidad', '');

        $estado = in_array($estado, [...EstadoAlerta::values(), 'todas'], true) ? $estado : EstadoAlerta::Pendiente->value;

        $alertas = AlertaConocimiento::query()
            ->with(['procedimiento:id,codigo_cups,nombre', 'sala:id,nombre', 'revisor:id,name'])
            ->withCount('lecciones')
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->when(in_array($tipo, TipoReglaConocimiento::values(), true), fn ($q) => $q->where('tipo', $tipo))
            ->when(in_array($severidad, SeveridadAlerta::values(), true), fn ($q) => $q->where('severidad', $severidad))
            ->orderByRaw("case severidad when 'critica' then 3 when 'advertencia' then 2 else 1 end desc")
            ->orderByDesc('impacto_cop')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AlertaConocimiento $a): array => $this->presentar($a));

        return Inertia::render('conocimiento/alertas', [
            'alertas' => $alertas,
            'filtros' => ['estado' => $estado, 'tipo' => $tipo, 'severidad' => $severidad],
            'tipos' => array_map(fn (TipoReglaConocimiento $t): array => ['valor' => $t->value, 'etiqueta' => $t->nombre()], TipoReglaConocimiento::cases()),
            'estados' => array_map(fn (EstadoAlerta $e): array => ['valor' => $e->value, 'etiqueta' => $e->etiqueta()], EstadoAlerta::cases()),
            'causas' => CausaSobrecosto::catalogo(),
            'resumen' => [
                'pendientes' => AlertaConocimiento::query()->where('estado', EstadoAlerta::Pendiente->value)->count(),
                'impacto_pendiente' => round((float) AlertaConocimiento::query()->where('estado', EstadoAlerta::Pendiente->value)->sum('impacto_cop'), 2),
                'criticas' => AlertaConocimiento::query()->where('estado', EstadoAlerta::Pendiente->value)->where('severidad', SeveridadAlerta::Critica->value)->count(),
            ],
        ]);
    }

    public function revisar(RevisarAlertaConocimientoRequest $request, AlertaConocimiento $alerta): RedirectResponse
    {
        $datos = $request->validated();

        $alerta->update([
            'estado' => $datos['estado'],
            'causa' => $datos['estado'] === EstadoAlerta::Revisada->value ? ($datos['causa'] ?? null) : null,
            'comentario' => $datos['comentario'] ?? null,
            'revisado_por' => $request->user()?->id,
            'revisado_en' => now(),
        ]);

        RegistroActividad::registrar(
            $datos['estado'] === EstadoAlerta::Revisada->value ? 'revisó' : 'descartó',
            sprintf('%s la alerta de conocimiento #%d: %s', $datos['estado'] === EstadoAlerta::Revisada->value ? 'Revisó' : 'Descartó', $alerta->id, $alerta->titulo),
            $alerta,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alerta cerrada.']);

        return back();
    }

    /** @return array<string, mixed> */
    protected function presentar(AlertaConocimiento $alerta): array
    {
        return [
            'id' => $alerta->id,
            'tipo' => $alerta->tipo->value,
            'tipo_nombre' => $alerta->tipo->nombre(),
            'severidad' => $alerta->severidad->value,
            'titulo' => $alerta->titulo,
            'descripcion' => $alerta->descripcion,
            'cirugia_id' => $alerta->cirugia_id,
            'procedimiento' => $alerta->procedimiento?->only(['id', 'codigo_cups', 'nombre']),
            'sala' => $alerta->sala?->nombre,
            'periodo' => $alerta->periodo,
            'valor' => $alerta->valor !== null ? (float) $alerta->valor : null,
            'umbral' => $alerta->umbral !== null ? (float) $alerta->umbral : null,
            'impacto_cop' => $alerta->impacto_cop !== null ? (float) $alerta->impacto_cop : null,
            'datos' => $alerta->datos,
            'estado' => $alerta->estado->value,
            'causa' => $alerta->causa?->value,
            'causa_etiqueta' => $alerta->causa?->etiqueta(),
            'comentario' => $alerta->comentario,
            'revisor' => $alerta->revisor?->name,
            'revisado_en' => $alerta->revisado_en?->toDateTimeString(),
            'detectada_en' => $alerta->created_at?->toDateTimeString(),
            'lecciones' => (int) $alerta->getAttribute('lecciones_count'),
        ];
    }
}
