import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BellRing,
    CircleCheck,
    ClipboardList,
    Plus,
    SlidersHorizontal,
    Stethoscope,
    TrendingUp,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { KpiCard } from '@/components/costeo/kpi-card';
import { NombreDato } from '@/components/nombre-dato';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, mesCorto, mesLargo, numero, pct } from '@/lib/formato';
import type { Tono } from '@/lib/semaforo';
import {
    compararPuntos,
    compararRelativo,
    PUNTO_TONO,
    tonoMayorEsMejor,
    tonoMenorEsMejor,
    UMBRALES,
} from '@/lib/semaforo';

type Resultado = {
    n: number;
    costo: number;
    facturado: number;
    margen: number;
    margen_pct: number | null;
};

type Indicadores = {
    mes: string;
    mes_anterior: string;
    resultado: { actual: Resultado; anterior: Resultado };
    costo_promedio: {
        actual: number | null;
        anterior: number | null;
        n: number;
    };
    completitud: {
        actual: number | null;
        anterior: number | null;
        completas: number;
        total: number;
    };
    alertas: {
        casos: number;
        prioritarios: number;
        sobrecostos: number;
        exceso: number;
        reglas: number;
        criticas: number;
        impacto: number;
    };
};

type ItemAtencion = {
    clave:
        'alertas' | 'perdida' | 'pendientes' | 'lecciones' | 'recomendaciones';
    tono: Tono;
    href: string;
    n?: number;
    casos?: number;
    sobrecostos?: number;
    exceso?: number;
    criticas?: number;
    procedimiento?: { id: number; codigo_cups: string; nombre: string };
    margen_promedio?: number;
    margen_pct?: number | null;
    perdida_total?: number;
};

function plural(n: number, uno: string, varios: string): string {
    return `${numero(n)} ${n === 1 ? uno : varios}`;
}

/** Texto de cada punto de la lista: título, explicación y acción. */
function textoAtencion(item: ItemAtencion): {
    titulo: ReactNode;
    detalle: string;
    accion: string;
} {
    switch (item.clave) {
        case 'alertas':
            return {
                titulo: `${plural(item.casos ?? 0, 'caso', 'casos')} con alertas por revisar`,
                detalle: `${plural(item.sobrecostos ?? 0, 'sobrecosto', 'sobrecostos')} sin explicar (${cop(item.exceso)} de exceso) y ${plural(item.criticas ?? 0, 'alerta crítica', 'alertas críticas')} de las reglas.`,
                accion: 'Abrir la bandeja',
            };
        case 'perdida':
            return {
                titulo: (
                    <>
                        <NombreDato texto={item.procedimiento?.nombre} /> se
                        hace a pérdida
                    </>
                ),
                detalle: `Pierde ${cop(Math.abs(item.margen_promedio ?? 0))} por cirugía (${pct(item.margen_pct)} de lo facturado) en ${plural(item.n ?? 0, 'cirugía', 'cirugías')} de los últimos tres meses: ${cop(Math.abs(item.perdida_total ?? 0))} en total.`,
                accion: 'Ver el procedimiento',
            };
        case 'pendientes':
            return {
                titulo: `${plural(item.n ?? 0, 'cirugía', 'cirugías')} sin completar`,
                detalle:
                    'No entran a los indicadores hasta que se cierren o se costeen.',
                accion: 'Ver pendientes',
            };
        case 'lecciones':
            return {
                titulo: `${plural(item.n ?? 0, 'lección espera', 'lecciones esperan')} su validación`,
                detalle:
                    'Al publicarlas quedan disponibles para todo el equipo quirúrgico.',
                accion: 'Revisar',
            };
        case 'recomendaciones':
            return {
                titulo: `${plural(item.n ?? 0, 'recomendación nueva', 'recomendaciones nuevas')}`,
                detalle:
                    'Acciones que sugiere el histórico, cada una con su ahorro estimado.',
                accion: 'Ver recomendaciones',
            };
    }
}

/**
 * Portada del administrador del hospital (#30).
 *
 * Responde «¿cómo vamos?» antes de entrar a cualquier tablero: cuatro
 * indicadores del último mes con cirugías, comparados con el anterior y con
 * su semáforo, y una lista corta de lo que pide acción.
 */
export default function Inicio({
    indicadores,
    atencion,
}: {
    indicadores: Indicadores;
    atencion: ItemAtencion[];
}) {
    const { hospital } = usePage().props;
    const { resultado, costo_promedio, completitud, alertas } = indicadores;
    const referencia = mesCorto(indicadores.mes_anterior);

    const variacionCosto =
        costo_promedio.actual != null &&
        costo_promedio.anterior != null &&
        costo_promedio.anterior > 0
            ? (costo_promedio.actual - costo_promedio.anterior) /
              costo_promedio.anterior
            : null;

    const margen = resultado.actual.margen;

    return (
        <>
            <Head title="Inicio" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo={hospital.activo?.nombre ?? 'Inicio'}
                    navegacion={false}
                    descripcion={`Cómo va ${mesLargo(indicadores.mes)}, el último mes con cirugías registradas, frente a ${mesLargo(indicadores.mes_anterior)}.`}
                    accion={
                        <Button asChild>
                            <Link href="/cirugias/create">
                                <Plus className="size-4" />
                                Registrar cirugía
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-[18px] sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        titulo="Margen sobre lo facturado"
                        valor={pct(resultado.actual.margen_pct)}
                        tono={tonoMayorEsMejor(
                            resultado.actual.margen_pct,
                            UMBRALES.margen,
                        )}
                        comparacion={compararPuntos(
                            resultado.actual.margen_pct,
                            resultado.anterior.margen_pct,
                            'sube',
                            referencia,
                        )}
                        detalle={
                            resultado.actual.n === 0
                                ? 'Sin cirugías facturadas en el mes'
                                : `${margen >= 0 ? 'Ganancia' : 'Pérdida'} de ${cop(Math.abs(margen))} en ${plural(resultado.actual.n, 'cirugía facturada', 'cirugías facturadas')}`
                        }
                    />
                    <KpiCard
                        titulo="Costo promedio por cirugía"
                        valor={cop(costo_promedio.actual)}
                        tono={tonoMenorEsMejor(
                            variacionCosto,
                            UMBRALES.variacionCosto,
                        )}
                        comparacion={compararRelativo(
                            costo_promedio.actual,
                            costo_promedio.anterior,
                            'baja',
                            referencia,
                        )}
                        detalle={plural(
                            costo_promedio.n,
                            'cirugía costeada',
                            'cirugías costeadas',
                        )}
                    />
                    <KpiCard
                        titulo="Completitud de captura"
                        valor={pct(completitud.actual)}
                        tono={tonoMayorEsMejor(
                            completitud.actual,
                            UMBRALES.completitud,
                        )}
                        comparacion={compararPuntos(
                            completitud.actual,
                            completitud.anterior,
                            'sube',
                            referencia,
                        )}
                        detalle={`${numero(completitud.completas)} de ${numero(completitud.total)} completas · meta más de 90 %`}
                    />
                    <KpiCard
                        titulo="Casos con alertas por revisar"
                        valor={numero(alertas.prioritarios)}
                        tono={
                            alertas.prioritarios === 0
                                ? 'bien'
                                : alertas.prioritarios <= 3
                                  ? 'atencion'
                                  : 'alerta'
                        }
                        detalle={`${plural(alertas.sobrecostos, 'sobrecosto', 'sobrecostos')} y ${plural(alertas.criticas, 'alerta crítica', 'alertas críticas')} sin explicar`}
                    />
                </div>

                <div className="grid gap-[18px] lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Lo que necesita su atención
                            </CardTitle>
                            <CardDescription>
                                De lo más urgente a lo menos; como mucho cinco
                                cosas.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {atencion.length === 0 ? (
                                <p className="flex items-center gap-2 py-6 text-sm text-muted-foreground">
                                    <CircleCheck className="size-5 text-emerald-600 dark:text-emerald-400" />
                                    Nada pendiente por ahora.
                                </p>
                            ) : (
                                <ol className="divide-y divide-border">
                                    {atencion.map((item) => {
                                        const texto = textoAtencion(item);

                                        return (
                                            <li
                                                key={item.clave}
                                                className="flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                                            >
                                                <span
                                                    className={`mt-1.5 size-2.5 shrink-0 rounded-full ${PUNTO_TONO[item.tono]}`}
                                                    aria-hidden
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-semibold">
                                                        {texto.titulo}
                                                    </p>
                                                    <p className="mt-0.5 text-[13px] text-muted-foreground">
                                                        {texto.detalle}
                                                    </p>
                                                </div>
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                    className="shrink-0"
                                                >
                                                    <Link
                                                        href={item.href}
                                                        prefetch
                                                    >
                                                        {texto.accion}
                                                        <ArrowRight className="size-4" />
                                                    </Link>
                                                </Button>
                                            </li>
                                        );
                                    })}
                                </ol>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Ir directo a
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <nav className="flex flex-col">
                                {[
                                    {
                                        href: '/alertas',
                                        texto: 'Bandeja de alertas',
                                        icono: BellRing,
                                    },
                                    {
                                        href: '/costeo',
                                        texto: 'Costeo quirúrgico',
                                        icono: Stethoscope,
                                    },
                                    {
                                        href: '/costeo/rentabilidad',
                                        texto: 'Rentabilidad por procedimiento',
                                        icono: TrendingUp,
                                    },
                                    {
                                        href: '/cirugias',
                                        texto: 'Cirugías realizadas',
                                        icono: ClipboardList,
                                    },
                                    {
                                        href: '/parametros',
                                        texto: 'Parámetros del hospital',
                                        icono: SlidersHorizontal,
                                    },
                                ].map((acceso) => (
                                    <Link
                                        key={acceso.href}
                                        href={acceso.href}
                                        prefetch
                                        className="flex items-center gap-2.5 rounded-lg px-2 py-2 text-sm hover:bg-accent"
                                    >
                                        <acceso.icono className="size-4 text-muted-foreground" />
                                        {acceso.texto}
                                    </Link>
                                ))}
                            </nav>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Inicio.layout = {
    breadcrumbs: [{ title: 'Inicio', href: '/inicio' }],
};
