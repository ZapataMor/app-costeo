import { TrendingDown, TrendingUp } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, pct } from '@/lib/formato';
import { COMPONENTES } from '@/lib/graficas';
import type { CostoCirugia } from '@/types/cirugias';
import type { ReferenciaProcedimiento } from '@/types/costeo';

/**
 * Composición del costo de una cirugía y su comparación con el promedio del
 * procedimiento.
 *
 * El detalle tenía el desglose completo en tablas y ni un elemento visual:
 * se veía que la cirugía costó $403.873, pero no si eso era mucho o poco, ni
 * qué componente explicaba la diferencia. Esas dos preguntas son las que
 * convierten una tabla en un hallazgo.
 */
export function ComposicionCirugia({
    costo,
    referencia,
}: {
    costo: CostoCirugia;
    referencia: ReferenciaProcedimiento | null;
}) {
    const total = Number(costo.costo_total);

    const valores: Record<string, number> = {
        recurso_humano: Number(costo.costo_recurso_humano),
        sala: Number(costo.costo_sala),
        equipos: Number(costo.costo_equipos),
        insumos: Number(costo.costo_insumos),
        indirectos: Number(costo.costo_indirecto),
    };

    const lineas = COMPONENTES.map((componente) => {
        const valor = valores[componente.clave] ?? 0;
        const promedio = referencia?.[componente.clave] ?? null;

        // Un componente que no se usó ni aquí ni en el promedio no tiene nada
        // que comparar: «habitual $ 0 (−$ 0)» es ruido en cada fila.
        const esperado =
            promedio === null || (promedio === 0 && valor === 0)
                ? null
                : promedio;

        return {
            ...componente,
            valor,
            porcentaje: total > 0 ? valor / total : 0,
            esperado,
            exceso: esperado !== null ? valor - esperado : null,
        };
    });

    const diferencia =
        referencia !== null ? total - referencia.costo_total : null;
    const diferenciaPct =
        referencia !== null && referencia.costo_total > 0
            ? diferencia! / referencia.costo_total
            : null;

    const porEncima = diferencia !== null && diferencia > 0;

    // Quién explica la desviación: el componente que más se separa de lo
    // habitual, que es la primera pregunta al ver un costo raro.
    const dominante =
        referencia === null
            ? null
            : [...lineas]
                  .filter((l) => l.exceso !== null)
                  .sort((a, b) => Math.abs(b.exceso!) - Math.abs(a.exceso!))[0];

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="text-base">
                            Composición del costo
                        </CardTitle>
                        <CardDescription>
                            {referencia === null
                                ? 'Todavía no hay otras cirugías costeadas de este procedimiento para comparar.'
                                : `Comparado con el promedio de las otras ${referencia.n} cirugías del mismo procedimiento.`}
                        </CardDescription>
                    </div>

                    {diferenciaPct !== null && (
                        <span
                            className={`flex items-center gap-1.5 rounded-md px-2.5 py-1 text-sm font-medium tabular-nums ${
                                porEncima
                                    ? 'bg-[#9E3B3B]/10 text-[#9E3B3B]'
                                    : 'bg-[#4C837C]/10 text-[#4C837C]'
                            }`}
                        >
                            {porEncima ? (
                                <TrendingUp className="size-4" />
                            ) : (
                                <TrendingDown className="size-4" />
                            )}
                            {porEncima ? '+' : '−'}
                            {pct(Math.abs(diferenciaPct))}
                            <span className="font-normal opacity-70">
                                vs. promedio
                            </span>
                        </span>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Barra 100 %: de un vistazo, de qué está hecho el costo. */}
                <div>
                    <div className="flex h-6 w-full overflow-hidden rounded-md">
                        {lineas.map((linea) =>
                            linea.valor > 0 ? (
                                <div
                                    key={linea.clave}
                                    style={{
                                        width: `${linea.porcentaje * 100}%`,
                                        background: linea.color,
                                    }}
                                    title={`${linea.nombre}: ${cop(linea.valor)} (${pct(linea.porcentaje)})`}
                                />
                            ) : null,
                        )}
                    </div>
                    <p className="mt-2 text-right text-sm">
                        <span className="text-muted-foreground">Total </span>
                        <span className="text-base font-semibold tabular-nums">
                            {cop(total)}
                        </span>
                    </p>
                </div>

                <ul className="space-y-2.5">
                    {lineas.map((linea) => (
                        <li
                            key={linea.clave}
                            className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                        >
                            <span
                                className="size-2.5 shrink-0 rounded-[3px]"
                                style={{ background: linea.color }}
                            />
                            <span className="w-36 shrink-0">
                                {linea.nombre}
                            </span>
                            <span className="w-24 text-right tabular-nums">
                                {cop(linea.valor)}
                            </span>
                            <span className="w-14 text-right text-xs text-muted-foreground tabular-nums">
                                {pct(linea.porcentaje, 0)}
                            </span>

                            {linea.esperado !== null && (
                                <span className="flex-1 text-right text-xs tabular-nums">
                                    <span className="text-muted-foreground">
                                        habitual {cop(linea.esperado)}{' '}
                                    </span>
                                    <span
                                        className={
                                            (linea.exceso ?? 0) > 0
                                                ? 'text-[#9E3B3B]'
                                                : 'text-[#4C837C]'
                                        }
                                    >
                                        ({(linea.exceso ?? 0) > 0 ? '+' : '−'}
                                        {cop(Math.abs(linea.exceso ?? 0))})
                                    </span>
                                </span>
                            )}
                        </li>
                    ))}
                </ul>

                {dominante !== undefined &&
                    dominante !== null &&
                    Math.abs(dominante.exceso ?? 0) > 0 && (
                        <p className="rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
                            Lo que más separa esta cirugía del promedio es{' '}
                            <span className="font-medium text-foreground">
                                {dominante.nombre.toLowerCase()}
                            </span>
                            : {cop(dominante.valor)} frente a los{' '}
                            {cop(dominante.esperado)} habituales (
                            {(dominante.exceso ?? 0) > 0 ? '+' : '−'}
                            {cop(Math.abs(dominante.exceso ?? 0))}).
                        </p>
                    )}
            </CardContent>
        </Card>
    );
}
