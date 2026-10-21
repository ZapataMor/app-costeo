<?php

namespace App\Http\Controllers\Conocimiento;

use App\Enums\EstadoRecomendacion;
use App\Enums\RolUsuario;
use App\Enums\TipoRecomendacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conocimiento\GestionarRecomendacionRequest;
use App\Models\Hospital;
use App\Models\Recomendacion;
use App\Models\RegistroActividad;
use App\Models\User;
use App\Services\Conocimiento\GeneradorRecomendaciones;
use App\Support\HospitalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recomendaciones derivadas del histórico, ordenadas por ahorro estimado,
 * con su seguimiento: aceptada (responsable y fecha) → implementada.
 */
class RecomendacionController extends Controller
{
    public function index(Request $request): Response
    {
        $estado = (string) $request->query('estado', EstadoRecomendacion::Nueva->value);
        $estado = in_array($estado, [...EstadoRecomendacion::values(), 'todas'], true) ? $estado : EstadoRecomendacion::Nueva->value;
        $tipo = (string) $request->query('tipo', '');

        $recomendaciones = Recomendacion::query()
            ->with(['procedimiento:id,codigo_cups,nombre', 'insumo:id,nombre', 'sala:id,nombre', 'responsable:id,name'])
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->when(in_array($tipo, TipoRecomendacion::values(), true), fn ($q) => $q->where('tipo', $tipo))
            ->orderByDesc('ahorro_mensual_estimado')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Recomendacion $r): array => [
                'id' => $r->id,
                'tipo' => $r->tipo->value,
                'tipo_etiqueta' => $r->tipo->etiqueta(),
                'titulo' => $r->titulo,
                'descripcion' => $r->descripcion,
                'procedimiento' => $r->procedimiento?->only(['id', 'codigo_cups', 'nombre']),
                'insumo' => $r->insumo?->nombre,
                'sala' => $r->sala?->nombre,
                'ahorro_mensual_estimado' => (float) $r->ahorro_mensual_estimado,
                'evidencia' => $r->evidencia,
                'estado' => $r->estado->value,
                'estado_etiqueta' => $r->estado->etiqueta(),
                'nota' => $r->nota,
                'responsable' => $r->responsable?->name,
                'fecha_compromiso' => $r->fecha_compromiso?->toDateString(),
                'gestionada_en' => $r->gestionada_en?->toDateTimeString(),
                'implementada_en' => $r->implementada_en?->toDateString(),
                'generada_en' => $r->generada_en->toDateTimeString(),
            ]);

        $totales = Recomendacion::query()
            ->groupBy('estado')
            ->selectRaw('estado, count(*) as n, sum(ahorro_mensual_estimado) as ahorro')
            ->get()
            ->keyBy(fn (Recomendacion $r): string => $r->estado->value);

        return Inertia::render('conocimiento/recomendaciones', [
            'recomendaciones' => $recomendaciones,
            'filtros' => ['estado' => $estado, 'tipo' => $tipo],
            'tipos' => TipoRecomendacion::catalogo(),
            'estados' => array_map(fn (EstadoRecomendacion $e): array => [
                'valor' => $e->value,
                'etiqueta' => $e->etiqueta(),
                'n' => (int) ($totales->get($e->value)?->getAttribute('n') ?? 0),
                'ahorro' => round((float) ($totales->get($e->value)?->getAttribute('ahorro') ?? 0), 2),
            ], EstadoRecomendacion::cases()),
            'responsables' => User::query()
                ->where('hospital_id', HospitalContext::id())
                ->whereIn('role', [RolUsuario::AdminHospital->value, RolUsuario::Profesional->value])
                ->where('activo', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
                ->all(),
        ]);
    }

    public function generar(GeneradorRecomendaciones $generador): RedirectResponse
    {
        $resultado = $generador->generar(Hospital::query()->findOrFail(HospitalContext::id()));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf(
                '%d recomendaciones vigentes; ahorro potencial de $%s al mes en las nuevas.',
                $resultado['generadas'],
                number_format($resultado['ahorro_mensual'], 0, ',', '.'),
            ),
        ]);

        return back();
    }

    public function gestionar(GestionarRecomendacionRequest $request, Recomendacion $recomendacion): RedirectResponse
    {
        $datos = $request->validated();
        $estado = EstadoRecomendacion::from($datos['estado']);

        $recomendacion->forceFill([
            'estado' => $estado,
            'nota' => $datos['nota'] ?? $recomendacion->nota,
            'responsable_id' => $datos['responsable_id'] ?? $recomendacion->responsable_id,
            'fecha_compromiso' => $datos['fecha_compromiso'] ?? $recomendacion->fecha_compromiso,
            'gestionada_por' => $request->user()?->id,
            'gestionada_en' => now(),
            'implementada_en' => $estado === EstadoRecomendacion::Implementada ? now() : null,
        ])->save();

        RegistroActividad::registrar(
            'actualizó',
            sprintf('Marcó como %s la recomendación «%s»', mb_strtolower($estado->etiqueta()), $recomendacion->titulo),
            $recomendacion,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recomendación '.mb_strtolower($estado->etiqueta()).'.']);

        return back();
    }
}
