import { cop, pct } from '@/lib/formato';
import { COMPONENTES } from '@/lib/graficas';
import type { ClaveComponente } from '@/lib/graficas';

/**
 * Composición del costo en una barra apilada al 100 %: de qué está hecho el
 * costo de un procedimiento sin ocupar una gráfica entera. Reemplaza a la
 * pestaña «Composición del costo», que repetía la tabla de procedimientos.
 *
 * `compacta` es la versión de una fila de tabla (sin leyenda); la normal
 * lleva debajo cada componente con su valor y su peso.
 */
export function BarraComposicion({
    componentes,
    compacta = false,
}: {
    componentes: Record<ClaveComponente, number>;
    compacta?: boolean;
}) {
    const total = COMPONENTES.reduce(
        (suma, c) => suma + (componentes[c.clave] ?? 0),
        0,
    );

    if (total <= 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    const tramos = COMPONENTES.map((c) => ({
        ...c,
        valor: componentes[c.clave] ?? 0,
        peso: (componentes[c.clave] ?? 0) / total,
    }));

    const descripcion = tramos
        .filter((t) => t.peso > 0)
        .map((t) => `${t.nombre} ${pct(t.peso, 0)}`)
        .join(', ');

    return (
        <div className={compacta ? 'w-full min-w-28' : 'w-full'}>
            <div
                className={`flex w-full overflow-hidden rounded ${compacta ? 'h-2' : 'h-3'}`}
                role="img"
                aria-label={`Composición del costo: ${descripcion}`}
                title={compacta ? descripcion : undefined}
            >
                {tramos.map((t) =>
                    t.peso > 0 ? (
                        <div
                            key={t.clave}
                            style={{
                                width: `${t.peso * 100}%`,
                                backgroundColor: t.color,
                            }}
                        />
                    ) : null,
                )}
            </div>
            {!compacta && (
                <ul className="mt-3 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    {tramos.map((t) => (
                        <li key={t.clave} className="flex items-center gap-2">
                            <span
                                className="size-2.5 shrink-0 rounded-sm"
                                style={{ backgroundColor: t.color }}
                                aria-hidden
                            />
                            <span className="flex-1">{t.nombre}</span>
                            <span className="tabular-nums">{cop(t.valor)}</span>
                            <span className="w-10 text-right text-muted-foreground tabular-nums">
                                {pct(t.peso, 0)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/** Leyenda suelta de los componentes, para encabezar una tabla de barras. */
export function LeyendaComposicion() {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {COMPONENTES.map((c) => (
                <li key={c.clave} className="flex items-center gap-1.5">
                    <span
                        className="size-2.5 rounded-sm"
                        style={{ backgroundColor: c.color }}
                        aria-hidden
                    />
                    {c.nombre}
                </li>
            ))}
        </ul>
    );
}
