import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import { SelectorPeriodo } from '@/components/costeo/selector-periodo';
import { TablaResponsive } from '@/components/costeo/tabla-responsive';
import { NombreDato } from '@/components/nombre-dato';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, numero, pct } from '@/lib/formato';
import { TEXTO_TONO, tonoMayorEsMejor, UMBRALES } from '@/lib/semaforo';
import type { GlosasRecaudo, MargenProcedimiento } from '@/types/costeo';

type Orden = 'total' | 'porcentaje';

/** Resultado de todas las cirugías del procedimiento: margen × n. */
function resultadoTotal(fila: MargenProcedimiento): number | null {
    return fila.margen_vs_facturado === null
        ? null
        : fila.margen_vs_facturado * fila.n;
}

/**
 * Barra divergente del margen: a la izquierda del cero en rojo (pérdida), a
 * la derecha en verde (ganancia). Reemplaza la gráfica de barras verticales,
 * que no tenía nombres en el eje y obligaba a pasar el cursor por cada una.
 */
function BarraMargen({
    valor,
    maximo,
}: {
    valor: number | null;
    maximo: number;
}) {
    if (valor === null) {
        return null;
    }

    const ancho = `${Math.min(100, (Math.abs(valor) / maximo) * 100)}%`;

    return (
        <div className="flex h-2.5 w-36 items-center" aria-hidden>
            <div className="flex h-full flex-1 justify-end overflow-hidden rounded-l bg-muted">
                {valor < 0 && (
                    <div
                        className="h-full bg-red-600/80 dark:bg-red-400/80"
                        style={{ width: ancho }}
                    />
                )}
            </div>
            <div className="h-4 w-px bg-foreground/50" />
            <div className="flex h-full flex-1 overflow-hidden rounded-r bg-muted">
                {valor >= 0 && (
                    <div
                        className="h-full bg-emerald-600/80 dark:bg-emerald-400/80"
                        style={{ width: ancho }}
                    />
                )}
            </div>
        </div>
    );
}

export default function Rentabilidad({
    por_procedimiento,
    glosasRecaudo,
    periodo,
    periodoEtiqueta,
}: {
    factor_referencia_soat: number;
    por_procedimiento: MargenProcedimiento[];
    glosasRecaudo: GlosasRecaudo;
    periodo: Periodo;
    periodoEtiqueta: string;
}) {
    const [orden, setOrden] = useState<Orden>('total');

    const conFactura = por_procedimiento.filter(
        (fila) => fila.margen_vs_facturado !== null,
    );
    const aPerdida = conFactura.filter(
        (fila) => (fila.margen_vs_facturado ?? 0) < 0,
    );

    const neto = conFactura.reduce(
        (suma, fila) => suma + (resultadoTotal(fila) ?? 0),
        0,
    );
    const facturadoTotal = conFactura.reduce(
        (suma, fila) => suma + (fila.facturado_promedio ?? 0) * fila.n,
        0,
    );
    const netoPct = facturadoTotal > 0 ? neto / facturadoTotal : null;
    const perdidaTotal = aPerdida.reduce(
        (suma, fila) => suma + (resultadoTotal(fila) ?? 0),
        0,
    );

    // Lo que más plata pierde arriba; los que no tienen facturación, al final.
    const filas = [...por_procedimiento].sort((a, b) => {
        const va =
            orden === 'total' ? resultadoTotal(a) : a.margen_vs_facturado_pct;
        const vb =
            orden === 'total' ? resultadoTotal(b) : b.margen_vs_facturado_pct;

        if (va === null || vb === null) {
            return va === null ? (vb === null ? 0 : 1) : -1;
        }

        return va - vb;
    });

    const maximoPct = Math.max(
        0.05,
        ...conFactura.map((fila) =>
            Math.abs(fila.margen_vs_facturado_pct ?? 0),
        ),
    );

    return (
        <>
            <Head title="Rentabilidad" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Rentabilidad"
                    descripcion="Qué deja cada procedimiento: costo real frente a lo facturado y a la referencia SOAT −25 %."
                />

                <SelectorPeriodo
                    url="/costeo/rentabilidad"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                />

                <div className="grid gap-[18px] md:grid-cols-3 xl:grid-cols-5">
                    <KpiCard
                        titulo="Resultado neto"
                        valor={cop(neto)}
                        tono={tonoMayorEsMejor(netoPct, UMBRALES.margen)}
                        detalle={`${pct(netoPct)} de lo facturado`}
                    />
                    <KpiCard
                        titulo="Procedimientos a pérdida"
                        valor={`${aPerdida.length} de ${conFactura.length}`}
                        tono={aPerdida.length === 0 ? 'bien' : 'alerta'}
                        detalle={
                            aPerdida.length === 0
                                ? 'Todos cubren su costo'
                                : `Pierden ${cop(Math.abs(perdidaTotal))} en total`
                        }
                    />
                    <KpiCard
                        titulo="Facturado"
                        valor={cop(glosasRecaudo.valor_facturado)}
                        detalle={`${numero(glosasRecaudo.n_facturas)} facturas`}
                    />
                    <KpiCard
                        titulo="Glosado"
                        valor={cop(glosasRecaudo.valor_glosado)}
                        detalle={`Tasa de glosas ${pct(glosasRecaudo.tasa_glosas)}`}
                    />
                    <KpiCard
                        titulo="Recaudado"
                        valor={cop(glosasRecaudo.valor_recaudado)}
                        detalle={`Tasa de recaudo ${pct(glosasRecaudo.tasa_recaudo)}`}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <CardTitle className="text-base">
                                    Margen por procedimiento
                                </CardTitle>
                                <CardDescription>
                                    Tarifa facturada promedio menos costo real
                                    promedio. A la izquierda del cero, el
                                    procedimiento se hace a pérdida.
                                </CardDescription>
                            </div>
                            <div
                                className="flex gap-1"
                                role="group"
                                aria-label="Ordenar"
                            >
                                <Button
                                    size="sm"
                                    variant={
                                        orden === 'total'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => setOrden('total')}
                                >
                                    Por resultado total
                                </Button>
                                <Button
                                    size="sm"
                                    variant={
                                        orden === 'porcentaje'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => setOrden('porcentaje')}
                                >
                                    Por margen %
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {filas.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No hay procedimientos facturados en este
                                periodo.
                            </p>
                        ) : (
                            <TablaResponsive>
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="py-2 pr-3">
                                            Procedimiento
                                        </th>
                                        <th className="py-2 pr-3 text-right">
                                            Cirugías
                                        </th>
                                        <th className="py-2 pr-3 text-right">
                                            Costo prom.
                                        </th>
                                        <th className="py-2 pr-3 text-right">
                                            Facturado prom.
                                        </th>
                                        <th className="py-2 pr-3 text-right">
                                            Margen por cirugía
                                        </th>
                                        <th className="py-2 pr-3">Margen %</th>
                                        <th className="py-2 pr-3 text-right">
                                            Resultado total
                                        </th>
                                        <th
                                            className="py-2 text-right"
                                            title="Margen frente a la tarifa SOAT −25 %"
                                        >
                                            Vs. SOAT −25 %
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filas.map((fila) => {
                                        const total = resultadoTotal(fila);
                                        const tono =
                                            total === null
                                                ? 'neutro'
                                                : total < 0
                                                  ? 'alerta'
                                                  : 'bien';

                                        return (
                                            <tr
                                                key={fila.procedimiento.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="max-w-[18rem] py-2 pr-3">
                                                    <Link
                                                        href={`/costeo/procedimientos/${fila.procedimiento.id}`}
                                                        className="block truncate hover:underline"
                                                    >
                                                        <NombreDato
                                                            texto={
                                                                fila
                                                                    .procedimiento
                                                                    .nombre
                                                            }
                                                        />
                                                    </Link>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {
                                                            fila.procedimiento
                                                                .codigo_cups
                                                        }
                                                    </span>
                                                </td>
                                                <td className="py-2 pr-3 text-right tabular-nums">
                                                    {numero(fila.n)}
                                                </td>
                                                <td className="py-2 pr-3 text-right whitespace-nowrap tabular-nums">
                                                    {cop(fila.costo_promedio)}
                                                </td>
                                                <td className="py-2 pr-3 text-right whitespace-nowrap tabular-nums">
                                                    {cop(
                                                        fila.facturado_promedio,
                                                    )}
                                                </td>
                                                <td
                                                    className={`py-2 pr-3 text-right whitespace-nowrap tabular-nums ${TEXTO_TONO[tono]}`}
                                                >
                                                    {cop(
                                                        fila.margen_vs_facturado,
                                                    )}
                                                </td>
                                                <td className="py-2 pr-3">
                                                    {fila.margen_vs_facturado_pct ===
                                                    null ? (
                                                        <Badge variant="secondary">
                                                            sin facturación
                                                        </Badge>
                                                    ) : (
                                                        <div className="flex items-center gap-2">
                                                            <BarraMargen
                                                                valor={
                                                                    fila.margen_vs_facturado_pct
                                                                }
                                                                maximo={
                                                                    maximoPct
                                                                }
                                                            />
                                                            <span
                                                                className={`w-14 text-right text-xs tabular-nums ${TEXTO_TONO[tono]}`}
                                                            >
                                                                {pct(
                                                                    fila.margen_vs_facturado_pct,
                                                                )}
                                                            </span>
                                                        </div>
                                                    )}
                                                </td>
                                                <td
                                                    className={`py-2 pr-3 text-right font-medium whitespace-nowrap tabular-nums ${TEXTO_TONO[tono]}`}
                                                >
                                                    {cop(total)}
                                                </td>
                                                <td className="py-2 text-right whitespace-nowrap text-muted-foreground tabular-nums">
                                                    {pct(
                                                        fila.margen_vs_referencia_pct,
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </TablaResponsive>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Rentabilidad.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Rentabilidad', href: '/costeo/rentabilidad' },
    ],
};
