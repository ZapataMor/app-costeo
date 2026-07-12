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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cop } from '@/lib/formato';
import type { ComponentesProcedimiento } from '@/types/costeo';

const series = [
    { clave: 'recurso_humano', nombre: 'Recurso humano', color: '#3b82f6' },
    { clave: 'sala', nombre: 'Sala', color: '#22c55e' },
    { clave: 'equipos', nombre: 'Equipos médicos', color: '#f59e0b' },
    { clave: 'insumos', nombre: 'Insumos', color: '#8b5cf6' },
    { clave: 'indirectos', nombre: 'Indirectos', color: '#64748b' },
] as const;

export default function Componentes({
    por_procedimiento,
}: {
    por_procedimiento: ComponentesProcedimiento[];
}) {
    const datos = por_procedimiento.map((fila) => ({
        ...fila,
        etiqueta: `${fila.procedimiento.codigo_cups}`,
    }));

    return (
        <>
            <Head title="Costo por componente" />
            <div className="flex flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Costo promedio por componente y procedimiento
                        </CardTitle>
                        <CardDescription>
                            Promedio TDABC de cada componente sobre las cirugías costeadas (barras apiladas)
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="h-96">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={datos}>
                                <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
                                <XAxis dataKey="etiqueta" fontSize={12} />
                                <YAxis tickFormatter={(v: number) => cop(v)} fontSize={11} width={90} />
                                <Tooltip
                                    formatter={(valor) => cop(Number(valor))}
                                    labelFormatter={(cups) => {
                                        const fila = datos.find((d) => d.etiqueta === cups);

                                        return fila ? `${fila.procedimiento.nombre} (${cups})` : cups;
                                    }}
                                />
                                <Legend />
                                {series.map((serie) => (
                                    <Bar
                                        key={serie.clave}
                                        dataKey={serie.clave}
                                        name={serie.nombre}
                                        stackId="componentes"
                                        fill={serie.color}
                                    />
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Detalle por procedimiento</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 font-medium">Procedimiento</th>
                                    <th className="py-2 text-right font-medium">n</th>
                                    {series.map((serie) => (
                                        <th key={serie.clave} className="py-2 text-right font-medium">
                                            {serie.nombre}
                                        </th>
                                    ))}
                                    <th className="py-2 text-right font-medium">Total</th>
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
                                        {series.map((serie) => (
                                            <td key={serie.clave} className="py-2 text-right tabular-nums">
                                                {cop(fila[serie.clave])}
                                            </td>
                                        ))}
                                        <td className="py-2 text-right font-medium tabular-nums">
                                            {cop(fila.total)}
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

Componentes.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Costo por componente', href: '/costeo/componentes' },
    ],
};
