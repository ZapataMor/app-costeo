import { Head } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ComposedChart,
    LabelList,
    Legend,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import { SelectorPeriodo } from '@/components/costeo/selector-periodo';
import { TablaResponsive } from '@/components/costeo/tabla-responsive';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, copCorto, pct } from '@/lib/formato';
import { COLOR, etiquetaEje, MARGEN } from '@/lib/graficas';
import type { GlosasRecaudo, MargenProcedimiento } from '@/types/costeo';

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
    // El margen es el dato de esta pantalla y solo estaba en la tabla: se
    // ordena por él y se le da su propia gráfica.
    const datos = [...por_procedimiento]
        .sort(
            (a, b) =>
                (a.margen_vs_facturado ?? 0) - (b.margen_vs_facturado ?? 0),
        )
        .map((fila) => ({
            ...fila,
            etiqueta: etiquetaEje(fila.procedimiento.nombre),
        }));

    const sinDatos = datos.length === 0;

    const aPerdida = por_procedimiento.filter(
        (fila) =>
            fila.margen_vs_facturado !== null && fila.margen_vs_facturado < 0,
    );

    return (
        <>
            <Head title="Rentabilidad" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Rentabilidad"
                    descripcion="Qué deja cada procedimiento: costo real TDABC frente a lo facturado y a la referencia SOAT −25 %."
                />

                <SelectorPeriodo
                    url="/costeo/rentabilidad"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                />

                <div className="grid gap-[18px] md:grid-cols-4">
                    <KpiCard
                        titulo="Facturado"
                        valor={cop(glosasRecaudo.valor_facturado)}
                        detalle={`${glosasRecaudo.n_facturas} facturas`}
                    />
                    <KpiCard
                        titulo="Glosado"
                        valor={cop(glosasRecaudo.valor_glosado)}
                        detalle={`tasa ${pct(glosasRecaudo.tasa_glosas)}`}
                    />
                    <KpiCard
                        titulo="Recaudado"
                        valor={cop(glosasRecaudo.valor_recaudado)}
                        detalle={`tasa ${pct(glosasRecaudo.tasa_recaudo)}`}
                    />
                    <KpiCard
                        titulo="Procedimientos a pérdida"
                        valor={String(aPerdida.length)}
                        detalle={
                            aPerdida.length === 0
                                ? 'todos cubren su costo'
                                : aPerdida
                                      .map((f) => f.procedimiento.nombre)
                                      .join(', ')
                        }
                    />
                </div>

                {/* Gráfica nueva: el margen por sí solo, con el cero como
                    referencia. En la de barras agrupadas había que restar dos
                    barras a ojo para saber si un procedimiento daba pérdida. */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Margen por procedimiento
                        </CardTitle>
                        <CardDescription>
                            Diferencia entre la tarifa facturada promedio y el
                            costo real. Por debajo de cero, el procedimiento se
                            hace a pérdida.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="h-72">
                        {sinDatos ? (
                            <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                No hay procedimientos facturados en este
                                periodo.
                            </div>
                        ) : (
                            <ResponsiveContainer width="100%" height="100%">
                                <ComposedChart data={datos} margin={MARGEN}>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        className="opacity-30"
                                        vertical={false}
                                    />
                                    <XAxis
                                        dataKey="etiqueta"
                                        fontSize={11}
                                        interval={0}
                                        height={48}
                                        tickMargin={10}
                                    />
                                    <YAxis
                                        tickFormatter={(v: number) =>
                                            copCorto(v)
                                        }
                                        fontSize={11}
                                        width={64}
                                    />
                                    <Tooltip
                                        formatter={(valor) =>
                                            cop(Number(valor))
                                        }
                                        labelFormatter={(etiqueta) => {
                                            const fila = datos.find(
                                                (d) => d.etiqueta === etiqueta,
                                            );

                                            return fila
                                                ? `${fila.procedimiento.nombre} · n=${fila.n}`
                                                : String(etiqueta);
                                        }}
                                    />
                                    <ReferenceLine
                                        y={0}
                                        stroke="currentColor"
                                    />
                                    <Bar
                                        dataKey="margen_vs_facturado"
                                        name="Margen vs. facturado"
                                        maxBarSize={70}
                                    >
                                        <LabelList
                                            dataKey="margen_vs_facturado"
                                            position="top"
                                            fontSize={11}
                                            formatter={(v) =>
                                                copCorto(Number(v))
                                            }
                                        />
                                        {datos.map((fila) => (
                                            <Cell
                                                key={fila.procedimiento.id}
                                                fill={
                                                    (fila.margen_vs_facturado ??
                                                        0) >= 0
                                                        ? COLOR.bien
                                                        : COLOR.alerta
                                                }
                                            />
                                        ))}
                                    </Bar>
                                </ComposedChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Costo real vs. tarifas
                        </CardTitle>
                        <CardDescription>
                            Costo promedio TDABC comparado con la tarifa
                            facturada promedio y la referencia SOAT −25 %
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="h-96 overflow-x-auto">
                        <div
                            className="h-full"
                            style={{
                                minWidth: `${Math.max(720, datos.length * 130)}px`,
                            }}
                        >
                            {sinDatos ? (
                                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                    Sin datos en este periodo.
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={datos} margin={MARGEN}>
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            className="opacity-30"
                                            vertical={false}
                                        />
                                        <XAxis
                                            dataKey="etiqueta"
                                            fontSize={11}
                                            interval={0}
                                            tickMargin={10}
                                            height={48}
                                        />
                                        <YAxis
                                            tickFormatter={(v: number) =>
                                                copCorto(v)
                                            }
                                            fontSize={11}
                                            width={64}
                                        />
                                        <Tooltip
                                            formatter={(valor) =>
                                                cop(Number(valor))
                                            }
                                            labelFormatter={(etiqueta) => {
                                                const fila = datos.find(
                                                    (d) =>
                                                        d.etiqueta === etiqueta,
                                                );

                                                return fila
                                                    ? fila.procedimiento.nombre
                                                    : String(etiqueta);
                                            }}
                                        />
                                        <Legend />
                                        <Bar
                                            dataKey="costo_promedio"
                                            name="Costo promedio"
                                            fill={COLOR.costo}
                                        />
                                        <Bar
                                            dataKey="facturado_promedio"
                                            name="Tarifa facturada"
                                            fill={COLOR.tarifa}
                                        />
                                        <Bar
                                            dataKey="tarifa_referencia"
                                            name="Referencia SOAT −25 %"
                                            fill={COLOR.referencia}
                                        />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Detalle del margen
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TablaResponsive>
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 font-medium">
                                        Procedimiento
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Costo prom.
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Facturado prom.
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Margen vs. facturado
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Margen vs. referencia
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Estado
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {datos.map((fila) => {
                                    const rentable =
                                        fila.margen_vs_facturado !== null &&
                                        fila.margen_vs_facturado >= 0;

                                    return (
                                        <tr
                                            key={fila.procedimiento.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2">
                                                {fila.procedimiento.nombre}{' '}
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {
                                                        fila.procedimiento
                                                            .codigo_cups
                                                    }
                                                </span>
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(fila.costo_promedio)}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(fila.facturado_promedio)}
                                            </td>
                                            <td className="py-2 text-right whitespace-nowrap tabular-nums">
                                                {cop(fila.margen_vs_facturado)}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    (
                                                    {pct(
                                                        fila.margen_vs_facturado_pct,
                                                    )}
                                                    )
                                                </span>
                                            </td>
                                            <td className="py-2 text-right whitespace-nowrap tabular-nums">
                                                {cop(fila.margen_vs_referencia)}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    (
                                                    {pct(
                                                        fila.margen_vs_referencia_pct,
                                                    )}
                                                    )
                                                </span>
                                            </td>
                                            <td className="py-2 text-right">
                                                {fila.margen_vs_facturado ===
                                                null ? (
                                                    <Badge variant="secondary">
                                                        sin facturación
                                                    </Badge>
                                                ) : rentable ? (
                                                    <Badge className="bg-[#4C837C] text-white hover:bg-[#4C837C]">
                                                        rentable
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="destructive">
                                                        a pérdida
                                                    </Badge>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </TablaResponsive>
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
