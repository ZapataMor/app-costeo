import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    BellRing,
    ChartColumnBig,
    ClipboardList,
    TrendingUp,
    Users,
    Waves,
} from 'lucide-react';
import { BotonExportar } from '@/components/boton-exportar';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import { SelectorPeriodo } from '@/components/costeo/selector-periodo';
import { TablaResponsive } from '@/components/costeo/tabla-responsive';
import { TendenciaCostos } from '@/components/costeo/tendencia-costos';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, minutos, numero, pct } from '@/lib/formato';
import type {
    Completitud,
    CostosKpi,
    GlosasRecaudo,
    TendenciaMensual,
    UtilizacionSalas,
} from '@/types/costeo';

const etiquetasChequeos: Record<string, string> = {
    equipo_quirurgico: 'Equipo quirúrgico',
    consumo_insumos: 'Consumo de insumos',
    costo_calculado: 'Costo calculado',
    resultado_clinico: 'Resultado clínico',
    facturacion: 'Facturación',
};

const paneles = [
    {
        titulo: 'Alertas de sobrecosto',
        descripcion:
            'Cirugías que se salieron del rango de su procedimiento, con el exceso ya desglosado y pendiente de causa.',
        href: '/costeo/alertas',
        icono: BellRing,
    },
    {
        titulo: 'Procedimientos',
        descripcion:
            'Explora cada procedimiento, sus cirugías realizadas y el costo detallado de cada una.',
        href: '/costeo/procedimientos',
        icono: ClipboardList,
    },
    {
        titulo: 'Personal',
        descripcion:
            'Cuánto cuesta cada profesional, cuánto gasto moviliza y el histórico de tiempos de sus operaciones.',
        href: '/costeo/personal',
        icono: Users,
    },
    {
        titulo: 'Costo por componente',
        descripcion:
            'Composición del costo: talento humano, sala, equipos e insumos.',
        href: '/costeo/componentes',
        icono: ChartColumnBig,
    },
    {
        titulo: 'Outliers de costo',
        descripcion:
            'Cirugías con costos atípicos (z-score e IQR) por procedimiento.',
        href: '/costeo/outliers',
        icono: AlertTriangle,
    },
    {
        titulo: 'Rentabilidad',
        descripcion: 'Costo real vs. tarifa facturada y referencia SOAT −25 %.',
        href: '/costeo/rentabilidad',
        icono: TrendingUp,
    },
    {
        titulo: 'Variabilidad',
        descripcion: 'Coeficiente de variación de costos entre procedimientos.',
        href: '/costeo/variabilidad',
        icono: Waves,
    },
];

export default function CosteoIndex({
    costos,
    completitud,
    utilizacion,
    glosasRecaudo,
    tendencia,
    periodo,
    periodoEtiqueta,
}: {
    costos: CostosKpi;
    completitud: Completitud;
    utilizacion: UtilizacionSalas;
    glosasRecaudo: GlosasRecaudo;
    tendencia: TendenciaMensual;
    periodo: Periodo;
    periodoEtiqueta: string;
}) {
    // De mayor a menor: el procedimiento más caro es lo primero que se busca
    // aquí, y antes salían en el orden arbitrario de la consulta.
    const porProcedimiento = [...costos.por_procedimiento].sort(
        (a, b) => (b.costo_promedio ?? 0) - (a.costo_promedio ?? 0),
    );

    const maximoPromedio = Math.max(
        1,
        ...porProcedimiento.map((f) => f.costo_promedio ?? 0),
    );

    return (
        <>
            <Head title="Costeo quirúrgico" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Costeo quirúrgico"
                    descripcion="Indicadores operativos, composición de costos y rentabilidad hospitalaria."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <SelectorPeriodo
                        url="/costeo"
                        periodo={periodo}
                        etiqueta={periodoEtiqueta}
                    />
                    <BotonExportar
                        url="/exportar/indicadores"
                        filtros={{
                            desde: periodo.desde ?? '',
                            hasta: periodo.hasta ?? '',
                        }}
                        texto="Exportar indicadores"
                    />
                </div>

                <div className="grid gap-[18px] md:grid-cols-3 xl:grid-cols-6">
                    <KpiCard
                        titulo="Costo promedio por cirugía"
                        valor={cop(costos.global.costo_promedio)}
                        detalle={`${costos.global.n_cirugias_costeadas} cirugías costeadas`}
                    />
                    <KpiCard
                        titulo="Costo más alto"
                        valor={cop(costos.global.costo_maximo)}
                        detalle={`el más bajo, ${cop(costos.global.costo_minimo)}`}
                    />
                    <KpiCard
                        titulo="Completitud de captura"
                        valor={pct(completitud.completitud_global)}
                        detalle={`${completitud.completas} de ${completitud.total_cirugias_realizadas} cirugías completas`}
                    />
                    {/* El titular es el minuto operado, no el porcentaje: la
                        capacidad instalada de un quirófano es tan grande que
                        el ratio siempre sale bajo y se lee como un error. */}
                    <KpiCard
                        titulo="Minutos de sala operados"
                        valor={numero(utilizacion.global.minutos_usados)}
                        detalle={`${utilizacion.global.n_cirugias} cirugías · ${pct(utilizacion.global.utilizacion_pct)} de la capacidad`}
                    />
                    <KpiCard
                        titulo="Tasa de glosas"
                        valor={pct(glosasRecaudo.tasa_glosas)}
                        detalle={`${cop(glosasRecaudo.valor_glosado)} glosado`}
                    />
                    <KpiCard
                        titulo="Tasa de recaudo"
                        valor={pct(glosasRecaudo.tasa_recaudo)}
                        detalle={`${cop(glosasRecaudo.valor_recaudado)} recaudado`}
                    />
                </div>

                <TendenciaCostos tendencia={tendencia} />

                <div className="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-[18px]">
                    {paneles.map((panel) => (
                        <Link key={panel.href} href={panel.href} prefetch>
                            <Card className="h-full transition-colors hover:bg-accent/50">
                                <CardHeader>
                                    <panel.icono className="mb-1 size-5 text-muted-foreground" />
                                    <CardTitle className="text-base">
                                        {panel.titulo}
                                    </CardTitle>
                                    <CardDescription>
                                        {panel.descripcion}
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>

                <div className="grid gap-[18px] lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Completitud de captura por bloque
                            </CardTitle>
                            <CardDescription>
                                Cirugías realizadas con cada bloque de
                                información registrado
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-3">
                                {Object.entries(completitud.chequeos).map(
                                    ([clave, chequeo]) => (
                                        <li
                                            key={clave}
                                            className="flex items-center gap-3"
                                        >
                                            <span className="w-44 shrink-0 text-sm">
                                                {etiquetasChequeos[clave] ??
                                                    clave}
                                            </span>
                                            <div className="h-2 flex-1 overflow-hidden rounded bg-muted">
                                                <div
                                                    className="h-full rounded bg-primary"
                                                    style={{
                                                        width: `${(chequeo.porcentaje ?? 0) * 100}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="w-16 text-right text-sm text-muted-foreground tabular-nums">
                                                {pct(chequeo.porcentaje)}
                                            </span>
                                        </li>
                                    ),
                                )}
                            </ul>
                        </CardContent>
                    </Card>

                    {/* La tabla de CUPS + promedio era ilegible de un vistazo:
                        ahora es un ranking con barra, y cada fila entra al
                        procedimiento. */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Costo promedio por procedimiento
                            </CardTitle>
                            <CardDescription>
                                De mayor a menor, sobre las cirugías costeadas
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {porProcedimiento.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    Todavía no hay cirugías costeadas en este
                                    periodo.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {porProcedimiento.map((fila) => (
                                        <li key={fila.procedimiento.id}>
                                            <Link
                                                href={`/costeo/procedimientos/${fila.procedimiento.id}`}
                                                className="group block"
                                            >
                                                <div className="flex items-baseline justify-between gap-3 text-sm">
                                                    <span className="truncate group-hover:underline">
                                                        {
                                                            fila.procedimiento
                                                                .nombre
                                                        }
                                                    </span>
                                                    <span className="shrink-0 tabular-nums">
                                                        {cop(
                                                            fila.costo_promedio,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <div className="h-1.5 flex-1 overflow-hidden rounded bg-muted">
                                                        <div
                                                            className="h-full rounded bg-primary"
                                                            style={{
                                                                width: `${((fila.costo_promedio ?? 0) / maximoPromedio) * 100}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="shrink-0 font-mono text-[11px] text-muted-foreground">
                                                        {
                                                            fila.procedimiento
                                                                .codigo_cups
                                                        }{' '}
                                                        · n={fila.n}
                                                    </span>
                                                </div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* `por_sala` ya se calculaba y nunca se mostraba: era el dato
                    accionable detrás del porcentaje global. */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Utilización por sala
                        </CardTitle>
                        <CardDescription>
                            Minutos operados sobre la capacidad instalada ·{' '}
                            {utilizacion.ventana.etiqueta} (
                            {utilizacion.ventana.dias} días)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TablaResponsive>
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 font-medium">Sala</th>
                                    <th className="py-2 text-right font-medium">
                                        Cirugías
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Minutos operados
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Capacidad
                                    </th>
                                    <th className="py-2 pl-6 font-medium">
                                        Utilización
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {utilizacion.por_sala.map((fila) => (
                                    <tr
                                        key={fila.sala.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-2">
                                            {fila.sala.nombre}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {fila.n_cirugias}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {minutos(fila.minutos_usados)}
                                        </td>
                                        <td className="py-2 text-right text-muted-foreground tabular-nums">
                                            {minutos(fila.minutos_disponibles)}
                                        </td>
                                        <td className="py-2 pl-6">
                                            <div className="flex items-center gap-2">
                                                <div className="h-2 w-full max-w-40 overflow-hidden rounded bg-muted">
                                                    <div
                                                        className="h-full rounded bg-primary"
                                                        style={{
                                                            width: `${Math.min(100, (fila.utilizacion_pct ?? 0) * 100)}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="w-14 shrink-0 text-right tabular-nums">
                                                    {pct(fila.utilizacion_pct)}
                                                </span>
                                            </div>
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

CosteoIndex.layout = {
    breadcrumbs: [{ title: 'Costeo quirúrgico', href: '/costeo' }],
};
