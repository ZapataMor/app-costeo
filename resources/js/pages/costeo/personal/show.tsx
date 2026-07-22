import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { IndiceBadge } from '@/components/costeo/indice-badge';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import { SelectorPeriodo } from '@/components/costeo/selector-periodo';
import Heading from '@/components/heading';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop } from '@/lib/formato';
import type {
    DesglosePersonal,
    PaginadoHistorialPersona,
    PersonaCosteo,
    ProcedimientoDePersona,
} from '@/types/costeo';

function TablaDesglose({
    titulo,
    descripcion,
    filas,
    etiquetas,
}: {
    titulo: string;
    descripcion: string;
    filas: DesglosePersonal[];
    etiquetas?: Record<string, string>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{titulo}</CardTitle>
                <CardDescription>{descripcion}</CardDescription>
            </CardHeader>
            <CardContent>
                {filas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Sin participaciones en el periodo.
                    </p>
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="pb-2 font-medium">Concepto</th>
                                <th className="pb-2 text-right font-medium">
                                    Veces
                                </th>
                                <th className="pb-2 text-right font-medium">
                                    Minutos
                                </th>
                                <th className="pb-2 text-right font-medium">
                                    Costo propio
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((fila) => (
                                <tr
                                    key={fila.clave}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-2 capitalize">
                                        {etiquetas?.[fila.clave] ?? fila.clave}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {fila.n_participaciones}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {fila.minutos.toLocaleString('es-CO')}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {cop(fila.costo_propio)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </CardContent>
        </Card>
    );
}

export default function PersonalCosteoShow({
    persona,
    por_rol,
    por_fase,
    porProcedimiento,
    historial,
    etiquetasFase,
    minimoParaComparar,
    periodo,
    periodoEtiqueta,
}: {
    persona: PersonaCosteo;
    por_rol: DesglosePersonal[];
    por_fase: DesglosePersonal[];
    porProcedimiento: ProcedimientoDePersona[];
    historial: PaginadoHistorialPersona;
    etiquetasFase: Record<string, string>;
    minimoParaComparar: number;
    periodo: Periodo;
    periodoEtiqueta: string;
}) {
    const url = `/costeo/personal/${persona.id}`;

    return (
        <>
            <Head title={`${persona.nombre} · Costeo`} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={persona.nombre}
                        description={[
                            persona.rol,
                            persona.especialidad,
                            `costo/minuto actual ${cop(persona.costo_por_minuto_actual)}`,
                            `costo mensual ${cop(persona.costo_mensual_actual)}`,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    />
                    <div className="flex items-center gap-2">
                        {!persona.activo && (
                            <Badge variant="outline">Inactivo</Badge>
                        )}
                        <Button asChild variant="outline">
                            <Link href="/costeo/personal">
                                <ArrowLeft className="size-4" />
                                Volver
                            </Link>
                        </Button>
                    </div>
                </div>

                <SelectorPeriodo
                    url={url}
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                />

                <div className="grid gap-[18px] md:grid-cols-3 xl:grid-cols-5">
                    <KpiCard
                        titulo="Cirugías"
                        valor={String(persona.n_cirugias)}
                        detalle={`${persona.n_participaciones} participaciones registradas`}
                    />
                    <KpiCard
                        titulo="Tiempo acumulado"
                        valor={`${persona.minutos_total.toLocaleString('es-CO')} min`}
                        detalle={
                            persona.minutos_promedio !== null
                                ? `${persona.minutos_promedio} min por cirugía`
                                : undefined
                        }
                    />
                    <KpiCard
                        titulo="Costo propio (su tiempo)"
                        valor={cop(persona.costo_propio_total)}
                        detalle={`${cop(persona.costo_propio_promedio)} por cirugía`}
                    />
                    <KpiCard
                        titulo="Costo inducido (como cirujano)"
                        valor={cop(persona.costo_inducido_total)}
                        detalle={
                            persona.n_como_cirujano > 0
                                ? `${persona.n_como_cirujano} cirugías · ${cop(persona.costo_inducido_promedio)} c/u`
                                : 'no figura como cirujano en el periodo'
                        }
                    />
                    <KpiCard
                        titulo="Índice vs. su procedimiento"
                        valor={
                            persona.indice_costo !== null
                                ? `${persona.indice_costo.toFixed(2)}×`
                                : '—'
                        }
                        detalle={
                            persona.n_comparables > 0
                                ? `sobre ${persona.n_comparables} cirugías comparables${
                                      persona.indice_duracion !== null
                                          ? ` · duración ${persona.indice_duracion.toFixed(2)}×`
                                          : ''
                                  }`
                                : `sin procedimientos con ${minimoParaComparar}+ cirugías costeadas`
                        }
                    />
                </div>

                <div className="grid gap-[18px] lg:grid-cols-2">
                    <TablaDesglose
                        titulo="Por rol desempeñado"
                        descripcion="En qué papel gastó sus minutos."
                        filas={por_rol}
                    />
                    <TablaDesglose
                        titulo="Por fase del ciclo"
                        descripcion="Preparar, operar o recuperar al paciente."
                        filas={por_fase}
                        etiquetas={etiquetasFase}
                    />
                </div>

                {porProcedimiento.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Sus procedimientos frente al promedio del
                                hospital
                            </CardTitle>
                            <CardDescription>
                                Solo cirugías donde figura como cirujano. El
                                índice compara el costo de sus cirugías contra
                                el promedio del mismo procedimiento, así que la
                                complejidad del caso no lo penaliza.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="pb-2 font-medium">
                                            Procedimiento
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Cirugías
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Costo promedio suyo
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Promedio del hospital
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Índice
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {porProcedimiento.map((fila) => (
                                        <tr
                                            key={fila.procedimiento.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2">
                                                <Link
                                                    href={`/costeo/procedimientos/${fila.procedimiento.id}`}
                                                    className="hover:underline"
                                                >
                                                    {fila.procedimiento.nombre}
                                                </Link>
                                                <span className="block font-mono text-xs text-muted-foreground">
                                                    {
                                                        fila.procedimiento
                                                            .codigo_cups
                                                    }
                                                </span>
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {fila.n}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(fila.costo_promedio_suyo)}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(
                                                    fila.costo_promedio_hospital,
                                                )}
                                            </td>
                                            <td className="py-2 text-right">
                                                <IndiceBadge
                                                    valor={fila.indice_costo}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                <div>
                    <h2 className="mb-2 text-base font-medium">
                        Histórico de operaciones
                    </h2>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <th className="p-3 font-medium">Fecha</th>
                                    <th className="p-3 font-medium">
                                        Procedimiento
                                    </th>
                                    <th className="p-3 font-medium">Rol</th>
                                    <th className="p-3 font-medium">Fase</th>
                                    <th className="p-3 text-right font-medium">
                                        Sus minutos
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Duración cirugía
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Costo propio
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Costo cirugía
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Índice
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {historial.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="p-6 text-center text-muted-foreground"
                                        >
                                            Sin participaciones registradas en
                                            el periodo.
                                        </td>
                                    </tr>
                                )}
                                {historial.data.map((fila) => (
                                    <tr
                                        key={`${fila.cirugia_id}-${fila.rol}-${fila.fase}`}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 tabular-nums">
                                            {fila.fecha}
                                        </td>
                                        <td className="p-3">
                                            {fila.procedimiento ? (
                                                <Link
                                                    href={`/costeo/procedimientos/${fila.procedimiento.id}/cirugias/${fila.cirugia_id}`}
                                                    className="hover:underline"
                                                >
                                                    {fila.procedimiento.nombre}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Sin procedimiento principal
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-3 capitalize">
                                            {fila.rol}
                                        </td>
                                        <td className="p-3">
                                            {etiquetasFase[fila.fase] ??
                                                fila.fase}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {fila.minutos} min
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {fila.duracion_cirugia !== null
                                                ? `${fila.duracion_cirugia} min`
                                                : '—'}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {cop(fila.costo_propio)}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {fila.costo_total_cirugia !==
                                            null ? (
                                                cop(fila.costo_total_cirugia)
                                            ) : (
                                                <Badge variant="outline">
                                                    Sin costear
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="p-3 text-right">
                                            <IndiceBadge
                                                valor={fila.indice_costo}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <Paginacion
                    links={historial.links}
                    total={historial.total}
                    from={historial.from}
                    to={historial.to}
                />
            </div>
        </>
    );
}

PersonalCosteoShow.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Personal', href: '/costeo/personal' },
        { title: 'Ficha de la persona', href: '#' },
    ],
};
