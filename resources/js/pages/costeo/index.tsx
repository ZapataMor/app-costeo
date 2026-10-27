import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight } from 'lucide-react';
import { BotonExportar } from '@/components/boton-exportar';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import {
    consultaDePeriodo,
    SelectorPeriodo,
} from '@/components/costeo/selector-periodo';
import { TendenciaCostos } from '@/components/costeo/tendencia-costos';
import { NombreDato } from '@/components/nombre-dato';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, minutos, numero, pct, pctVariacion } from '@/lib/formato';
import type { Comparacion } from '@/lib/semaforo';
import {
    compararPuntos,
    TEXTO_TONO,
    tonoMayorEsMejor,
    tonoMenorEsMejor,
    UMBRALES,
} from '@/lib/semaforo';
import type {
    AnalisisProcedimiento,
    CapacidadNoUtilizada,
    ComparacionResumen,
    Completitud,
    CostosKpi,
    DesviacionKpi,
    ResultadoKpi,
    TendenciaMensual,
} from '@/types/costeo';

/** Filas de cada lista de «dónde mirar». */
const FILAS_LISTA = 5;

/** Clasificación de la desviación, en el orden de la barra. */
const CLASIFICACIONES = [
    { clave: 'subejecucion', nombre: 'Por debajo', color: '#5B687C' },
    { clave: 'en_rango', nombre: 'En rango', color: '#4C837C' },
    { clave: 'sobrecosto_leve', nombre: 'Sobrecosto leve', color: '#A47E53' },
    { clave: 'sobrecosto_alto', nombre: 'Sobrecosto alto', color: '#9E3B3B' },
] as const;

/**
 * Variación a igual mezcla de procedimientos: la única comparación honesta
 * del costo por cirugía entre dos periodos.
 */
function compararMezcla(
    mezcla: ComparacionResumen['mezcla'] | undefined,
): Comparacion | undefined {
    if (!mezcla || mezcla.variacion === null) {
        return undefined;
    }

    const v = mezcla.variacion;
    const flecha = Math.abs(v) < 0.0005 ? '=' : v > 0 ? '▲' : '▼';

    return {
        texto: `${flecha} ${pctVariacion(v)} a igual mezcla vs. periodo anterior`,
        tono: Math.abs(v) < 0.0005 ? 'neutro' : v > 0 ? 'alerta' : 'bien',
    };
}

/** Barra horizontal de las listas. */
function Barra({ fraccion, color }: { fraccion: number; color: string }) {
    return (
        <div className="h-1.5 flex-1 overflow-hidden rounded bg-muted">
            <div
                className="h-full rounded"
                style={{
                    width: `${Math.min(100, Math.max(0, fraccion * 100))}%`,
                    backgroundColor: color,
                }}
            />
        </div>
    );
}

export default function CosteoIndex({
    costos,
    resultado,
    desviacion,
    completitud,
    tendencia,
    capacidad,
    procedimientos,
    anterior,
    periodo,
    periodoEtiqueta,
    periodoAnteriorEtiqueta,
}: {
    costos: CostosKpi;
    resultado: ResultadoKpi;
    desviacion: DesviacionKpi;
    completitud: Completitud;
    tendencia: TendenciaMensual;
    capacidad: CapacidadNoUtilizada;
    procedimientos: AnalisisProcedimiento[];
    anterior: ComparacionResumen | null;
    periodo: Periodo;
    periodoEtiqueta: string;
    periodoAnteriorEtiqueta: string | null;
}) {
    const consulta = consultaDePeriodo(periodo);
    const costoTotal = tendencia.meses.reduce((s, m) => s + m.costo_total, 0);

    const pierden = procedimientos
        .filter((p) => p.resultado !== null && p.resultado < 0)
        .sort((a, b) => (a.resultado ?? 0) - (b.resultado ?? 0))
        .slice(0, FILAS_LISTA);
    const mayorPerdida = Math.max(
        1,
        ...pierden.map((p) => -(p.resultado ?? 0)),
    );

    // Solo sobrecostos de más del 5 %: lo que está en rango no es noticia.
    const desviados = procedimientos
        .filter((p) => p.desviacion_pct !== null && p.desviacion_pct > 0.05)
        .sort((a, b) => (b.desviacion_pct ?? 0) - (a.desviacion_pct ?? 0))
        .slice(0, FILAS_LISTA);
    const mayorDesviacion = Math.max(
        0.01,
        ...desviados.map((p) => p.desviacion_pct ?? 0),
    );

    const totalClasificadas = CLASIFICACIONES.reduce(
        (s, c) => s + (desviacion.por_clasificacion[c.clave] ?? 0),
        0,
    );

    const capturaIncompleta =
        completitud.completitud_global !== null &&
        completitud.completitud_global < UMBRALES.completitud.bien;

    return (
        <>
            <Head title="Costeo quirúrgico" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Costeo quirúrgico"
                    descripcion="Si el servicio gana o pierde, cuánto se aparta del protocolo, cuánta capacidad se paga sin usar y qué procedimientos lo explican."
                    accion={
                        <BotonExportar
                            url="/exportar/indicadores"
                            filtros={{
                                desde: periodo.desde ?? '',
                                hasta: periodo.hasta ?? '',
                            }}
                            texto="Exportar indicadores"
                        />
                    }
                />

                <SelectorPeriodo
                    url="/costeo"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                />

                {capturaIncompleta && (
                    <div className="flex flex-wrap items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                        <p className="flex-1">
                            Solo el{' '}
                            <strong>
                                {pct(completitud.completitud_global)}
                            </strong>{' '}
                            de las{' '}
                            {numero(completitud.total_cirugias_realizadas)}{' '}
                            cirugías del periodo tiene la captura completa
                            (equipo, insumos, costo, resultado clínico y
                            factura). Las cifras pueden cambiar cuando se
                            complete.
                        </p>
                        <Link
                            href="/cirugias?vista=pendientes"
                            className="font-medium whitespace-nowrap hover:underline"
                        >
                            Ver pendientes
                        </Link>
                    </div>
                )}

                <div className="grid gap-[18px] sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        titulo="Resultado neto"
                        valor={cop(resultado.margen)}
                        tono={tonoMayorEsMejor(
                            resultado.margen_pct,
                            UMBRALES.margen,
                        )}
                        comparacion={compararPuntos(
                            resultado.margen_pct,
                            anterior?.resultado.margen_pct,
                            'sube',
                            'periodo anterior',
                        )}
                        detalle={
                            resultado.n > 0
                                ? `margen ${pct(resultado.margen_pct)} sobre ${cop(resultado.facturado)} facturados · ${numero(resultado.n)} cirugías`
                                : 'ninguna cirugía facturada en el periodo'
                        }
                    />
                    <KpiCard
                        titulo="Costo promedio por cirugía"
                        valor={cop(costos.global.costo_promedio)}
                        comparacion={compararMezcla(anterior?.mezcla)}
                        detalle={`${numero(costos.global.n_cirugias_costeadas)} cirugías costeadas · ${cop(costoTotal)} en total`}
                    />
                    <KpiCard
                        titulo="Desviación frente al estándar"
                        valor={
                            desviacion.desviacion_pct === null
                                ? '—'
                                : pctVariacion(desviacion.desviacion_pct)
                        }
                        tono={tonoMenorEsMejor(
                            desviacion.desviacion_pct,
                            UMBRALES.desviacionEstandar,
                        )}
                        comparacion={compararPuntos(
                            desviacion.desviacion_pct,
                            anterior?.desviacion.desviacion_pct,
                            'baja',
                            'periodo anterior',
                        )}
                        detalle={
                            desviacion.n > 0
                                ? `${cop(desviacion.desviacion)} sobre lo que marca el protocolo · ${numero(desviacion.n)} de ${numero(desviacion.n_costeadas)} cirugías con estándar`
                                : 'ningún procedimiento del periodo tiene plantilla de protocolo'
                        }
                    />
                    <KpiCard
                        titulo="Capacidad pagada sin usar"
                        valor={cop(capacidad.total)}
                        detalle={
                            capacidad.bolsas.monto > 0
                                ? `las cirugías absorbieron el ${pct(capacidad.bolsas.absorcion_pct, 0)} de las bolsas de costos indirectos`
                                : 'salas sin cirugía, valoradas a su costo por hora'
                        }
                    />
                </div>

                <TendenciaCostos tendencia={tendencia} />

                <div className="grid items-start gap-[18px] lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Dónde se pierde dinero
                            </CardTitle>
                            <CardDescription>
                                Procedimientos cuyo costo real supera lo
                                facturado, por pérdida total en el periodo
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {pierden.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    {resultado.n === 0
                                        ? 'Sin cirugías facturadas en el periodo.'
                                        : 'Ningún procedimiento facturado da pérdida en este periodo.'}
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {pierden.map((fila) => (
                                        <li key={fila.procedimiento.id}>
                                            <Link
                                                href={`/costeo/procedimientos/${fila.procedimiento.id}${consulta}`}
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
                                                    <span
                                                        className={`shrink-0 font-medium tabular-nums ${TEXTO_TONO.alerta}`}
                                                    >
                                                        {cop(fila.resultado)}
                                                    </span>
                                                </div>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <Barra
                                                        fraccion={
                                                            -(
                                                                fila.resultado ??
                                                                0
                                                            ) / mayorPerdida
                                                        }
                                                        color="#9E3B3B"
                                                    />
                                                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                        margen{' '}
                                                        {pct(fila.margen_pct)} ·{' '}
                                                        {numero(
                                                            fila.n_facturadas,
                                                        )}{' '}
                                                        facturadas
                                                    </span>
                                                </div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <Link
                                href={`/costeo/rentabilidad${consulta}`}
                                className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium hover:underline"
                            >
                                Ver la rentabilidad de todos
                                <ArrowRight className="size-4" />
                            </Link>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Dónde se aparta del protocolo
                            </CardTitle>
                            <CardDescription>
                                Procedimientos que cuestan más de un 5 % por
                                encima de su costo estándar
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {totalClasificadas > 0 && (
                                <div className="mb-5">
                                    <div
                                        className="flex h-2.5 w-full overflow-hidden rounded"
                                        role="img"
                                        aria-label={CLASIFICACIONES.map(
                                            (c) =>
                                                `${c.nombre}: ${desviacion.por_clasificacion[c.clave] ?? 0}`,
                                        ).join(', ')}
                                    >
                                        {CLASIFICACIONES.map((c) => (
                                            <div
                                                key={c.clave}
                                                style={{
                                                    width: `${((desviacion.por_clasificacion[c.clave] ?? 0) / totalClasificadas) * 100}%`,
                                                    backgroundColor: c.color,
                                                }}
                                            />
                                        ))}
                                    </div>
                                    <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        {CLASIFICACIONES.map((c) => (
                                            <li
                                                key={c.clave}
                                                className="flex items-center gap-1.5"
                                            >
                                                <span
                                                    className="size-2 rounded-sm"
                                                    style={{
                                                        backgroundColor:
                                                            c.color,
                                                    }}
                                                    aria-hidden
                                                />
                                                {c.nombre}{' '}
                                                <span className="tabular-nums">
                                                    {numero(
                                                        desviacion
                                                            .por_clasificacion[
                                                            c.clave
                                                        ] ?? 0,
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {desviados.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    {desviacion.n === 0
                                        ? 'Sin cirugías con costo estándar en el periodo: configure la plantilla de protocolo de cada procedimiento.'
                                        : 'Ningún procedimiento supera su estándar en más de un 5 %.'}
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {desviados.map((fila) => (
                                        <li key={fila.procedimiento.id}>
                                            <Link
                                                href={`/costeo/procedimientos/${fila.procedimiento.id}${consulta}`}
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
                                                    <span
                                                        className={`shrink-0 font-medium tabular-nums ${TEXTO_TONO[tonoMenorEsMejor(fila.desviacion_pct, UMBRALES.desviacionEstandar)]}`}
                                                    >
                                                        {pctVariacion(
                                                            fila.desviacion_pct,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <Barra
                                                        fraccion={
                                                            (fila.desviacion_pct ??
                                                                0) /
                                                            mayorDesviacion
                                                        }
                                                        color="#A47E53"
                                                    />
                                                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                        {numero(
                                                            fila.n_con_estandar,
                                                        )}{' '}
                                                        {fila.n_con_estandar ===
                                                        1
                                                            ? 'cirugía'
                                                            : 'cirugías'}
                                                    </span>
                                                </div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <Link
                                href={`/costeo/procedimientos${consulta}`}
                                className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium hover:underline"
                            >
                                Ver todos los procedimientos
                                <ArrowRight className="size-4" />
                            </Link>
                        </CardContent>
                    </Card>
                </div>

                <CapacidadCard capacidad={capacidad} />

                {periodoAnteriorEtiqueta && (
                    <p className="text-xs text-muted-foreground">
                        Las comparaciones son contra el periodo anterior de la
                        misma duración: {periodoAnteriorEtiqueta}.
                    </p>
                )}
            </div>
        </>
    );
}

/**
 * Capacidad no utilizada (backlog #2): lo que el hospital paga por tener
 * quirófanos y bolsas de costos indirectos disponibles y las cirugías no
 * alcanzan a usar. No es un costo de ninguna cirugía, por eso no aparecía en
 * ningún tablero.
 */
function CapacidadCard({ capacidad }: { capacidad: CapacidadNoUtilizada }) {
    const { bolsas, salas } = capacidad;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    Capacidad pagada sin usar
                </CardTitle>
                <CardDescription className="max-w-[80ch]">
                    Lo que cuesta tener los quirófanos disponibles y que las
                    cirugías no alcanzan a usar · {capacidad.ventana.etiqueta} (
                    {capacidad.ventana.dias} días). No es costo de ninguna
                    cirugía: es la oportunidad de operar más con la misma
                    estructura.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-8 lg:grid-cols-2">
                <div>
                    <h3 className="text-sm font-medium">
                        Bolsas de costos indirectos
                    </h3>
                    {bolsas.monto > 0 ? (
                        <>
                            <div className="mt-3 flex h-3 w-full overflow-hidden rounded bg-muted">
                                <div
                                    className="h-full bg-[#4C837C]"
                                    style={{
                                        width: `${(bolsas.absorcion_pct ?? 0) * 100}%`,
                                    }}
                                />
                            </div>
                            <dl className="mt-3 grid grid-cols-3 gap-3 text-sm">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Monto de las bolsas
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        {cop(bolsas.monto)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Absorbido por cirugías
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        {cop(bolsas.absorbido)}{' '}
                                        <span className="text-xs font-normal text-muted-foreground">
                                            ({pct(bolsas.absorcion_pct, 0)})
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Sin absorber
                                    </dt>
                                    <dd
                                        className={`font-medium tabular-nums ${TEXTO_TONO.atencion}`}
                                    >
                                        {cop(bolsas.no_absorbido)}
                                    </dd>
                                </div>
                            </dl>
                        </>
                    ) : (
                        <p className="mt-2 text-sm text-muted-foreground">
                            El hospital no tiene bolsas de costos indirectos con
                            monto mensual en el periodo: los indirectos se
                            calculan como un porcentaje del costo directo.
                        </p>
                    )}
                </div>

                <div>
                    <h3 className="text-sm font-medium">
                        Salas · {minutos(salas.minutos_ociosos)} sin cirugía
                    </h3>
                    {salas.por_sala.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            No hay salas registradas.
                        </p>
                    ) : (
                        <ul className="mt-3 space-y-3">
                            {salas.por_sala.map((fila) => (
                                <li key={fila.sala.id}>
                                    <div className="flex items-baseline justify-between gap-3 text-sm">
                                        <span className="truncate">
                                            <NombreDato
                                                texto={fila.sala.nombre}
                                            />
                                        </span>
                                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                            {fila.valor_ocioso !== null
                                                ? `${cop(fila.valor_ocioso)} sin usar`
                                                : 'su costo va en las bolsas'}
                                        </span>
                                    </div>
                                    <div className="mt-1 flex items-center gap-2">
                                        <Barra
                                            fraccion={fila.utilizacion_pct ?? 0}
                                            color="#4C837C"
                                        />
                                        <span className="w-40 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                                            usada al {pct(fila.utilizacion_pct)}{' '}
                                            · {minutos(fila.minutos_usados)}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

CosteoIndex.layout = {
    breadcrumbs: [{ title: 'Costeo quirúrgico', href: '/costeo' }],
};
