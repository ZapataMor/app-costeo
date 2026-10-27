import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { BotonExportar } from '@/components/boton-exportar';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import { SelectorPeriodo } from '@/components/costeo/selector-periodo';
import { TendenciaCostos } from '@/components/costeo/tendencia-costos';
import { NombreDato } from '@/components/nombre-dato';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, minutos, numero, pct } from '@/lib/formato';
import { tonoMayorEsMejor, UMBRALES } from '@/lib/semaforo';
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

/** Los diez más caros; el resto está a un clic en «Procedimientos». */
const TOP_PROCEDIMIENTOS = 10;

/** Barra horizontal de las listas de esta página. */
function Barra({ fraccion }: { fraccion: number }) {
    return (
        <div className="h-1.5 flex-1 overflow-hidden rounded bg-muted">
            <div
                className="h-full rounded bg-primary"
                style={{
                    width: `${Math.min(100, Math.max(0, fraccion * 100))}%`,
                }}
            />
        </div>
    );
}

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
    const top = porProcedimiento.slice(0, TOP_PROCEDIMIENTOS);

    const maximoPromedio = Math.max(
        1,
        ...porProcedimiento.map((f) => f.costo_promedio ?? 0),
    );

    // El periodo viaja al explorador para que «ver todos» muestre lo mismo.
    const consultaPeriodo = new URLSearchParams(
        Object.entries(periodo).filter(
            (par): par is [string, string] => par[1] !== null,
        ),
    ).toString();

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
                        detalle={`${numero(costos.global.n_cirugias_costeadas)} cirugías costeadas`}
                    />
                    <KpiCard
                        titulo="Costo más alto"
                        valor={cop(costos.global.costo_maximo)}
                        detalle={`el más bajo, ${cop(costos.global.costo_minimo)}`}
                    />
                    <KpiCard
                        titulo="Completitud de captura"
                        valor={pct(completitud.completitud_global)}
                        tono={tonoMayorEsMejor(
                            completitud.completitud_global,
                            UMBRALES.completitud,
                        )}
                        detalle={`${numero(completitud.completas)} de ${numero(completitud.total_cirugias_realizadas)} completas · meta más de 90 %`}
                    />
                    {/* El titular es el minuto operado, no el porcentaje: la
                        capacidad instalada de un quirófano es tan grande que
                        el ratio siempre sale bajo y se lee como un error. */}
                    <KpiCard
                        titulo="Minutos de sala operados"
                        valor={numero(utilizacion.global.minutos_usados)}
                        detalle={`${numero(utilizacion.global.n_cirugias)} cirugías · ${pct(utilizacion.global.utilizacion_pct)} de la capacidad`}
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

                {/* Antes: siete tarjetas de enlace entre las gráficas (ahora
                    son las pestañas del encabezado), un ranking de ~40 filas
                    y, al lado, una tarjeta de cinco que dejaba un hueco. */}
                <div className="grid items-start gap-[18px] lg:grid-cols-5">
                    <Card className="lg:col-span-3">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Procedimientos más costosos
                            </CardTitle>
                            <CardDescription>
                                Costo promedio por cirugía, de mayor a menor
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {top.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    Todavía no hay cirugías costeadas en este
                                    periodo.
                                </p>
                            ) : (
                                <>
                                    <ul className="space-y-3">
                                        {top.map((fila) => (
                                            <li key={fila.procedimiento.id}>
                                                <Link
                                                    href={`/costeo/procedimientos/${fila.procedimiento.id}`}
                                                    className="group block"
                                                >
                                                    <div className="flex items-baseline justify-between gap-3 text-sm">
                                                        <span className="truncate group-hover:underline">
                                                            <NombreDato
                                                                texto={
                                                                    fila
                                                                        .procedimiento
                                                                        .nombre
                                                                }
                                                            />
                                                        </span>
                                                        <span className="shrink-0 font-medium tabular-nums">
                                                            {cop(
                                                                fila.costo_promedio,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 flex items-center gap-2">
                                                        <Barra
                                                            fraccion={
                                                                (fila.costo_promedio ??
                                                                    0) /
                                                                maximoPromedio
                                                            }
                                                        />
                                                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                            {numero(fila.n)}{' '}
                                                            {fila.n === 1
                                                                ? 'cirugía'
                                                                : 'cirugías'}
                                                        </span>
                                                    </div>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                    {porProcedimiento.length > top.length && (
                                        <Link
                                            href={`/costeo/procedimientos${consultaPeriodo ? `?${consultaPeriodo}` : ''}`}
                                            className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium hover:underline"
                                        >
                                            Ver los {porProcedimiento.length}{' '}
                                            procedimientos
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-[18px] lg:col-span-2">
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
                                                className="flex items-center gap-3 text-sm"
                                            >
                                                <span className="w-36 shrink-0">
                                                    {etiquetasChequeos[clave] ??
                                                        clave}
                                                </span>
                                                <Barra
                                                    fraccion={
                                                        chequeo.porcentaje ?? 0
                                                    }
                                                />
                                                <span className="w-14 shrink-0 text-right text-muted-foreground tabular-nums">
                                                    {pct(chequeo.porcentaje)}
                                                </span>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </CardContent>
                        </Card>

                        {/* `por_sala` ya se calculaba y nunca se mostraba: era
                            el dato accionable detrás del porcentaje global. */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Utilización por sala
                                </CardTitle>
                                <CardDescription>
                                    Minutos operados sobre la capacidad
                                    instalada · {utilizacion.ventana.etiqueta} (
                                    {utilizacion.ventana.dias} días)
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-3">
                                    {utilizacion.por_sala.map((fila) => (
                                        <li key={fila.sala.id}>
                                            <div className="flex items-baseline justify-between gap-3 text-sm">
                                                <span className="truncate">
                                                    <NombreDato
                                                        texto={fila.sala.nombre}
                                                    />
                                                </span>
                                                <span className="shrink-0 tabular-nums">
                                                    {pct(fila.utilizacion_pct)}
                                                </span>
                                            </div>
                                            <div className="mt-1 flex items-center gap-2">
                                                <Barra
                                                    fraccion={
                                                        fila.utilizacion_pct ??
                                                        0
                                                    }
                                                />
                                                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                    {minutos(
                                                        fila.minutos_usados,
                                                    )}{' '}
                                                    de{' '}
                                                    {minutos(
                                                        fila.minutos_disponibles,
                                                    )}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

CosteoIndex.layout = {
    breadcrumbs: [{ title: 'Costeo quirúrgico', href: '/costeo' }],
};
