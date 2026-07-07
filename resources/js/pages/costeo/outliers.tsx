import { Head } from '@inertiajs/react';
import {
    CartesianGrid,
    ReferenceLine,
    ResponsiveContainer,
    Scatter,
    ScatterChart,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cop, pct } from '@/lib/formato';
import type { GrupoOutliers } from '@/types/costeo';

export default function Outliers({ grupos }: { grupos: GrupoOutliers[] }) {
    return (
        <>
            <Head title="Outliers de costo" />
            <div className="flex flex-col gap-4 p-4">
                <p className="text-sm text-muted-foreground">
                    Detección combinada por z-score (|z| &gt; 3) y rango intercuartílico (1,5 × IQR)
                    sobre el costo total de cada procedimiento. La línea punteada marca el límite
                    superior IQR; la línea sólida, la media.
                </p>

                {grupos.map((grupo) => {
                    const puntos = grupo.puntos.map((punto, indice) => ({
                        ...punto,
                        indice: indice + 1,
                    }));
                    const normales = puntos.filter((p) => !p.es_outlier);
                    const atipicos = puntos.filter((p) => p.es_outlier);

                    return (
                        <Card key={grupo.procedimiento.id}>
                            <CardHeader>
                                <div className="flex flex-wrap items-center gap-2">
                                    <CardTitle className="text-base">
                                        {grupo.procedimiento.nombre}
                                    </CardTitle>
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {grupo.procedimiento.codigo_cups}
                                    </span>
                                    {grupo.total_outliers > 0 ? (
                                        <Badge variant="destructive">
                                            {grupo.total_outliers} outlier{grupo.total_outliers > 1 ? 's' : ''}
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">sin outliers</Badge>
                                    )}
                                </div>
                                <CardDescription>
                                    n = {grupo.n} · media {cop(grupo.media)} · desviación{' '}
                                    {cop(grupo.desviacion)} · CV {pct(grupo.coeficiente_variacion)} · límite
                                    IQR superior {cop(grupo.limites.iqr_superior)}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="h-72">
                                <ResponsiveContainer width="100%" height="100%">
                                    <ScatterChart margin={{ top: 10, right: 20, bottom: 10, left: 10 }}>
                                        <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
                                        <XAxis
                                            dataKey="indice"
                                            type="number"
                                            name="Cirugía"
                                            domain={[0, puntos.length + 1]}
                                            tick={false}
                                            label={{ value: 'Cirugías', position: 'insideBottom', fontSize: 12 }}
                                        />
                                        <YAxis
                                            dataKey="costo_total"
                                            type="number"
                                            name="Costo"
                                            tickFormatter={(v: number) => cop(v)}
                                            fontSize={11}
                                            width={95}
                                        />
                                        <Tooltip
                                            cursor={{ strokeDasharray: '3 3' }}
                                            formatter={(valor, nombre) =>
                                                nombre === 'Costo' ? cop(Number(valor)) : valor
                                            }
                                        />
                                        <ReferenceLine y={grupo.media} stroke="#3b82f6" />
                                        <ReferenceLine
                                            y={grupo.limites.iqr_superior}
                                            stroke="#ef4444"
                                            strokeDasharray="6 4"
                                        />
                                        <Scatter name="Dentro de rango" data={normales} fill="#3b82f6" />
                                        <Scatter name="Outlier" data={atipicos} fill="#ef4444" />
                                    </ScatterChart>
                                </ResponsiveContainer>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </>
    );
}

Outliers.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Outliers de costo', href: '/costeo/outliers' },
    ],
};
