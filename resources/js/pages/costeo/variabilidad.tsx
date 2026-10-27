import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { BoxplotCostos } from '@/components/costeo/boxplot-costos';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import {
    consultaDePeriodo,
    SelectorPeriodo,
} from '@/components/costeo/selector-periodo';
import { TablaResponsive } from '@/components/costeo/tabla-responsive';
import { NombreDato } from '@/components/nombre-dato';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { CRITERIO_ATIPICO, cop, fecha, numero, pct } from '@/lib/formato';
import type { Tono } from '@/lib/semaforo';
import { TEXTO_TONO } from '@/lib/semaforo';
import type { GrupoOutliers } from '@/types/costeo';

/** Procedimientos del diagrama de caja antes de «ver todos». */
const CAJAS_VISIBLES = 10;

const TONO_VARIABILIDAD: Record<string, Tono> = {
    alta: 'alerta',
    media: 'atencion',
    baja: 'bien',
};

/**
 * Variabilidad y atípicos: ¿se opera siempre igual?
 *
 * Eran dos pestañas —«Variabilidad» y «Costos fuera de lo normal»— con la
 * misma tabla de media, desviación y CV por procedimiento. Ahora es una:
 * primero el procedimiento (cuánto varía su costo), después la cirugía (qué
 * casos se salen del rango habitual).
 */
export default function Variabilidad({
    grupos,
    periodo,
    periodoEtiqueta,
}: {
    grupos: GrupoOutliers[];
    periodo: Periodo;
    periodoEtiqueta: string;
}) {
    const [verMetodo, setVerMetodo] = useState(false);
    const [todasLasCajas, setTodasLasCajas] = useState(false);
    const consulta = consultaDePeriodo(periodo);

    const atipicos = grupos
        .flatMap((grupo) =>
            grupo.puntos
                .filter((punto) => punto.es_outlier)
                .map((punto) => ({
                    ...punto,
                    procedimiento: grupo.procedimiento,
                    media: grupo.media,
                    exceso: punto.costo_total - grupo.media,
                })),
        )
        .sort((a, b) => b.exceso - a.exceso);

    const excesoTotal = atipicos.reduce(
        (suma, punto) => suma + Math.max(0, punto.exceso),
        0,
    );
    const totalCirugias = grupos.reduce((suma, grupo) => suma + grupo.n, 0);
    const conAlta = grupos.filter((g) => g.nivel_variabilidad === 'alta');
    const cajas = todasLasCajas ? grupos : grupos.slice(0, CAJAS_VISIBLES);

    return (
        <>
            <Head title="Variabilidad y atípicos" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Variabilidad y atípicos"
                    descripcion="Qué tan parecidas son entre sí las cirugías del mismo procedimiento y cuáles se salen de lo habitual. Mucha variación significa que el protocolo no se sigue igual todas las veces."
                    accion={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setVerMetodo(!verMetodo)}
                            aria-expanded={verMetodo}
                        >
                            {verMetodo ? 'Ocultar' : 'Cómo se calcula'}
                        </Button>
                    }
                />

                {verMetodo && (
                    <div className="max-w-[80ch] space-y-2 rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
                        <p>
                            <strong>Variabilidad</strong>: coeficiente de
                            variación del costo total (desviación estándar ÷
                            media). Alta por encima de 30 %, media por encima de
                            15 %, baja de ahí para abajo.
                        </p>
                        <p>
                            <strong>Cirugía atípica</strong>: se combinan dos
                            criterios sobre el costo de cada procedimiento —a
                            más de 3 desviaciones estándar de la media (z-score)
                            o fuera de 1,5 veces el rango intercuartílico
                            (Tukey)—. Basta con uno.
                        </p>
                    </div>
                )}

                <SelectorPeriodo
                    url="/costeo/variabilidad"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                />

                <div className="grid gap-[18px] md:grid-cols-3">
                    <KpiCard
                        titulo="Procedimientos con variabilidad alta"
                        valor={numero(conAlta.length)}
                        tono={conAlta.length > 0 ? 'atencion' : 'bien'}
                        detalle={`de ${numero(grupos.length)} operados en el periodo · primeros candidatos a estandarizar`}
                    />
                    <KpiCard
                        titulo="Cirugías atípicas"
                        valor={numero(atipicos.length)}
                        tono={atipicos.length > 0 ? 'atencion' : 'bien'}
                        detalle={`de ${numero(totalCirugias)} cirugías costeadas`}
                    />
                    <KpiCard
                        titulo="Exceso de las atípicas"
                        valor={cop(excesoTotal)}
                        detalle="lo que costaron por encima del promedio de su procedimiento"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Por procedimiento
                        </CardTitle>
                        <CardDescription>
                            De más a menos variable
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {grupos.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Sin cirugías costeadas en este periodo.
                            </p>
                        ) : (
                            <TablaResponsive>
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2 font-medium">
                                            Procedimiento
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Cirugías
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Promedio
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Mediana
                                        </th>
                                        <th
                                            className="py-2 text-right font-medium"
                                            title="Coeficiente de variación"
                                        >
                                            Variabilidad
                                        </th>
                                        <th
                                            className="py-2 text-right font-medium"
                                            title="Por encima de este costo, la cirugía es atípica"
                                        >
                                            Límite habitual
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Atípicas
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
                                                    href={`/costeo/procedimientos/${grupo.procedimiento.id}${consulta}`}
                                                    className="hover:underline"
                                                >
                                                    <NombreDato
                                                        texto={
                                                            grupo.procedimiento
                                                                .nombre
                                                        }
                                                    />
                                                </Link>
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {numero(grupo.n)}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(grupo.media)}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(grupo.caja.mediana)}
                                            </td>
                                            <td
                                                className={`py-2 text-right tabular-nums ${TEXTO_TONO[TONO_VARIABILIDAD[grupo.nivel_variabilidad ?? ''] ?? 'neutro']}`}
                                            >
                                                {pct(
                                                    grupo.coeficiente_variacion,
                                                    0,
                                                )}{' '}
                                                <span className="text-xs">
                                                    {grupo.nivel_variabilidad}
                                                </span>
                                            </td>
                                            <td className="py-2 text-right text-muted-foreground tabular-nums">
                                                {cop(
                                                    grupo.limites.iqr_superior,
                                                )}
                                            </td>
                                            <td className="py-2 text-right">
                                                {grupo.total_outliers > 0 ? (
                                                    <Badge variant="destructive">
                                                        {grupo.total_outliers}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        0
                                                    </span>
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
                            Cirugías atípicas
                        </CardTitle>
                        <CardDescription>
                            De mayor a menor exceso sobre el promedio de su
                            procedimiento. Cada una merece una revisión:
                            complicación, insumo extra o error de captura.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {atipicos.length === 0 ? (
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
                                    {atipicos.map((punto) => (
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
                                                <NombreDato
                                                    texto={
                                                        punto.procedimiento
                                                            .nombre
                                                    }
                                                />
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(punto.costo_total)}
                                            </td>
                                            <td className="py-2 text-right text-muted-foreground tabular-nums">
                                                {cop(punto.media)}
                                            </td>
                                            <td
                                                className={`py-2 text-right font-medium tabular-nums ${punto.exceso > 0 ? TEXTO_TONO.alerta : ''}`}
                                            >
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
                                                            className="ml-1"
                                                            title={
                                                                CRITERIO_ATIPICO[
                                                                    criterio
                                                                ]?.tecnico
                                                            }
                                                        >
                                                            {CRITERIO_ATIPICO[
                                                                criterio
                                                            ]?.texto ??
                                                                criterio}
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

                {grupos.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Dispersión del costo
                            </CardTitle>
                            <CardDescription>
                                Todos en la misma escala: la caja es la mitad
                                central de los casos y los puntos rojos, las
                                atípicas (clic para abrirlas)
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BoxplotCostos grupos={cajas} />
                            {grupos.length > CAJAS_VISIBLES && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="mt-4"
                                    onClick={() =>
                                        setTodasLasCajas(!todasLasCajas)
                                    }
                                >
                                    {todasLasCajas
                                        ? `Ver solo los ${CAJAS_VISIBLES} más variables`
                                        : `Ver los ${grupos.length} procedimientos`}
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Variabilidad.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Variabilidad y atípicos', href: '/costeo/variabilidad' },
    ],
};
