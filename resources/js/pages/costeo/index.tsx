import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ChartColumnBig,
    ClipboardList,
    TrendingUp,
    Waves,
} from 'lucide-react';
import { KpiCard } from '@/components/costeo/kpi-card';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, pct } from '@/lib/formato';
import type {
    Completitud,
    CostosKpi,
    GlosasRecaudo,
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
        titulo: 'Procedimientos',
        descripcion:
            'Explora cada procedimiento, sus cirugías realizadas y el costo detallado de cada una.',
        href: '/costeo/procedimientos',
        icono: ClipboardList,
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
}: {
    costos: CostosKpi;
    completitud: Completitud;
    utilizacion: UtilizacionSalas;
    glosasRecaudo: GlosasRecaudo;
}) {
    return (
        <>
            <Head title="Costeo quirúrgico" />
            <div className="flex flex-col gap-4 p-4">
                <div className="mb-1">
                    <h1 className="font-serif text-[32px] leading-tight font-semibold text-[#161B2F] dark:text-[#F3F0ED]">
                        Costeo quirúrgico
                    </h1>
                    <p className="mt-1 text-[13.5px] text-[#74787E] dark:text-[#A6AAB2]">
                        Indicadores operativos, composición de costos y
                        rentabilidad hospitalaria.
                    </p>
                </div>

                <div className="grid gap-[18px] md:grid-cols-3 xl:grid-cols-6">
                    <KpiCard
                        titulo="Costo promedio por cirugía"
                        valor={cop(costos.global.costo_promedio)}
                        detalle={`${costos.global.n_cirugias_costeadas} cirugías costeadas`}
                    />
                    <KpiCard
                        titulo="Rango de costos"
                        valor={cop(costos.global.costo_maximo)}
                        detalle={`mínimo ${cop(costos.global.costo_minimo)}`}
                    />
                    <KpiCard
                        titulo="Completitud de captura"
                        valor={pct(completitud.completitud_global)}
                        detalle={`${completitud.completas} de ${completitud.total_cirugias_realizadas} cirugías completas`}
                    />
                    <KpiCard
                        titulo={`Utilización de salas (${utilizacion.mes})`}
                        valor={pct(utilizacion.global.utilizacion_pct)}
                        detalle={`${utilizacion.global.minutos_usados.toLocaleString('es-CO')} min operados`}
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

                <div className="grid gap-[18px] md:grid-cols-2 xl:grid-cols-5">
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

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Costo promedio por procedimiento
                            </CardTitle>
                            <CardDescription>
                                Código CUPS y promedio de las cirugías costeadas
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2 font-medium">
                                            CUPS
                                        </th>
                                        <th className="py-2 font-medium">
                                            Procedimiento
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            n
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Promedio
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {costos.por_procedimiento.map((fila) => (
                                        <tr
                                            key={fila.procedimiento.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2 font-mono text-xs">
                                                {fila.procedimiento.codigo_cups}
                                            </td>
                                            <td className="py-2">
                                                {fila.procedimiento.nombre}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {fila.n}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(fila.costo_promedio)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

CosteoIndex.layout = {
    breadcrumbs: [{ title: 'Costeo quirúrgico', href: '/costeo' }],
};
