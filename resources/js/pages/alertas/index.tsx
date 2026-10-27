import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Settings2 } from 'lucide-react';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { KpiCard } from '@/components/costeo/kpi-card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cop, mesCorto, mesLargo, numero } from '@/lib/formato';
import { PUNTO_TONO } from '@/lib/semaforo';

type Grupo = {
    clave: string;
    procedimiento: { id: number; codigo_cups: string; nombre: string } | null;
    sobrecostos: { pendientes: number; exceso: number };
    reglas: { pendientes: number; criticas: number; impacto: number };
    meses: string[];
    prioritario: boolean;
};

type Resumen = {
    casos: number;
    prioritarios: number;
    sobrecostos: number;
    exceso: number;
    reglas: number;
    criticas: number;
    impacto: number;
};

const TODOS = 'todos';

/** «abr 26 – oct 26», o un solo mes. */
function rangoMeses(meses: string[]): string {
    if (meses.length === 0) {
        return '—';
    }

    const primero = mesCorto(meses[0]);
    const ultimo = mesCorto(meses[meses.length - 1]);

    return primero === ultimo ? primero : `${primero} – ${ultimo}`;
}

/** Enlace a la bandeja de una fuente, filtrada por el caso. */
function hrefCaso(base: string, clave: string, mes: string): string {
    const params = new URLSearchParams({ procedimiento: clave });

    if (mes !== '') {
        params.set('mes', mes);
    }

    return `${base}?${params.toString()}`;
}

/**
 * Bandeja única de alertas (#32).
 *
 * Reúne los sobrecostos por cirugía y las alertas de las reglas por
 * procedimiento: lo que antes eran dos bandejas y cientos de tarjetas se lee
 * como unas decenas de casos. Los montos de cada fuente van en su columna y
 * no se suman: miden cosas distintas y a veces la misma cirugía.
 */
export default function AlertasIndex({
    grupos,
    resumen,
    meses,
    filtros,
}: {
    grupos: Grupo[];
    resumen: Resumen;
    meses: string[];
    filtros: { mes: string };
}) {
    const filtrarMes = (mes: string) =>
        router.get('/alertas', mes === TODOS ? {} : { mes }, {
            preserveScroll: true,
            replace: true,
        });

    return (
        <>
            <Head title="Alertas" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Alertas"
                    navegacion={false}
                    descripcion="Sobrecostos por cirugía y alertas de las reglas, agrupados por procedimiento. Cada caso abre sus alertas para atribuirles la causa."
                    accion={
                        <Button asChild variant="outline" size="sm">
                            <Link href="/conocimiento/reglas">
                                <Settings2 className="size-4" />
                                Ajustar reglas
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-[18px] md:grid-cols-3">
                    <KpiCard
                        titulo="Casos por revisar"
                        valor={numero(resumen.prioritarios)}
                        tono={resumen.prioritarios === 0 ? 'bien' : 'alerta'}
                        detalle={`Con un sobrecosto o una alerta crítica · ${numero(resumen.casos)} casos en total`}
                    />
                    <KpiCard
                        titulo="Sobrecostos sin explicar"
                        valor={numero(resumen.sobrecostos)}
                        detalle={`${cop(resumen.exceso)} sobre el costo habitual de cada procedimiento`}
                    />
                    <KpiCard
                        titulo="Alertas de las reglas"
                        valor={numero(resumen.reglas)}
                        detalle={`${numero(resumen.criticas)} críticas · ${cop(resumen.impacto)} de impacto estimado`}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <CardTitle className="text-base">
                                    Casos por procedimiento
                                </CardTitle>
                                <CardDescription>
                                    Primero los que tienen un sobrecosto sin
                                    explicar o una alerta crítica; dentro de
                                    ellos, los de mayor exceso.
                                </CardDescription>
                            </div>
                            <Select
                                value={filtros.mes || TODOS}
                                onValueChange={filtrarMes}
                            >
                                <SelectTrigger
                                    className="h-9 w-52"
                                    aria-label="Mes"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={TODOS}>
                                        Todos los meses
                                    </SelectItem>
                                    {meses.map((mes) => (
                                        <SelectItem key={mes} value={mes}>
                                            {mesLargo(mes)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {grupos.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No hay alertas pendientes
                                {filtros.mes
                                    ? ` en ${mesLargo(filtros.mes)}`
                                    : ''}
                                .
                            </p>
                        ) : (
                            <TablaResponsive>
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="py-2 pr-3">
                                            Procedimiento
                                        </th>
                                        <th className="py-2 pr-3">Meses</th>
                                        <th className="py-2 pr-3 text-right">
                                            Sobrecostos
                                        </th>
                                        <th className="py-2 pr-3 text-right">
                                            Alertas de reglas
                                        </th>
                                        <th className="py-2 text-right">
                                            Revisar
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {grupos.map((grupo) => (
                                        <tr
                                            key={grupo.clave}
                                            className="border-b align-top last:border-0"
                                        >
                                            <td className="py-2.5 pr-3">
                                                <div className="flex items-start gap-2">
                                                    <span
                                                        className={`mt-1.5 size-2 shrink-0 rounded-full ${PUNTO_TONO[grupo.prioritario ? 'alerta' : 'atencion']}`}
                                                        title={
                                                            grupo.prioritario
                                                                ? 'Pide revisión'
                                                                : 'Puede esperar'
                                                        }
                                                    />
                                                    {grupo.procedimiento ? (
                                                        <span>
                                                            <NombreDato
                                                                texto={
                                                                    grupo
                                                                        .procedimiento
                                                                        .nombre
                                                                }
                                                            />
                                                            <span className="block font-mono text-xs text-muted-foreground">
                                                                {
                                                                    grupo
                                                                        .procedimiento
                                                                        .codigo_cups
                                                                }
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span>
                                                            Salas y hospital
                                                            <span className="block text-xs text-muted-foreground">
                                                                Alertas sin un
                                                                procedimiento
                                                            </span>
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-2.5 pr-3 whitespace-nowrap text-muted-foreground">
                                                {rangoMeses(grupo.meses)}
                                            </td>
                                            <td className="py-2.5 pr-3 text-right whitespace-nowrap tabular-nums">
                                                {grupo.sobrecostos.pendientes >
                                                0 ? (
                                                    <>
                                                        {numero(
                                                            grupo.sobrecostos
                                                                .pendientes,
                                                        )}
                                                        <span className="block text-xs text-muted-foreground">
                                                            {cop(
                                                                grupo
                                                                    .sobrecostos
                                                                    .exceso,
                                                            )}{' '}
                                                            de exceso
                                                        </span>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2.5 pr-3 text-right whitespace-nowrap tabular-nums">
                                                {grupo.reglas.pendientes > 0 ? (
                                                    <>
                                                        {numero(
                                                            grupo.reglas
                                                                .pendientes,
                                                        )}
                                                        {grupo.reglas.criticas >
                                                            0 && (
                                                            <Badge
                                                                variant="outline"
                                                                className="ml-1.5 border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
                                                            >
                                                                {
                                                                    grupo.reglas
                                                                        .criticas
                                                                }{' '}
                                                                {grupo.reglas
                                                                    .criticas ===
                                                                1
                                                                    ? 'crítica'
                                                                    : 'críticas'}
                                                            </Badge>
                                                        )}
                                                        <span className="block text-xs text-muted-foreground">
                                                            {cop(
                                                                grupo.reglas
                                                                    .impacto,
                                                            )}{' '}
                                                            de impacto
                                                        </span>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 text-right whitespace-nowrap">
                                                {grupo.sobrecostos.pendientes >
                                                    0 && (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={hrefCaso(
                                                                '/costeo/alertas',
                                                                grupo.clave,
                                                                filtros.mes,
                                                            )}
                                                        >
                                                            Sobrecostos
                                                            <ArrowRight className="size-3.5" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {grupo.reglas.pendientes >
                                                    0 && (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={hrefCaso(
                                                                '/conocimiento/alertas',
                                                                grupo.clave,
                                                                filtros.mes,
                                                            )}
                                                        >
                                                            Reglas
                                                            <ArrowRight className="size-3.5" />
                                                        </Link>
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </TablaResponsive>
                        )}
                    </CardContent>
                </Card>

                <p className="text-sm text-muted-foreground">
                    También puede ver{' '}
                    <Link
                        href="/costeo/alertas?estado=todas"
                        className="underline underline-offset-2"
                    >
                        todos los sobrecostos
                    </Link>{' '}
                    o{' '}
                    <Link
                        href="/conocimiento/alertas?estado=todas"
                        className="underline underline-offset-2"
                    >
                        todas las alertas de las reglas
                    </Link>
                    , incluidas las ya revisadas.
                </p>
            </div>
        </>
    );
}

AlertasIndex.layout = {
    breadcrumbs: [{ title: 'Alertas', href: '/alertas' }],
};
