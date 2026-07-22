import { TrendingDown, TrendingUp } from 'lucide-react';
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
import { cop, copCorto, mesCorto, numero, pctVariacion } from '@/lib/formato';
import { COLOR, COMPONENTES, MARGEN } from '@/lib/graficas';
import type { TendenciaMensual } from '@/types/costeo';

type Vista = 'promedio' | 'componentes';

/**
 * Evolución mensual del costo.
 *
 * Es la gráfica que faltaba: todos los demás paneles son una foto —cuánto
 * cuesta hoy—, y la pregunta que hace un gerente es si el número va bajando.
 * Dos vistas sobre la misma serie: el promedio con el volumen detrás, y la
 * composición por componente para ver de dónde viene el cambio.
 */
export function TendenciaCostos({
    tendencia,
}: {
    tendencia: TendenciaMensual;
}) {
    const [vista, setVista] = useState<Vista>('promedio');

    const datos = tendencia.meses.map((mes) => ({
        ...mes,
        eje: mesCorto(mes.mes),
    }));

    const sinDatos = datos.length === 0;
    const unSoloMes = datos.length === 1;

    const variacion = tendencia.variacion_ultimo_mes;
    const bajando = variacion !== null && variacion < 0;

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="text-base">
                            Evolución del costo por cirugía
                        </CardTitle>
                        <CardDescription>
                            {sinDatos
                                ? 'Sin cirugías costeadas en el periodo seleccionado.'
                                : unSoloMes
                                  ? 'Un solo mes con datos: la tendencia aparece cuando haya al menos dos.'
                                  : 'Promedio TDABC de cada mes y número de cirugías costeadas.'}
                        </CardDescription>
                    </div>

                    {!sinDatos && (
                        <div className="flex items-center gap-3">
                            {variacion !== null && (
                                <span
                                    className={`flex items-center gap-1.5 rounded-md px-2.5 py-1 text-sm font-medium tabular-nums ${
                                        bajando
                                            ? 'bg-[#4C837C]/10 text-[#4C837C]'
                                            : 'bg-[#9E3B3B]/10 text-[#9E3B3B]'
                                    }`}
                                    title="Variación del costo promedio frente al mes anterior"
                                >
                                    {bajando ? (
                                        <TrendingDown className="size-4" />
                                    ) : (
                                        <TrendingUp className="size-4" />
                                    )}
                                    {pctVariacion(variacion)}
                                    <span className="font-normal opacity-70">
                                        vs. mes anterior
                                    </span>
                                </span>
                            )}

                            <ToggleGroup
                                type="single"
                                size="sm"
                                value={vista}
                                onValueChange={(v) => v && setVista(v as Vista)}
                                variant="outline"
                            >
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
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent className="h-80">
                {sinDatos ? (
                    <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                        Registre y costee cirugías para ver aquí su evolución.
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
                            <YAxis
                                yAxisId="conteo"
                                orientation="right"
                                allowDecimals={false}
                                fontSize={11}
                                width={36}
                            />
                            <Tooltip
                                formatter={(valor, nombre) =>
                                    nombre === 'Cirugías'
                                        ? numero(Number(valor))
                                        : cop(Number(valor))
                                }
                            />
                            <Legend />

                            {/* El volumen va detrás y en el eje derecho: da
                                contexto sin competir con el costo. */}
                            <Bar
                                yAxisId="conteo"
                                dataKey="n"
                                name="Cirugías"
                                fill={COLOR.neutro}
                                fillOpacity={0.18}
                                barSize={28}
                            />

                            {vista === 'promedio' ? (
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
                            ) : (
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
                                ))
                            )}
                        </ComposedChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
