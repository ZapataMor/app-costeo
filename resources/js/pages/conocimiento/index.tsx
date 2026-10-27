import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    Lightbulb,
    MessagesSquare,
    ShieldAlert,
    Sparkles,
} from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { KpiCard } from '@/components/costeo/kpi-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, mesCorto, pct } from '@/lib/formato';
import { COLOR, MARGEN, etiquetaEje } from '@/lib/graficas';
import type { ResumenConocimiento } from '@/types/conocimiento';

const BANDAS = [
    { clave: 'subejecucion', nombre: 'Subejecución', color: '#7AA6C2' },
    { clave: 'en_rango', nombre: 'En rango', color: COLOR.bien },
    {
        clave: 'sobrecosto_leve',
        nombre: 'Sobrecosto leve',
        color: COLOR.atencion,
    },
    {
        clave: 'sobrecosto_alto',
        nombre: 'Sobrecosto alto',
        color: COLOR.alerta,
    },
] as const;

/**
 * Tablero de la Capa 4: ¿el hospital convierte sus datos en conocimiento y
 * el conocimiento en ahorro?
 */
export default function TableroConocimiento({
    resumen,
}: {
    resumen: ResumenConocimiento;
}) {
    const { lecciones, alertas, sobrecostos, recomendaciones, comunidades } =
        resumen;
    const nuevas = recomendaciones.find((r) => r.estado === 'nueva');
    const enCurso = recomendaciones.find((r) => r.estado === 'aceptada');
    const implementadas = recomendaciones.find(
        (r) => r.estado === 'implementada',
    );
    const enRevision =
        lecciones.por_estado.find((e) => e.estado === 'en_revision')?.n ?? 0;

    return (
        <>
            <Head title="Conocimiento" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Gestión del conocimiento
                        </h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Capa 4 del sistema: reglas que vigilan el histórico,
                            lecciones aprendidas, recomendaciones con ahorro
                            estimado y comunidades de práctica. La meta de la
                            tesis es pasar de 2 a {lecciones.meta_mensual}{' '}
                            lecciones aprendidas al mes.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href="/conocimiento/reglas">Reglas</Link>
                        </Button>
                        <Button asChild size="sm">
                            <Link href="/conocimiento/lecciones/create">
                                Nueva lección
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        titulo="Lecciones publicadas este mes"
                        valor={`${lecciones.publicadas_mes_actual} / ${lecciones.meta_mensual}`}
                        detalle={`${enRevision} por validar · ${lecciones.compartidas_red} compartidas con la red`}
                    />
                    <KpiCard
                        titulo="Alertas por revisar"
                        valor={String(alertas.pendientes)}
                        detalle={`${cop(alertas.impacto_pendiente)} de impacto sin explicar`}
                    />
                    <KpiCard
                        titulo="Sobrecosto evitable identificado"
                        valor={cop(sobrecostos.exceso_evitable)}
                        detalle={`${pct(sobrecostos.tasa_revision ?? 0, 0)} de las alertas de sobrecosto revisadas`}
                    />
                    <KpiCard
                        titulo="Ahorro potencial (recomendaciones)"
                        valor={`${cop(nuevas?.ahorro_mensual ?? 0)}/mes`}
                        detalle={`${cop(enCurso?.ahorro_mensual ?? 0)}/mes en implementación · ${cop(implementadas?.ahorro_mensual ?? 0)}/mes implementado`}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Lecciones publicadas por mes
                            </CardTitle>
                            <CardDescription>
                                La línea marca la meta de la tesis.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart
                                    data={lecciones.por_mes.map((m) => ({
                                        ...m,
                                        eje: mesCorto(m.mes),
                                    }))}
                                    margin={MARGEN}
                                >
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        className="opacity-30"
                                        vertical={false}
                                    />
                                    <XAxis dataKey="eje" fontSize={11} />
                                    <YAxis
                                        allowDecimals={false}
                                        fontSize={11}
                                        width={32}
                                        domain={[
                                            0,
                                            (max: number) =>
                                                Math.max(
                                                    max,
                                                    lecciones.meta_mensual,
                                                ),
                                        ]}
                                    />
                                    <Tooltip />
                                    <ReferenceLine
                                        y={lecciones.meta_mensual}
                                        stroke={COLOR.referencia}
                                        strokeDasharray="4 4"
                                        label={{
                                            value: 'Meta',
                                            fontSize: 11,
                                            position: 'insideTopRight',
                                        }}
                                    />
                                    <Bar
                                        dataKey="publicadas"
                                        name="Publicadas"
                                        fill={COLOR.neutro}
                                        radius={[3, 3, 0, 0]}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Real frente al estándar del protocolo
                            </CardTitle>
                            <CardDescription>
                                Cirugías costeadas por banda de desviación, mes
                                a mes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="h-64">
                            {resumen.desviacion.length === 0 ? (
                                <p className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                    Sin cirugías con costo estándar.
                                </p>
                            ) : (
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart
                                        data={resumen.desviacion.map((m) => ({
                                            ...m,
                                            eje: mesCorto(m.mes),
                                        }))}
                                        margin={MARGEN}
                                    >
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            className="opacity-30"
                                            vertical={false}
                                        />
                                        <XAxis dataKey="eje" fontSize={11} />
                                        <YAxis
                                            allowDecimals={false}
                                            fontSize={11}
                                            width={36}
                                        />
                                        <Tooltip />
                                        <Legend />
                                        {BANDAS.map((b) => (
                                            <Bar
                                                key={b.clave}
                                                dataKey={b.clave}
                                                name={b.nombre}
                                                stackId="bandas"
                                                fill={b.color}
                                            />
                                        ))}
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Alertas de las reglas
                            </CardTitle>
                            <CardDescription>
                                Pendientes por tipo y el impacto que todavía
                                nadie ha explicado.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2 font-medium">
                                            Regla
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Pendientes
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Revisadas
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Impacto pendiente
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {alertas.por_tipo.map((fila) => (
                                        <tr
                                            key={fila.tipo}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2">
                                                <Link
                                                    href={`/conocimiento/alertas?tipo=${fila.tipo}`}
                                                    className="hover:underline"
                                                >
                                                    {fila.nombre}
                                                </Link>
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {fila.pendientes}
                                            </td>
                                            <td className="py-2 text-right text-muted-foreground tabular-nums">
                                                {fila.revisadas}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(fila.impacto_pendiente)}
                                            </td>
                                        </tr>
                                    ))}
                                    {alertas.por_tipo.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="py-6 text-center text-muted-foreground"
                                            >
                                                Las reglas aún no se han
                                                evaluado.{' '}
                                                <Link
                                                    href="/conocimiento/reglas"
                                                    className="underline"
                                                >
                                                    Evaluar ahora
                                                </Link>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Dónde se concentra el sobrecosto
                            </CardTitle>
                            <CardDescription>
                                Exceso acumulado sobre el estándar.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {resumen.procedimientos_criticos.map((p) => (
                                <Link
                                    key={p.id}
                                    href={`/conocimiento/mejores-practicas/${p.id}`}
                                    className="flex items-center justify-between gap-2 rounded-md p-1.5 text-sm hover:bg-muted"
                                >
                                    <span className="truncate">
                                        {etiquetaEje(p.nombre, 40)}
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            ({p.n})
                                        </span>
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        {cop(p.exceso)}
                                    </span>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Causas del sobrecosto revisado
                            </CardTitle>
                            <CardDescription>
                                Evitable {cop(sobrecostos.exceso_evitable)} · no
                                evitable {cop(sobrecostos.exceso_no_evitable)}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-1.5">
                            {sobrecostos.por_causa.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Ninguna alerta de sobrecosto revisada
                                    todavía.
                                </p>
                            ) : (
                                sobrecostos.por_causa.map((c) => (
                                    <div
                                        key={c.causa}
                                        className="flex items-center justify-between gap-2 text-sm"
                                    >
                                        <span>
                                            {c.etiqueta}{' '}
                                            {c.evitable && (
                                                <Badge
                                                    variant="destructive"
                                                    className="ml-1"
                                                >
                                                    evitable
                                                </Badge>
                                            )}
                                        </span>
                                        <span className="tabular-nums">
                                            {c.n} · {cop(c.exceso)}
                                        </span>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <Acceso
                            href="/alertas"
                            icono={ShieldAlert}
                            titulo="Alertas"
                            detalle={`${alertas.pendientes} pendientes`}
                        />
                        <Acceso
                            href="/conocimiento/lecciones"
                            icono={BookOpen}
                            titulo="Lecciones aprendidas"
                            detalle={`${enRevision} por validar · ${lecciones.de_la_red} de la red`}
                        />
                        <Acceso
                            href="/conocimiento/recomendaciones"
                            icono={Lightbulb}
                            titulo="Recomendaciones"
                            detalle={`${nuevas?.n ?? 0} nuevas · ${enCurso?.n ?? 0} en curso`}
                        />
                        <Acceso
                            href="/conocimiento/comunidades"
                            icono={MessagesSquare}
                            titulo="Comunidades de práctica"
                            detalle={`${comunidades.hilos_activos} hilos activos · ${comunidades.participantes} participantes`}
                        />
                        <Acceso
                            href="/conocimiento/mejores-practicas"
                            icono={Sparkles}
                            titulo="Mejores prácticas"
                            detalle="Lo que hacen distinto los casos de menor costo y buen resultado"
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

function Acceso({
    href,
    icono: Icono,
    titulo,
    detalle,
}: {
    href: string;
    icono: typeof BookOpen;
    titulo: string;
    detalle: string;
}) {
    return (
        <Link
            href={href}
            className="flex gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-muted/50"
        >
            <Icono className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
            <div>
                <p className="text-sm font-medium">{titulo}</p>
                <p className="text-xs text-muted-foreground">{detalle}</p>
            </div>
        </Link>
    );
}

TableroConocimiento.layout = {
    breadcrumbs: [{ title: 'Conocimiento', href: '/conocimiento' }],
};
