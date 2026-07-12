import { Head } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cop, pct } from '@/lib/formato';
import type { VariabilidadProcedimiento } from '@/types/costeo';

const colorPorNivel: Record<string, string> = {
    alta: '#ef4444',
    media: '#f59e0b',
    baja: '#22c55e',
};

export default function Variabilidad({
    por_procedimiento,
}: {
    por_procedimiento: VariabilidadProcedimiento[];
}) {
    const datos = por_procedimiento.map((fila) => ({
        ...fila,
        etiqueta: fila.procedimiento.codigo_cups,
        cv_pct: fila.coeficiente_variacion !== null ? fila.coeficiente_variacion * 100 : 0,
    }));

    return (
        <>
            <Head title="Variabilidad de costos" />
            <div className="flex flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Coeficiente de variación por procedimiento
                        </CardTitle>
                        <CardDescription>
                            CV = desviación estándar ÷ media del costo total. Umbrales: alta &gt; 30 %,
                            media &gt; 15 %, baja ≤ 15 % (líneas punteadas).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="h-96">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={datos}>
                                <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
                                <XAxis dataKey="etiqueta" fontSize={12} />
                                <YAxis
                                    tickFormatter={(v: number) => `${v.toFixed(0)} %`}
                                    fontSize={11}
                                    width={50}
                                />
                                <Tooltip
                                    formatter={(valor) => `${Number(valor).toFixed(1)} %`}
                                    labelFormatter={(cups) => {
                                        const fila = datos.find((d) => d.etiqueta === cups);

                                        return fila ? `${fila.procedimiento.nombre} (${cups})` : cups;
                                    }}
                                />
                                <ReferenceLine y={30} stroke="#ef4444" strokeDasharray="6 4" />
                                <ReferenceLine y={15} stroke="#f59e0b" strokeDasharray="6 4" />
                                <Bar dataKey="cv_pct" name="CV">
                                    {datos.map((fila) => (
                                        <Cell
                                            key={fila.procedimiento.id}
                                            fill={colorPorNivel[fila.nivel_variabilidad ?? 'baja']}
                                        />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Detalle estadístico</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 font-medium">Procedimiento</th>
                                    <th className="py-2 text-right font-medium">n</th>
                                    <th className="py-2 text-right font-medium">Media</th>
                                    <th className="py-2 text-right font-medium">Desviación</th>
                                    <th className="py-2 text-right font-medium">CV</th>
                                    <th className="py-2 text-right font-medium">Nivel</th>
                                </tr>
                            </thead>
                            <tbody>
                                {por_procedimiento.map((fila) => (
                                    <tr key={fila.procedimiento.id} className="border-b last:border-0">
                                        <td className="py-2">
                                            {fila.procedimiento.nombre}{' '}
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {fila.procedimiento.codigo_cups}
                                            </span>
                                        </td>
                                        <td className="py-2 text-right tabular-nums">{fila.n}</td>
                                        <td className="py-2 text-right tabular-nums">{cop(fila.media)}</td>
                                        <td className="py-2 text-right tabular-nums">{cop(fila.desviacion)}</td>
                                        <td className="py-2 text-right tabular-nums">{pct(fila.coeficiente_variacion)}</td>
                                        <td className="py-2 text-right">
                                            {fila.nivel_variabilidad === 'alta' && <Badge variant="destructive">alta</Badge>}
                                            {fila.nivel_variabilidad === 'media' && <Badge className="bg-amber-500 text-white hover:bg-amber-500">media</Badge>}
                                            {fila.nivel_variabilidad === 'baja' && <Badge className="bg-green-600 text-white hover:bg-green-600">baja</Badge>}
                                            {fila.nivel_variabilidad === null && <Badge variant="secondary">n/d</Badge>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Variabilidad.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Variabilidad', href: '/costeo/variabilidad' },
    ],
};
