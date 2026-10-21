<?php

namespace App\Http\Controllers\Conocimiento;

use App\Enums\EstadoAlerta;
use App\Enums\SeveridadAlerta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conocimiento\ActualizarReglaRequest;
use App\Models\AlertaConocimiento;
use App\Models\Hospital;
use App\Models\RegistroActividad;
use App\Models\ReglaConocimiento;
use App\Services\Conocimiento\CatalogoReglas;
use App\Services\Conocimiento\MotorReglas;
use App\Support\HospitalContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reglas de conocimiento del hospital: qué vigilar y con qué umbrales.
 */
class ReglaConocimientoController extends Controller
{
    public function index(CatalogoReglas $catalogo): Response
    {
        $hospital = $this->hospital();
        $reglas = $catalogo->delHospital($hospital);

        $conteos = AlertaConocimiento::query()
            ->groupBy('tipo', 'estado')
            ->selectRaw('tipo, estado, count(*) as n')
            ->get()
            ->groupBy(fn (AlertaConocimiento $a): string => $a->tipo->value);

        return Inertia::render('conocimiento/reglas', [
            'reglas' => array_values(array_map(fn (ReglaConocimiento $r): array => [
                'id' => $r->id,
                'tipo' => $r->tipo->value,
                'nombre' => $r->nombre,
                'descripcion' => $r->descripcion,
                'alcance' => $r->tipo->alcance(),
                'severidad' => $r->severidad->value,
                'activa' => $r->activa,
                'evaluada_en' => $r->evaluada_en?->toDateTimeString(),
                'parametros' => array_map(fn (string $clave, array $definicion): array => [
                    'clave' => $clave,
                    'etiqueta' => $definicion['etiqueta'],
                    'unidad' => $definicion['unidad'],
                    'valor' => $r->parametro($clave),
                    'por_defecto' => $definicion['valor'],
                ], array_keys($r->tipo->parametrosPorDefecto()), $r->tipo->parametrosPorDefecto()),
                'pendientes' => (int) ($conteos->get($r->tipo->value)?->firstWhere('estado', EstadoAlerta::Pendiente)?->getAttribute('n') ?? 0),
                'total' => (int) ($conteos->get($r->tipo->value)?->sum(fn (AlertaConocimiento $a): int => (int) $a->getAttribute('n')) ?? 0),
            ], $reglas)),
            'severidades' => SeveridadAlerta::values(),
        ]);
    }

    public function update(ActualizarReglaRequest $request, ReglaConocimiento $regla): RedirectResponse
    {
        $datos = $request->validated();
        $regla->update([
            'activa' => $datos['activa'],
            'severidad' => $datos['severidad'],
            'parametros' => array_map(fn ($v): float => (float) $v, $datos['parametros']),
        ]);

        RegistroActividad::registrar('actualizó', "Actualizó la regla de conocimiento «{$regla->nombre}»", $regla);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Regla actualizada. Reevalúe para aplicar los nuevos umbrales al histórico.']);

        return back();
    }

    /**
     * Pasa todas las reglas por el histórico completo. Las alertas ya
     * revisadas no se tocan.
     */
    public function evaluar(MotorReglas $motor): RedirectResponse
    {
        $resultado = $motor->evaluarHistorico($this->hospital());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Reglas evaluadas sobre %d cirugías y %d meses.', $resultado['cirugias'], $resultado['meses']),
        ]);

        return back();
    }

    protected function hospital(): Hospital
    {
        return Hospital::query()->findOrFail(HospitalContext::id());
    }
}
