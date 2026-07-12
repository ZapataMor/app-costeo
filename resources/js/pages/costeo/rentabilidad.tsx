import { Head } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { KpiCard } from '@/components/costeo/kpi-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cop, pct } from '@/lib/formato';
import type { GlosasRecaudo, MargenProcedimiento } from '@/types/costeo';

export default function Rentabilidad({
    por_procedimiento,
    glosasRecaudo,
}: {
    factor_referencia_soat: number;
    por_procedimiento: MargenProcedimiento[];
    glosasRecaudo: GlosasRecaudo;
}) {
    const datos = por_procedimiento.map((fila) => ({
        ...fila,
        etiqueta: fila.procedimiento.codigo_cups,
    }));

    return (
        <>
            <Head title="Rentabilidad" />
            <div className="flex flex-col gap-4 p-4">
                <div className="grid gap-4 md:grid-cols-4">
                    <KpiCard
                        titulo="Facturado"
                        valor={cop(glosasRecaudo.valor_facturado)}
                        detalle={`${glosasRecaudo.n_facturas} facturas`}
                    />
                    <KpiCard titulo="Glosado" valor={cop(glosasRecaudo.valor_glosado)} detalle={`tasa ${pct(glosasRecaudo.tasa_glosas)}`} />
                    <KpiCard titulo="Recaudado" valor={cop(glosasRecaudo.valor_recaudado)} detalle={`tasa ${pct(glosasRecaudo.tasa_recaudo)}`} />
                    <KpiCard
                        titulo="Referencia tarifaria"
                        valor="SOAT −25 %"
                        detalle="base de comparación del margen"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Costo real vs. tarifas</CardTitle>
                        <CardDescription>
                            Costo promedio TDABC comparado con la tarifa facturada promedio y la
                            referencia SOAT −25 % por procedimiento
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="h-96">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={datos}>
                                <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
                                <XAxis dataKey="etiqueta" fontSize={12} />
                                <YAxis tickFormatter={(v: number) => cop(v)} fontSize={11} width={95} />
                                <Tooltip
                                    formatter={(valor) => cop(Number(valor))}
                                    labelFormatter={(cups) => {
                                        const fila = datos.find((d) => d.etiqueta === cups);

                                        return fila ? `${fila.procedimiento.nombre} (${cups})` : cups;
                                    }}
                                />
                                <Legend />
                                <Bar dataKey="costo_promedio" name="Costo promedio" fill="#ef4444" />
                                <Bar dataKey="facturado_promedio" name="Tarifa facturada" fill="#3b82f6" />
                                <Bar dataKey="tarifa_referencia" name="Referencia SOAT −25 %" fill="#22c55e" />
                            </BarChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Margen por procedimiento</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 font-medium">Procedimiento</th>
                                    <th className="py-2 text-right font-medium">Costo prom.</th>
                                    <th className="py-2 text-right font-medium">Facturado prom.</th>
                                    <th className="py-2 text-right font-medium">Margen vs. facturado</th>
                                    <th className="py-2 text-right font-medium">Margen vs. referencia</th>
                                    <th className="py-2 text-right font-medium">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {por_procedimiento.map((fila) => {
                                    const rentable =
                                        fila.margen_vs_facturado !== null && fila.margen_vs_facturado >= 0;

                                    return (
                                        <tr key={fila.procedimiento.id} className="border-b last:border-0">
                                            <td className="py-2">
                                                {fila.procedimiento.nombre}{' '}
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {fila.procedimiento.codigo_cups}
                                                </span>
                                            </td>
                                            <td className="py-2 text-right tabular-nums">{cop(fila.costo_promedio)}</td>
                                            <td className="py-2 text-right tabular-nums">{cop(fila.facturado_promedio)}</td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(fila.margen_vs_facturado)}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    ({pct(fila.margen_vs_facturado_pct)})
                                                </span>
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(fila.margen_vs_referencia)}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    ({pct(fila.margen_vs_referencia_pct)})
                                                </span>
                                            </td>
                                            <td className="py-2 text-right">
                                                {fila.margen_vs_facturado === null ? (
                                                    <Badge variant="secondary">sin facturación</Badge>
                                                ) : rentable ? (
                                                    <Badge className="bg-green-600 text-white hover:bg-green-600">rentable</Badge>
                                                ) : (
                                                    <Badge variant="destructive">a pérdida</Badge>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
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
