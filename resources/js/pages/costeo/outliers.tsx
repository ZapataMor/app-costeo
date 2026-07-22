import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { BoxplotCostos } from '@/components/costeo/boxplot-costos';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import { SelectorPeriodo } from '@/components/costeo/selector-periodo';
import { TablaResponsive } from '@/components/costeo/tabla-responsive';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, fecha, pct } from '@/lib/formato';
import type { GrupoOutliers } from '@/types/costeo';

export default function Outliers({
    grupos,
    periodo,
    periodoEtiqueta,
}: {
    grupos: GrupoOutliers[];
    periodo: Periodo;
    periodoEtiqueta: string;
}) {
    const [verMetodo, setVerMetodo] = useState(false);

    // El titular: cuántos casos atípicos hay y cuánto dinero representan por
    // encima de la media de su procedimiento. Antes había que recorrer cuatro
    // gráficas para descubrir que solo había uno.
    const atipicos = grupos.flatMap((grupo) =>
        grupo.puntos
            .filter((punto) => punto.es_outlier)
            .map((punto) => ({
                ...punto,
                procedimiento: grupo.procedimiento,
                media: grupo.media,
                exceso: punto.costo_total - grupo.media,
            })),
    );

    const ordenados = [...atipicos].sort((a, b) => b.exceso - a.exceso);
    const excesoTotal = atipicos.reduce(
        (suma, punto) => suma + Math.max(0, punto.exceso),
        0,
    );
    const totalCirugias = grupos.reduce((suma, grupo) => suma + grupo.n, 0);

    return (
        <>
            <Head title="Outliers de costo" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Outliers de costo"
                    descripcion="Cirugías cuyo costo se sale del rango habitual de su propio procedimiento."
                    accion={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setVerMetodo(!verMetodo)}
                            aria-expanded={verMetodo}
                        >
                            {verMetodo ? 'Ocultar' : 'Cómo se detectan'}
                        </Button>
                    }
                />

                {verMetodo && (
                    <p className="max-w-[80ch] rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
                        Se combinan dos criterios sobre el costo total de cada
                        procedimiento: <strong>z-score</strong> (|z| &gt; 3,
                        cuánto se aleja de la media en desviaciones estándar) y{' '}
                        <strong>Tukey</strong> (fuera de 1,5 × el rango
                        intercuartílico). Basta con que uno de los dos marque la
                        cirugía. El diagrama de caja muestra la mitad central de
                        los casos; los puntos rojos son los atípicos.
                    </p>
                )}

                <SelectorPeriodo
                    url="/costeo/outliers"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                />

                <div className="grid gap-[18px] md:grid-cols-3">
                    <KpiCard
                        titulo="Cirugías atípicas"
                        valor={String(atipicos.length)}
                        detalle={`de ${totalCirugias} cirugías costeadas`}
                    />
                    <KpiCard
                        titulo="Exceso sobre el promedio"
                        valor={cop(excesoTotal)}
                        detalle="suma de lo que costaron de más"
                    />
                    <KpiCard
                        titulo="Procedimientos afectados"
                        valor={String(
                            grupos.filter((g) => g.total_outliers > 0).length,
                        )}
                        detalle={`de ${grupos.length} con datos suficientes`}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Dispersión de costos por procedimiento
                        </CardTitle>
                        <CardDescription>
                            Todos los procedimientos en la misma escala, para
                            comparar de un vistazo cuál es más disperso
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <BoxplotCostos grupos={grupos} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Cirugías atípicas
                        </CardTitle>
                        <CardDescription>
                            De mayor a menor exceso sobre el promedio de su
                            procedimiento
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {ordenados.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Ninguna cirugía se sale del rango en este
                                periodo.
                            </p>
                        ) : (
                            <TablaResponsive>
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2 font-medium">
                                            Fecha
                                        </th>
                                        <th className="py-2 font-medium">
                                            Procedimiento
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Costo
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Promedio
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Exceso
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Criterio
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ordenados.map((punto) => (
                                        <tr
                                            key={punto.cirugia_id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2 whitespace-nowrap">
                                                <Link
                                                    href={`/costeo/procedimientos/${punto.procedimiento.id}/cirugias/${punto.cirugia_id}`}
                                                    className="hover:underline"
                                                >
                                                    {fecha(punto.fecha)}
                                                </Link>
                                            </td>
                                            <td className="py-2">
                                                {punto.procedimiento.nombre}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(punto.costo_total)}
                                            </td>
                                            <td className="py-2 text-right text-muted-foreground tabular-nums">
                                                {cop(punto.media)}
                                            </td>
                                            <td className="py-2 text-right font-medium tabular-nums">
                                                {cop(punto.exceso)}
                                                <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                    (
                                                    {pct(
                                                        punto.media > 0
                                                            ? punto.exceso /
                                                                  punto.media
                                                            : null,
                                                        0,
                                                    )}
                                                    )
                                                </span>
                                            </td>
                                            <td className="py-2 text-right">
                                                {punto.criterios.map(
                                                    (criterio) => (
                                                        <Badge
                                                            key={criterio}
                                                            variant="outline"
                                                            className="ml-1 uppercase"
                                                        >
                                                            {criterio}
                                                        </Badge>
                                                    ),
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </TablaResponsive>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Estadística por procedimiento
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
                                        Mediana
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        CV
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Límite superior
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Atípicos
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {grupos.map((grupo) => (
                                    <tr
                                        key={grupo.procedimiento.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-2">
                                            <Link
                                                href={`/costeo/procedimientos/${grupo.procedimiento.id}`}
                                                className="hover:underline"
                                            >
                                                {grupo.procedimiento.nombre}
                                            </Link>{' '}
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {
                                                    grupo.procedimiento
                                                        .codigo_cups
                                                }
                                            </span>
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {grupo.n}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {cop(grupo.media)}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {cop(grupo.caja.mediana)}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {pct(grupo.coeficiente_variacion)}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {cop(grupo.limites.iqr_superior)}
                                        </td>
                                        <td className="py-2 text-right">
                                            {grupo.total_outliers > 0 ? (
                                                <Badge variant="destructive">
                                                    {grupo.total_outliers}
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    0
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

Outliers.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Outliers de costo', href: '/costeo/outliers' },
    ],
};
