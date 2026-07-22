<?php

namespace App\Http\Controllers\Costeo;

use App\Enums\CausaSobrecosto;
use App\Enums\EstadoAlerta;
use App\Http\Controllers\Controller;
use App\Http\Requests\RevisarAlertaSobrecostoRequest;
use App\Models\AlertaSobrecosto;
use App\Models\RegistroActividad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bandeja de sobrecostos (Capa 3 → Capa 4).
 *
 * El dashboard de Outliers muestra *que* hay casos atípicos; esta bandeja es
 * donde se cierran. La diferencia entre las dos es el estado: un outlier no
 * se puede revisar ni dar por resuelto, una alerta sí, y esa transición es la
 * que produce el dato nuevo —la causa—.
 *
 * El resumen separa el sobrecosto **evitable** del total, que es la única
 * cifra que un gerente puede llevar a un comité: el exceso por complicación
 * clínica no es gestionable y sumarlo al mismo total vuelve la cifra inútil
 * para decidir. Esa separación solo existe una vez que hay causas atribuidas;
 * es, en concreto, lo que la revisión le agrega al hospital.
 */
class AlertaSobrecostoController extends Controller
{
    public function index(Request $request): Response
    {
        $estado = (string) $request->query('estado', EstadoAlerta::Pendiente->value);

        if (! in_array($estado, [...EstadoAlerta::values(), 'todas'], true)) {
            $estado = EstadoAlerta::Pendiente->value;
        }

        $alertas = AlertaSobrecosto::query()
            ->with(['procedimiento:id,codigo_cups,nombre', 'cirugia:id,fecha', 'revisor:id,name'])
            ->when($estado !== 'todas', fn ($query) => $query->where('estado', $estado))
            // Lo más caro primero: con la bandeja llena, el orden por exceso
            // es lo que hace que el tiempo de revisión se gaste donde hay
            // plata, y no en el caso que llegó de último.
            ->orderByDesc('exceso')
            ->paginate(15)
            ->withQueryString();

        $alertas->through(fn (AlertaSobrecosto $alerta): array => $this->presentar($alerta));

        return Inertia::render('costeo/alertas', [
            'alertas' => $alertas,
            'filtros' => ['estado' => $estado],
            'estados' => array_map(fn (EstadoAlerta $e): array => [
                'valor' => $e->value,
                'etiqueta' => $e->etiqueta(),
            ], EstadoAlerta::cases()),
            'causas' => CausaSobrecosto::catalogo(),
            'resumen' => $this->resumen(),
        ]);
    }

    /**
     * Atribuye la causa de un sobrecosto: el acto que convierte un dato
     * estadístico en conocimiento del hospital.
     */
    public function revisar(RevisarAlertaSobrecostoRequest $request, AlertaSobrecosto $alerta): RedirectResponse
    {
        $datos = $request->validated();
        $descartada = $datos['estado'] === EstadoAlerta::Descartada->value;

        $alerta->update([
            'estado' => $datos['estado'],
            'causa' => $descartada ? null : $datos['causa'],
            'causa_detalle' => $datos['causa_detalle'] ?? null,
            'revisado_por' => $request->user()->id,
            'revisado_en' => now(),
        ]);

        // Se audita a mano y no con el trait `Auditable`: la alerta la crea el
        // motor de costeo, y auditar sus creaciones automáticas llenaría el
        // historial de ruido. Lo que sí importa es quién explicó cada caso.
        RegistroActividad::registrar(
            $descartada ? 'descartó' : 'revisó',
            $descartada
                ? sprintf('Descartó la alerta de sobrecosto #%d', $alerta->id)
                : sprintf(
                    'Revisó la alerta de sobrecosto #%d: %s',
                    $alerta->id,
                    $alerta->causa?->etiqueta() ?? '—',
                ),
            $alerta,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $descartada ? 'Alerta descartada.' : 'Causa registrada.',
        ]);

        return back();
    }

    /**
     * Cifras de cabecera del módulo.
     *
     * @return array<string, mixed>
     */
    protected function resumen(): array
    {
        $alertas = AlertaSobrecosto::query()->get(['estado', 'causa', 'exceso']);

        $revisadas = $alertas->where('estado', EstadoAlerta::Revisada);
        $evitable = $revisadas
            ->filter(fn (AlertaSobrecosto $a): bool => $a->causa?->evitable() ?? false)
            ->sum(fn (AlertaSobrecosto $a): float => (float) $a->exceso);

        return [
            'pendientes' => $alertas->where('estado', EstadoAlerta::Pendiente)->count(),
            'revisadas' => $revisadas->count(),
            'exceso_total' => round($alertas->sum(fn (AlertaSobrecosto $a): float => (float) $a->exceso), 2),
            'exceso_evitable' => round((float) $evitable, 2),
            // Ranking de causas: es la salida de Capa 4. Un hospital cuya
            // primera causa es «desperdicio de insumos» tiene un problema
            // distinto del que encabeza «retraso de alistamiento», y hasta
            // que las causas no se cuentan las dos se ven igual.
            'por_causa' => $revisadas
                ->filter(fn (AlertaSobrecosto $a): bool => $a->causa !== null)
                ->groupBy(fn (AlertaSobrecosto $a): string => $a->causa->value)
                ->map(fn ($grupo, string $valor): array => [
                    'causa' => $valor,
                    'etiqueta' => CausaSobrecosto::from($valor)->etiqueta(),
                    'evitable' => CausaSobrecosto::from($valor)->evitable(),
                    'n' => $grupo->count(),
                    'exceso' => round($grupo->sum(fn (AlertaSobrecosto $a): float => (float) $a->exceso), 2),
                ])
                ->values()
                ->sortByDesc('exceso')
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function presentar(AlertaSobrecosto $alerta): array
    {
        return [
            'id' => $alerta->id,
            'cirugia_id' => $alerta->cirugia_id,
            'fecha' => $alerta->cirugia?->fecha?->toDateString(),
            'procedimiento' => [
                'id' => $alerta->procedimiento?->id,
                'codigo_cups' => $alerta->procedimiento?->codigo_cups,
                'nombre' => $alerta->procedimiento?->nombre,
            ],
            'costo_total' => (float) $alerta->costo_total,
            'costo_esperado' => (float) $alerta->costo_esperado,
            'exceso' => (float) $alerta->exceso,
            'exceso_pct' => (float) $alerta->exceso_pct,
            'z' => $alerta->z !== null ? (float) $alerta->z : null,
            'criterios' => $alerta->criterios,
            'n_baseline' => $alerta->n_baseline,
            'atribucion' => $alerta->atribucion,
            'componente_dominante' => $alerta->componente_dominante->value,
            'componente_dominante_etiqueta' => $alerta->componente_dominante->etiqueta(),
            // Las causas probables del componente dominante encabezan el
            // selector: reduce el sesgo de elegir siempre la primera opción.
            'causas_sugeridas' => array_map(
                fn (CausaSobrecosto $c): string => $c->value,
                $alerta->componente_dominante->causasProbables(),
            ),
            'estado' => $alerta->estado->value,
            'estado_etiqueta' => $alerta->estado->etiqueta(),
            'causa' => $alerta->causa?->value,
            'causa_etiqueta' => $alerta->causa?->etiqueta(),
            'causa_evitable' => $alerta->causa?->evitable(),
            'causa_detalle' => $alerta->causa_detalle,
            'revisor' => $alerta->revisor?->name,
            'revisado_en' => $alerta->revisado_en?->toDateTimeString(),
            'detectado_en' => $alerta->detectado_en->toDateTimeString(),
        ];
    }
}
