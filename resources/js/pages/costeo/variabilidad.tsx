import { Head, Link } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
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
import { cop, pct } from '@/lib/formato';
import { COLOR_POR_NIVEL, etiquetaEje, MARGEN } from '@/lib/graficas';
import type { VariabilidadProcedimiento } from '@/types/costeo';

export default function Variabilidad({
    por_procedimiento,
    periodo,
    periodoEtiqueta,
}: {
    por_procedimiento: VariabilidadProcedimiento[];
    periodo: Periodo;
    periodoEtiqueta: string;
}) {
    const datos = por_procedimiento.map((fila) => ({
        ...fila,
        etiqueta: etiquetaEje(fila.procedimiento.nombre),
        cv_pct:
            fila.coeficiente_variacion !== null
                ? fila.coeficiente_variacion * 100
                : 0,
    }));

    const sinDatos = datos.length === 0;
    const conAlta = datos.filter((f) => f.nivel_variabilidad === 'alta');

    return (
        <>
            <Head title="Variabilidad de costos" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Variabilidad"
                    descripcion="Qué tan parecidas son entre sí las cirugías del mismo procedimiento. Mucha variación significa que el protocolo no se está siguiendo igual todas las veces."
                />

                <SelectorPeriodo
                    url="/costeo/variabilidad"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                />

                {conAlta.length > 0 && (
                    <div className="rounded-lg border border-amber-300/70 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        {conAlta.length === 1
                            ? `«${conAlta[0].procedimiento.nombre}» tiene variabilidad alta: es el primer candidato a estandarizar.`
                            : `${conAlta.length} procedimientos tienen variabilidad alta: son los primeros candidatos a estandarizar.`}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Coeficiente de variación por procedimiento
                        </CardTitle>
                        <CardDescription>
                            CV = desviación estándar ÷ media del costo total.
                            Umbrales: alta &gt; 30 %, media &gt; 15 %, baja ≤ 15
                            % (líneas punteadas).
                        </CardDescription>
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
                                    <XAxis
                                        dataKey="etiqueta"
                                        fontSize={11}
                                        interval={0}
                                        height={48}
                                        tickMargin={10}
                                    />
                                    <YAxis
                                        tickFormatter={(v: number) =>
                                            `${v.toFixed(0)} %`
                                        }
                                        fontSize={11}
                                        width={50}
                                    />
                                    <Tooltip
                                        formatter={(valor) =>
                                            `${Number(valor).toFixed(1)} %`
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
                                    {/* Los umbrales estaban sin rotular: había
                                        que leer la descripción para saber qué
                                        marcaba cada línea. */}
                                    <ReferenceLine
                                        y={30}
                                        stroke={COLOR_POR_NIVEL.alta}
                                        strokeDasharray="6 4"
                                        label={{
                                            value: 'alta · 30 %',
                                            position: 'right',
                                            fontSize: 10,
                                            fill: COLOR_POR_NIVEL.alta,
                                        }}
                                    />
                                    <ReferenceLine
                                        y={15}
                                        stroke={COLOR_POR_NIVEL.media}
                                        strokeDasharray="6 4"
                                        label={{
                                            value: 'media · 15 %',
                                            position: 'right',
                                            fontSize: 10,
                                            fill: COLOR_POR_NIVEL.media,
                                        }}
                                    />
                                    <Bar
                                        dataKey="cv_pct"
                                        name="CV"
                                        maxBarSize={80}
                                    >
                                        <LabelList
                                            dataKey="cv_pct"
                                            position="top"
                                            fontSize={11}
                                            formatter={(v) =>
                                                `${Number(v).toFixed(0)} %`
                                            }
                                        />
                                        {datos.map((fila) => (
                                            <Cell
                                                key={fila.procedimiento.id}
                                                fill={
                                                    COLOR_POR_NIVEL[
                                                        fila.nivel_variabilidad ??
                                                            'baja'
                                                    ]
                                                }
                                            />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Detalle estadístico
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
                                        n
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Media
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Desviación
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        CV
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Nivel
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
                                            <Link
                                                href={`/costeo/procedimientos/${fila.procedimiento.id}`}
                                                className="hover:underline"
                                            >
                                                {fila.procedimiento.nombre}
                                            </Link>{' '}
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {fila.procedimiento.codigo_cups}
                                            </span>
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {fila.n}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {cop(fila.media)}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {cop(fila.desviacion)}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {pct(fila.coeficiente_variacion)}
                                        </td>
                                        <td className="py-2 text-right">
                                            {fila.nivel_variabilidad ===
                                                'alta' && (
                                                <Badge variant="destructive">
                                                    alta
                                                </Badge>
                                            )}
                                            {fila.nivel_variabilidad ===
                                                'media' && (
                                                <Badge className="bg-[#A47E53] text-white hover:bg-[#A47E53]">
                                                    media
                                                </Badge>
                                            )}
                                            {fila.nivel_variabilidad ===
                                                'baja' && (
                                                <Badge className="bg-[#4C837C] text-white hover:bg-[#4C837C]">
                                                    baja
                                                </Badge>
                                            )}
                                            {fila.nivel_variabilidad ===
                                                null && (
                                                <Badge variant="secondary">
                                                    n/d
                                                </Badge>
                                            )}
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

Variabilidad.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Variabilidad', href: '/costeo/variabilidad' },
    ],
};
