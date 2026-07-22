import { Head } from '@inertiajs/react';
import { useState } from 'react';
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
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import type { Periodo } from '@/components/costeo/selector-periodo';
import { SelectorPeriodo } from '@/components/costeo/selector-periodo';
import { TablaResponsive } from '@/components/costeo/tabla-responsive';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cop, copCorto, pct } from '@/lib/formato';
import { COMPONENTES, etiquetaEje, MARGEN } from '@/lib/graficas';
import type { ComponentesProcedimiento } from '@/types/costeo';

type Escala = 'pesos' | 'porcentaje';

export default function Componentes({
    por_procedimiento,
    periodo,
    periodoEtiqueta,
}: {
    por_procedimiento: ComponentesProcedimiento[];
    periodo: Periodo;
    periodoEtiqueta: string;
}) {
    const [escala, setEscala] = useState<Escala>('pesos');

    const enPorcentaje = escala === 'porcentaje';

    const datos = por_procedimiento.map((fila) => {
        const total = COMPONENTES.reduce(
            (suma, componente) => suma + (fila[componente.clave] ?? 0),
            0,
        );

        // En porcentaje cada barra llega a 100 y se pueden comparar
        // composiciones entre procedimientos de tamaños muy distintos:
        // en pesos, la colecistectomía aplasta a la herniorrafia.
        const valores = Object.fromEntries(
            COMPONENTES.map((componente) => [
                componente.clave,
                enPorcentaje && total > 0
                    ? ((fila[componente.clave] ?? 0) / total) * 100
                    : (fila[componente.clave] ?? 0),
            ]),
        );

        return {
            ...fila,
            ...valores,
            etiqueta: etiquetaEje(fila.procedimiento.nombre),
            total_real: total,
        };
    });

    const sinDatos = datos.length === 0;

    return (
        <>
            <Head title="Costo por componente" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Costo por componente"
                    descripcion="De qué está hecho el costo de cada procedimiento: talento humano, sala, equipos, insumos e indirectos."
                />

                <SelectorPeriodo
                    url="/costeo/componentes"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                />

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <CardTitle className="text-base">
                                    Composición del costo por procedimiento
                                </CardTitle>
                                <CardDescription>
                                    {enPorcentaje
                                        ? 'Peso relativo de cada componente sobre el total del procedimiento'
                                        : 'Promedio TDABC de cada componente sobre las cirugías costeadas'}
                                </CardDescription>
                            </div>
                            <ToggleGroup
                                type="single"
                                size="sm"
                                variant="outline"
                                value={escala}
                                onValueChange={(v) =>
                                    v && setEscala(v as Escala)
                                }
                            >
                                <ToggleGroupItem
                                    value="pesos"
                                    aria-label="Ver en pesos"
                                >
                                    Pesos
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="porcentaje"
                                    aria-label="Ver en porcentaje"
                                >
                                    % del total
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>
                    </CardHeader>
                    <CardContent className="h-96">
                        {sinDatos ? (
                            <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                No hay cirugías costeadas en este periodo.
                            </div>
                        ) : (
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={datos} margin={MARGEN}>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        className="opacity-30"
                                        vertical={false}
                                    />
                                    {/* El eje mostraba el código CUPS: exacto
                                        pero ilegible fuera de facturación. */}
                                    <XAxis
                                        dataKey="etiqueta"
                                        fontSize={11}
                                        interval={0}
                                        height={48}
                                        tickMargin={10}
                                    />
                                    <YAxis
                                        fontSize={11}
                                        width={enPorcentaje ? 44 : 64}
                                        domain={
                                            enPorcentaje ? [0, 100] : undefined
                                        }
                                        tickFormatter={(v: number) =>
                                            enPorcentaje
                                                ? `${v.toFixed(0)} %`
                                                : copCorto(v)
                                        }
                                    />
                                    <Tooltip
                                        formatter={(valor) =>
                                            enPorcentaje
                                                ? `${Number(valor).toFixed(1)} %`
                                                : cop(Number(valor))
                                        }
                                        labelFormatter={(etiqueta) => {
                                            const fila = datos.find(
                                                (d) => d.etiqueta === etiqueta,
                                            );

                                            return fila
                                                ? `${fila.procedimiento.nombre} (${fila.procedimiento.codigo_cups}) · n=${fila.n}`
                                                : String(etiqueta);
                                        }}
                                    />
                                    <Legend />
                                    {COMPONENTES.map((componente) => (
                                        <Bar
                                            key={componente.clave}
                                            dataKey={componente.clave}
                                            name={componente.nombre}
                                            stackId="componentes"
                                            fill={componente.color}
                                            maxBarSize={90}
                                        />
                                    ))}
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Detalle por procedimiento
                        </CardTitle>
                        <CardDescription>
                            Entre paréntesis, el peso de cada componente sobre
                            el total
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TablaResponsive>
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 font-medium">
                                        Procedimiento
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        n
                                    </th>
                                    {COMPONENTES.map((componente) => (
                                        <th
                                            key={componente.clave}
                                            className="py-2 text-right font-medium"
                                        >
                                            {componente.nombre}
                                        </th>
                                    ))}
                                    <th className="py-2 text-right font-medium">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {por_procedimiento.map((fila) => (
                                    <tr
                                        key={fila.procedimiento.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-2">
                                            {fila.procedimiento.nombre}{' '}
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {fila.procedimiento.codigo_cups}
                                            </span>
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {fila.n}
                                        </td>
                                        {COMPONENTES.map((componente) => (
                                            <td
                                                key={componente.clave}
                                                className="py-2 text-right whitespace-nowrap tabular-nums"
                                            >
                                                {cop(fila[componente.clave])}
                                                {fila.total > 0 && (
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        (
                                                        {pct(
                                                            (fila[
                                                                componente.clave
                                                            ] ?? 0) /
                                                                fila.total,
                                                            0,
                                                        )}
                                                        )
                                                    </span>
                                                )}
                                            </td>
                                        ))}
                                        <td className="py-2 text-right font-medium tabular-nums">
                                            {cop(fila.total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </TablaResponsive>
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
