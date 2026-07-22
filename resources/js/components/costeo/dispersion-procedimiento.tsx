import { router } from '@inertiajs/react';
import {
    CartesianGrid,
    Cell,
    ReferenceArea,
    ReferenceLine,
    ResponsiveContainer,
    Scatter,
    ScatterChart,
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
import { cop, copCorto, fecha, minutos } from '@/lib/formato';
import { COLOR, MARGEN } from '@/lib/graficas';
import type { PuntoSerieProcedimiento } from '@/types/costeo';

/**
 * Costo de cada cirugía del procedimiento a lo largo del tiempo.
 *
 * La ficha del procedimiento mostraba una tabla con costos que iban de
 * $400.000 a $815.000 —el doble— sin un solo elemento visual: había que leer
 * diez números y compararlos de memoria. Aquí se ve la dispersión, la
 * tendencia y qué caso concreto se salió.
 */
export function DispersionProcedimiento({
    serie,
    procedimientoId,
    promedio,
}: {
    serie: PuntoSerieProcedimiento[];
    procedimientoId: number;
    promedio: number | null;
}) {
    if (serie.length < 2) {
        return null;
    }

    const datos = serie.map((punto, indice) => ({
        ...punto,
        // El eje es el orden cronológico, no la fecha: dos cirugías del mismo
        // día se superpondrían en un eje temporal.
        orden: indice + 1,
    }));

    const costos = datos.map((d) => d.costo_total);
    const media = promedio ?? costos.reduce((a, b) => a + b, 0) / costos.length;

    // Banda de ±15 %: lo que se considera comportamiento normal del protocolo.
    const bandaInferior = media * 0.85;
    const bandaSuperior = media * 1.15;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    Costo de cada cirugía
                </CardTitle>
                <CardDescription>
                    En orden cronológico. La línea es el promedio y la banda, el
                    ±15 % alrededor de él. Haga clic en un punto para abrir esa
                    cirugía.
                </CardDescription>
            </CardHeader>
            <CardContent className="h-72">
                <ResponsiveContainer width="100%" height="100%">
                    <ScatterChart margin={MARGEN}>
                        <CartesianGrid
                            strokeDasharray="3 3"
                            className="opacity-30"
                        />
                        <XAxis
                            dataKey="orden"
                            type="number"
                            name="Cirugía"
                            domain={[0, datos.length + 1]}
                            tick={false}
                            height={28}
                            label={{
                                value: 'Cirugías, de la más antigua a la más reciente',
                                position: 'insideBottom',
                                fontSize: 11,
                            }}
                        />
                        <YAxis
                            dataKey="costo_total"
                            type="number"
                            name="Costo"
                            tickFormatter={(v: number) => copCorto(v)}
                            fontSize={11}
                            width={64}
                            domain={['dataMin - 50000', 'dataMax + 50000']}
                        />

                        <ReferenceArea
                            y1={bandaInferior}
                            y2={bandaSuperior}
                            fill={COLOR.neutro}
                            fillOpacity={0.08}
                        />
                        <ReferenceLine
                            y={media}
                            stroke={COLOR.neutro}
                            strokeDasharray="4 4"
                        />

                        <Tooltip
                            cursor={{ strokeDasharray: '3 3' }}
                            content={({ active, payload }) => {
                                if (!active || !payload?.length) {
                                    return null;
                                }

                                const punto = payload[0]
                                    .payload as (typeof datos)[number];

                                return (
                                    <div className="recharts-default-tooltip rounded-lg border bg-popover px-3 py-2 text-xs shadow-lg">
                                        <p className="font-medium">
                                            {fecha(punto.fecha)}
                                        </p>
                                        <p className="tabular-nums">
                                            {cop(punto.costo_total)}
                                        </p>
                                        <p className="text-muted-foreground tabular-nums">
                                            {minutos(punto.duracion_minutos)} ·{' '}
                                            {punto.costo_total > media
                                                ? '+'
                                                : ''}
                                            {(
                                                ((punto.costo_total - media) /
                                                    media) *
                                                100
                                            ).toFixed(0)}{' '}
                                            % vs. promedio
                                        </p>
                                        <p className="mt-1 text-muted-foreground">
                                            Clic para abrir el detalle
                                        </p>
                                    </div>
                                );
                            }}
                        />

                        <Scatter
                            data={datos}
                            onClick={(punto) => {
                                const { cirugia_id: id } =
                                    punto as unknown as (typeof datos)[number];

                                router.visit(
                                    `/costeo/procedimientos/${procedimientoId}/cirugias/${id}`,
                                );
                            }}
                            className="cursor-pointer"
                        >
                            {datos.map((punto) => (
                                <Cell
                                    key={punto.cirugia_id}
                                    fill={
                                        punto.costo_total > bandaSuperior ||
                                        punto.costo_total < bandaInferior
                                            ? COLOR.alerta
                                            : COLOR.neutro
                                    }
                                />
                            ))}
                        </Scatter>
                    </ScatterChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
