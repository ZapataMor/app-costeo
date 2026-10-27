import { useState } from 'react';
import {
    Area,
    Bar,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cop, copCorto, mesCorto, numero, pct } from '@/lib/formato';
import { COLOR, COMPONENTES, MARGEN } from '@/lib/graficas';
import type { TendenciaMensual } from '@/types/costeo';

type Vista = 'resultado' | 'promedio' | 'componentes';

const DESCRIPCION: Record<Vista, string> = {
    resultado:
        'Lo facturado frente al costo real de las mismas cirugías, mes a mes, y el margen resultante.',
    promedio:
        'Costo promedio por cirugía y número de cirugías costeadas. Ojo: sube o baja también con la mezcla de procedimientos del mes.',
    componentes:
        'Costo promedio por cirugía separado por componente, para ver de dónde viene un cambio.',
};

/**
 * Evolución mensual.
 *
 * La vista principal es ahora el resultado (facturado frente a costo): el
 * costo promedio solo, con su «▲ +23,5 % vs. mes anterior» en rojo, se leía
 * como un empeoramiento cuando a menudo era un mes con más procedimientos
 * caros. Esa variación a igual mezcla está en la tarjeta del Resumen.
 */
export function TendenciaCostos({
    tendencia,
}: {
    tendencia: TendenciaMensual;
}) {
    const [vista, setVista] = useState<Vista>('resultado');

    const datos = tendencia.meses.map((mes) => ({
        ...mes,
        eje: mesCorto(mes.mes),
    }));

    const sinDatos = datos.length === 0;
    const unSoloMes = datos.length === 1;
    const sinFacturas = datos.every((mes) => mes.n_facturadas === 0);

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="text-base">
                            Evolución mensual
                        </CardTitle>
                        <CardDescription className="max-w-[70ch]">
                            {sinDatos
                                ? 'Sin cirugías costeadas en el periodo seleccionado.'
                                : unSoloMes
                                  ? 'Un solo mes con datos: la tendencia aparece cuando haya al menos dos.'
                                  : DESCRIPCION[vista]}
                        </CardDescription>
                    </div>

                    {!sinDatos && (
                        <ToggleGroup
                            type="single"
                            size="sm"
                            value={vista}
                            onValueChange={(v) => v && setVista(v as Vista)}
                            variant="outline"
                        >
                            <ToggleGroupItem
                                value="resultado"
                                aria-label="Ver facturado frente a costo"
                            >
                                Resultado
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="promedio"
                                aria-label="Ver el costo promedio"
                            >
                                Promedio
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="componentes"
                                aria-label="Ver la composición del costo"
                            >
                                Componentes
                            </ToggleGroupItem>
                        </ToggleGroup>
                    )}
                </div>
            </CardHeader>
            <CardContent className="h-80">
                {sinDatos ? (
                    <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                        Registre y costee cirugías para ver aquí su evolución.
                    </div>
                ) : vista === 'resultado' && sinFacturas ? (
                    <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                        Ninguna cirugía del periodo tiene factura registrada
                        todavía.
                    </div>
                ) : (
                    <ResponsiveContainer width="100%" height="100%">
                        <ComposedChart data={datos} margin={MARGEN}>
                            <CartesianGrid
                                strokeDasharray="3 3"
                                className="opacity-30"
                                vertical={false}
                            />
                            <XAxis dataKey="eje" fontSize={11} />
                            <YAxis
                                yAxisId="pesos"
                                tickFormatter={(v: number) => copCorto(v)}
                                fontSize={11}
                                width={64}
                            />
                            {vista === 'resultado' ? (
                                <YAxis
                                    yAxisId="porcentaje"
                                    orientation="right"
                                    tickFormatter={(v: number) => pct(v, 0)}
                                    fontSize={11}
                                    width={48}
                                />
                            ) : (
                                <YAxis
                                    yAxisId="conteo"
                                    orientation="right"
                                    allowDecimals={false}
                                    fontSize={11}
                                    width={36}
                                />
                            )}
                            <Tooltip
                                formatter={(valor, nombre) => {
                                    if (nombre === 'Cirugías') {
                                        return numero(Number(valor));
                                    }

                                    if (nombre === 'Margen') {
                                        return pct(Number(valor));
                                    }

                                    return cop(Number(valor));
                                }}
                            />
                            <Legend />

                            {vista === 'resultado' && [
                                <Bar
                                    key="facturado"
                                    yAxisId="pesos"
                                    dataKey="facturado"
                                    name="Facturado"
                                    fill={COLOR.tarifa}
                                    radius={[3, 3, 0, 0]}
                                    maxBarSize={36}
                                />,
                                <Bar
                                    key="costo"
                                    yAxisId="pesos"
                                    dataKey="costo_facturadas"
                                    name="Costo real"
                                    fill={COLOR.costo}
                                    radius={[3, 3, 0, 0]}
                                    maxBarSize={36}
                                />,
                                <Line
                                    key="margen"
                                    yAxisId="porcentaje"
                                    type="monotone"
                                    dataKey="margen_pct"
                                    name="Margen"
                                    stroke={COLOR.bien}
                                    strokeWidth={2.5}
                                    dot={{ r: 3 }}
                                    connectNulls
                                />,
                            ]}

                            {vista !== 'resultado' && (
                                <Bar
                                    yAxisId="conteo"
                                    dataKey="n"
                                    name="Cirugías"
                                    fill={COLOR.neutro}
                                    fillOpacity={0.18}
                                    barSize={28}
                                />
                            )}

                            {vista === 'promedio' && (
                                <Line
                                    yAxisId="pesos"
                                    type="monotone"
                                    dataKey="costo_promedio"
                                    name="Costo promedio"
                                    stroke={COLOR.costo}
                                    strokeWidth={2.5}
                                    dot={{ r: 3 }}
                                    activeDot={{ r: 5 }}
                                />
                            )}

                            {vista === 'componentes' &&
                                COMPONENTES.map((componente) => (
                                    <Area
                                        key={componente.clave}
                                        yAxisId="pesos"
                                        type="monotone"
                                        dataKey={componente.clave}
                                        name={componente.nombre}
                                        stackId="componentes"
                                        stroke={componente.color}
                                        fill={componente.color}
                                        fillOpacity={0.75}
                                    />
                                ))}
                        </ComposedChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
