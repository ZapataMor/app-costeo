import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookPlus, Globe, MessageSquarePlus } from 'lucide-react';
import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ClasificacionBadge } from '@/components/conocimiento/clasificacion-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, copCorto, fecha, mesCorto, numero, pct } from '@/lib/formato';
import { COLOR, MARGEN, etiquetaEje } from '@/lib/graficas';
import type {
    AnalisisPracticas,
    LeccionBreve,
    PerfilGrupo,
} from '@/types/conocimiento';

type Procedimiento = {
    id: number;
    codigo_cups: string;
    nombre: string;
    especialidad: string;
    complejidad: string;
    duracion_estimada_minutos: number;
};

const FILAS: [keyof PerfilGrupo, string, 'cop' | 'min' | 'dias' | 'pct'][] = [
    ['costo_total', 'Costo total', 'cop'],
    ['recurso_humano', 'Talento humano', 'cop'],
    ['sala', 'Sala', 'cop'],
    ['equipos', 'Equipos', 'cop'],
    ['insumos', 'Insumos', 'cop'],
    ['indirecto', 'Indirecto', 'cop'],
    ['duracion', 'Tiempo de sala', 'min'],
    ['estancia', 'Estancia', 'dias'],
    ['tasa_complicaciones', 'Complicaciones', 'pct'],
];

function formatear(valor: number | null, tipo: 'cop' | 'min' | 'dias' | 'pct') {
    if (valor === null) {
        return '—';
    }

    return {
        cop: () => cop(valor),
        min: () => `${numero(Math.round(valor))} min`,
        dias: () => `${valor.toLocaleString('es-CO')} días`,
        pct: () => pct(valor, 1),
    }[tipo]();
}

export default function DetalleMejoresPracticas({
    procedimiento,
    analisis,
    lecciones,
    red,
    puedeVerCostos,
}: {
    procedimiento: Procedimiento;
    analisis: AnalisisPracticas;
    lecciones: LeccionBreve[];
    red: LeccionBreve[];
    puedeVerCostos: boolean;
}) {
    return (
        <>
            <Head title={`Mejores prácticas · ${procedimiento.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-2">
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            aria-label="Volver"
                        >
                            <Link href="/conocimiento/mejores-practicas">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-semibold">
                                {etiquetaEje(procedimiento.nombre, 80)}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                CUPS {procedimiento.codigo_cups} ·{' '}
                                {procedimiento.especialidad} · complejidad{' '}
                                {procedimiento.complejidad} · duración estimada{' '}
                                {procedimiento.duracion_estimada_minutos} min
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={`/conocimiento/comunidades?enlazable_tipo=procedimiento&enlazable_id=${procedimiento.id}`}
                            >
                                <MessageSquarePlus className="size-4" />
                                Discutir
                            </Link>
                        </Button>
                        <Button asChild size="sm">
                            <Link href="/conocimiento/lecciones/create">
                                <BookPlus className="size-4" />
                                Escribir una lección
                            </Link>
                        </Button>
                    </div>
                </div>

                {!analisis.suficiente ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            Hay {analisis.n} cirugías costeadas; se necesitan al
                            menos {analisis.minimo} para comparar grupos con
                            sentido.
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Mejor desempeño frente al resto
                                    </CardTitle>
                                    <CardDescription>
                                        «Mejor desempeño»: el cuartil de menor
                                        costo sin complicaciones y con estancia
                                        no mayor a la mediana (
                                        {analisis.grupos.mejores.n} de{' '}
                                        {analisis.n} casos,{' '}
                                        {fecha(analisis.desde)} a{' '}
                                        {fecha(analisis.hasta)}).
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="overflow-x-auto p-0">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="p-3 font-medium" />
                                                <th className="p-3 text-right font-medium">
                                                    Mejor desempeño
                                                </th>
                                                <th className="p-3 text-right font-medium">
                                                    Resto
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {FILAS.filter(
                                                ([, , tipo]) =>
                                                    puedeVerCostos ||
                                                    tipo !== 'cop',
                                            ).map(([clave, nombre, tipo]) => (
                                                <tr
                                                    key={clave}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="p-3">
                                                        {nombre}
                                                    </td>
                                                    <td className="p-3 text-right font-medium tabular-nums">
                                                        {formatear(
                                                            analisis.grupos
                                                                .mejores[clave],
                                                            tipo,
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-right text-muted-foreground tabular-nums">
                                                        {formatear(
                                                            analisis.grupos
                                                                .resto[clave],
                                                            tipo,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Dónde está la diferencia en insumos
                                    </CardTitle>
                                    <CardDescription>
                                        Insumos que el grupo de mejor desempeño
                                        usa en menor cantidad, por cirugía.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="overflow-x-auto p-0">
                                    {analisis.insumos.length === 0 ? (
                                        <p className="p-4 text-sm text-muted-foreground">
                                            Sin diferencias de consumo
                                            relevantes.
                                        </p>
                                    ) : (
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-left text-muted-foreground">
                                                    <th className="p-3 font-medium">
                                                        Insumo
                                                    </th>
                                                    <th className="p-3 text-right font-medium">
                                                        Mejores
                                                    </th>
                                                    <th className="p-3 text-right font-medium">
                                                        Resto
                                                    </th>
                                                    {puedeVerCostos && (
                                                        <th className="p-3 text-right font-medium">
                                                            $/cirugía
                                                        </th>
                                                    )}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {analisis.insumos.map((i) => (
                                                    <tr
                                                        key={i.insumo_id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="p-3">
                                                            {i.insumo}
                                                        </td>
                                                        <td className="p-3 text-right tabular-nums">
                                                            {i.cantidad_mejores}
                                                        </td>
                                                        <td className="p-3 text-right tabular-nums">
                                                            {i.cantidad_resto}
                                                        </td>
                                                        {puedeVerCostos && (
                                                            <td className="p-3 text-right tabular-nums">
                                                                {cop(
                                                                    i.diferencia_por_cirugia,
                                                                )}
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Profesionales (anónimo)
                                    </CardTitle>
                                    <CardDescription>
                                        Cirujanos con al menos 3 casos. Índice
                                        1,00 = promedio del procedimiento. El
                                        fin es aprender de los pares, no
                                        señalar.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="overflow-x-auto p-0">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="p-3 font-medium" />
                                                <th className="p-3 text-right font-medium">
                                                    Casos
                                                </th>
                                                <th className="p-3 text-right font-medium">
                                                    Índice costo
                                                </th>
                                                <th className="p-3 text-right font-medium">
                                                    Índice tiempo
                                                </th>
                                                <th className="p-3 text-right font-medium">
                                                    Complicaciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {analisis.profesionales.map((p) => (
                                                <tr
                                                    key={p.etiqueta}
                                                    className={`border-b last:border-0 ${p.es_propio ? 'bg-sky-50 font-medium dark:bg-sky-950' : ''}`}
                                                >
                                                    <td className="p-3">
                                                        {p.etiqueta}
                                                        {p.es_propio && (
                                                            <Badge
                                                                variant="outline"
                                                                className="ml-2"
                                                            >
                                                                Usted
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-right tabular-nums">
                                                        {p.n}
                                                    </td>
                                                    <td className="p-3 text-right tabular-nums">
                                                        {p.indice_costo?.toFixed(
                                                            2,
                                                        ) ?? '—'}
                                                    </td>
                                                    <td className="p-3 text-right tabular-nums">
                                                        {p.indice_duracion?.toFixed(
                                                            2,
                                                        ) ?? '—'}
                                                    </td>
                                                    <td className="p-3 text-right tabular-nums">
                                                        {pct(
                                                            p.tasa_complicaciones,
                                                            0,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Evolución y cumplimiento del estándar
                                    </CardTitle>
                                    <CardDescription className="flex flex-wrap gap-2">
                                        {analisis.clasificacion.map((c) => (
                                            <span
                                                key={c.clasificacion}
                                                className="flex items-center gap-1"
                                            >
                                                <ClasificacionBadge
                                                    clasificacion={
                                                        c.clasificacion
                                                    }
                                                />
                                                {c.n}
                                            </span>
                                        ))}
                                    </CardDescription>
                                </CardHeader>
                                {puedeVerCostos && (
                                    <CardContent className="h-56">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <LineChart
                                                data={analisis.tendencia.map(
                                                    (t) => ({
                                                        ...t,
                                                        eje: mesCorto(t.mes),
                                                    }),
                                                )}
                                                margin={MARGEN}
                                            >
                                                <CartesianGrid
                                                    strokeDasharray="3 3"
                                                    className="opacity-30"
                                                    vertical={false}
                                                />
                                                <XAxis
                                                    dataKey="eje"
                                                    fontSize={11}
                                                />
                                                <YAxis
                                                    tickFormatter={(
                                                        v: number,
                                                    ) => copCorto(v)}
                                                    fontSize={11}
                                                    width={64}
                                                />
                                                <Tooltip
                                                    formatter={(v) =>
                                                        cop(Number(v))
                                                    }
                                                />
                                                <Line
                                                    type="monotone"
                                                    dataKey="costo_promedio"
                                                    name="Costo promedio"
                                                    stroke={COLOR.costo}
                                                    strokeWidth={2}
                                                />
                                            </LineChart>
                                        </ResponsiveContainer>
                                    </CardContent>
                                )}
                            </Card>
                        </div>
                    </>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <ListaLecciones
                        titulo="Lecciones del hospital"
                        vacio="Aún no hay lecciones publicadas para este procedimiento."
                        lecciones={lecciones}
                    />
                    <ListaLecciones
                        titulo="De la red de hospitales"
                        vacio="Ningún otro hospital ha compartido lecciones de este procedimiento o especialidad."
                        lecciones={red}
                    />
                </div>
            </div>
        </>
    );
}

function ListaLecciones({
    titulo,
    vacio,
    lecciones,
}: {
    titulo: string;
    vacio: string;
    lecciones: LeccionBreve[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{titulo}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {lecciones.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{vacio}</p>
                ) : (
                    lecciones.map((l) => (
                        <Link
                            key={l.id}
                            href={
                                l.hospital
                                    ? `/conocimiento/lecciones/red/${l.id}`
                                    : `/conocimiento/lecciones/${l.id}`
                            }
                            className="block rounded-md p-2 hover:bg-muted"
                        >
                            <p className="flex items-center gap-2 text-sm font-medium">
                                {l.hospital && <Globe className="size-3.5" />}
                                {l.titulo}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {l.tipo_etiqueta}
                                {l.hospital && ` · ${l.hospital}`}
                                {l.publicada_en &&
                                    ` · ${fecha(l.publicada_en)}`}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {l.extracto}
                            </p>
                        </Link>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

DetalleMejoresPracticas.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento/lecciones' },
        { title: 'Mejores prácticas', href: '/conocimiento/mejores-practicas' },
    ],
};
