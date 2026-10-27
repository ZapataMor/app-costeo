<?php

namespace App\Http\Controllers\Conocimiento;

use App\Enums\CausaSobrecosto;
use App\Enums\EstadoAlerta;
use App\Enums\SeveridadAlerta;
use App\Enums\TipoReglaConocimiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conocimiento\RevisarAlertaConocimientoRequest;
use App\Models\AlertaConocimiento;
use App\Models\ProcedimientoQuirurgico;
use App\Models\RegistroActividad;
use App\Services\Alertas\BandejaAlertas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        // Filtro de caso desde la bandeja única: un procedimiento (o «sin»,
        // las alertas de sala y del mes del hospital) y, si se eligió, un mes.
        $procedimiento = (string) $request->query('procedimiento', '');
        $procedimientoId = ctype_digit($procedimiento) ? (int) $procedimiento : null;
        $sinProcedimiento = $procedimiento === BandejaAlertas::SIN_PROCEDIMIENTO;
        $mes = self::mes($request->query('mes'));

        $alertas = AlertaConocimiento::query()
            ->with(['procedimiento:id,codigo_cups,nombre', 'sala:id,nombre', 'revisor:id,name'])
            ->withCount('lecciones')
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->when(in_array($tipo, TipoReglaConocimiento::values(), true), fn ($q) => $q->where('tipo', $tipo))
            ->when(in_array($severidad, SeveridadAlerta::values(), true), fn ($q) => $q->where('severidad', $severidad))
            ->when($procedimientoId !== null, fn ($q) => $q->where('procedimiento_quirurgico_id', $procedimientoId))
            ->when($sinProcedimiento, fn ($q) => $q->whereNull('procedimiento_quirurgico_id'))
            // Mismo criterio de mes que la bandeja: el periodo evaluado, si
            // no el de la cirugía, si no el de la detección.
            ->when($mes !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('periodo', $mes->format('Y-m'))
                ->orWhere(fn ($x) => $x->whereNull('periodo')->whereHas('cirugia', fn ($c) => $c
                    ->whereDate('fecha', '>=', $mes->toDateString())
                    ->whereDate('fecha', '<=', $mes->copy()->endOfMonth()->toDateString())))
                ->orWhere(fn ($x) => $x->whereNull('periodo')->whereNull('cirugia_id')
                    ->whereBetween('created_at', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()]))))
            ->orderByRaw("case severidad when 'critica' then 3 when 'advertencia' then 2 else 1 end desc")
            ->orderByDesc('impacto_cop')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AlertaConocimiento $a): array => $this->presentar($a));

        return Inertia::render('conocimiento/alertas', [
            'alertas' => $alertas,
            'filtros' => [
                'estado' => $estado,
                'tipo' => $tipo,
                'severidad' => $severidad,
                'procedimiento' => $procedimientoId !== null ? (string) $procedimientoId : ($sinProcedimiento ? BandejaAlertas::SIN_PROCEDIMIENTO : ''),
                'mes' => $mes?->format('Y-m') ?? '',
            ],
            'caso' => [
                'procedimiento' => $procedimientoId !== null
                    ? ProcedimientoQuirurgico::query()->find($procedimientoId)?->only(['id', 'codigo_cups', 'nombre'])
                    : null,
                'sin_procedimiento' => $sinProcedimiento,
                'mes' => $mes?->format('Y-m'),
            ],
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

    /** «2026-05» → primer día del mes; cualquier otra cosa, sin filtro. */
    protected static function mes(mixed $valor): ?Carbon
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}$/', $valor) === 1
            ? Carbon::createFromFormat('!Y-m', $valor)
            : null;
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
