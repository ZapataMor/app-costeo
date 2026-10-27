import { KpiCard } from '@/components/costeo/kpi-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cop, etiqueta, pct, pctVariacion } from '@/lib/formato';
import { COMPONENTES } from '@/lib/graficas';
import type { Tono } from '@/lib/semaforo';
import { tonoMayorEsMejor, UMBRALES } from '@/lib/semaforo';
import type {
    ClasificacionDesviacion,
    CostoCirugia,
    Facturacion,
} from '@/types/cirugias';

const TONO_CLASIFICACION: Record<ClasificacionDesviacion, Tono> = {
    subejecucion: 'bien',
    en_rango: 'bien',
    sobrecosto_leve: 'atencion',
    sobrecosto_alto: 'alerta',
};

const NOMBRE_CLASIFICACION: Record<ClasificacionDesviacion, string> = {
    subejecucion: 'subejecución',
    en_rango: 'en rango',
    sobrecosto_leve: 'sobrecosto leve',
    sobrecosto_alto: 'sobrecosto alto',
};

/**
 * Lo primero que se lee de una cirugía (#34): cuánto costó, si se salió de
 * su protocolo, cuánto dejó y si ya cuenta en los indicadores, y de qué está
 * hecho el costo. Antes eran seis o siete tablas apiladas y el veredicto
 * aparecía a mitad de página; el cálculo completo sigue disponible, plegado.
 */
export function ResumenCosto({
    costo,
    facturacion,
    estado,
    contabilizada,
    duracionMinutos,
}: {
    costo: CostoCirugia;
    facturacion: Facturacion | null;
    estado: string;
    contabilizada: boolean;
    duracionMinutos: number | null;
}) {
    const total = Number(costo.costo_total);
    const clasificacion = costo.clasificacion_desviacion;
    const facturado =
        facturacion !== null ? Number(facturacion.valor_facturado) : null;
    const margen = facturado !== null ? facturado - total : null;
    const margenPct =
        margen !== null && facturado !== null && facturado > 0
            ? margen / facturado
            : null;

    const componentes = [
        { ...COMPONENTES[0], valor: Number(costo.costo_recurso_humano) },
        { ...COMPONENTES[1], valor: Number(costo.costo_sala) },
        { ...COMPONENTES[2], valor: Number(costo.costo_equipos) },
        { ...COMPONENTES[3], valor: Number(costo.costo_insumos) },
        { ...COMPONENTES[4], valor: Number(costo.costo_indirecto) },
    ].filter((c) => c.valor > 0);

    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-[18px] sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    titulo="Costo total"
                    valor={cop(total)}
                    detalle={`Directo ${cop(Number(costo.costo_directo))} · indirecto ${cop(Number(costo.costo_indirecto))}`}
                />
                <KpiCard
                    titulo="Frente al estándar del protocolo"
                    valor={
                        costo.desviacion_pct === null
                            ? 'Sin estándar'
                            : pctVariacion(Number(costo.desviacion_pct))
                    }
                    tono={
                        clasificacion === null
                            ? 'neutro'
                            : TONO_CLASIFICACION[clasificacion]
                    }
                    detalle={
                        costo.costo_estandar === null
                            ? 'El procedimiento no tiene plantilla de protocolo'
                            : `Debió costar ${cop(Number(costo.costo_estandar))}${clasificacion ? ` · ${NOMBRE_CLASIFICACION[clasificacion]}` : ''}`
                    }
                />
                <KpiCard
                    titulo="Margen sobre lo facturado"
                    valor={margen === null ? 'Sin facturar' : cop(margen)}
                    tono={
                        margenPct === null
                            ? 'neutro'
                            : tonoMayorEsMejor(margenPct, UMBRALES.margen)
                    }
                    detalle={
                        facturado === null
                            ? 'Registre la facturación para ver el margen'
                            : `${pct(margenPct)} de ${cop(facturado)} facturados`
                    }
                />
                <KpiCard
                    titulo="Estado"
                    valor={etiqueta(estado)}
                    tono={contabilizada ? 'bien' : 'atencion'}
                    detalle={`${contabilizada ? 'Cuenta en los indicadores' : 'Todavía no cuenta en los indicadores'}${duracionMinutos !== null ? ` · ${duracionMinutos} min` : ''}`}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        De qué está hecho el costo
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        className="flex h-4 w-full overflow-hidden rounded-full bg-muted"
                        role="img"
                        aria-label={componentes
                            .map(
                                (c) =>
                                    `${c.nombre} ${pct(total > 0 ? c.valor / total : null, 0)}`,
                            )
                            .join(', ')}
                    >
                        {componentes.map((c) => (
                            <div
                                key={c.clave}
                                className={
                                    c.clave === 'indirectos'
                                        ? 'h-full bg-[#161B2F] dark:bg-[#D4CDCB]'
                                        : 'h-full'
                                }
                                style={{
                                    width: `${total > 0 ? (c.valor / total) * 100 : 0}%`,
                                    backgroundColor:
                                        c.clave === 'indirectos'
                                            ? undefined
                                            : c.color,
                                }}
                                title={`${c.nombre}: ${cop(c.valor)}`}
                            />
                        ))}
                    </div>
                    <ul className="mt-3 flex flex-wrap gap-x-6 gap-y-1.5 text-sm">
                        {componentes.map((c) => (
                            <li
                                key={c.clave}
                                className="flex items-center gap-2"
                            >
                                <span
                                    className={`size-2.5 shrink-0 rounded-sm ${c.clave === 'indirectos' ? 'bg-[#161B2F] dark:bg-[#D4CDCB]' : ''}`}
                                    style={{
                                        backgroundColor:
                                            c.clave === 'indirectos'
                                                ? undefined
                                                : c.color,
                                    }}
                                />
                                <span className="text-muted-foreground">
                                    {c.nombre}
                                </span>
                                <span className="tabular-nums">
                                    {cop(c.valor)}{' '}
                                    <span className="text-xs text-muted-foreground">
                                        {pct(
                                            total > 0 ? c.valor / total : null,
                                            0,
                                        )}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </CardContent>
            </Card>
        </div>
    );
}
