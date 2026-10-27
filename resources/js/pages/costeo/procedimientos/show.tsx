import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Eye, X } from 'lucide-react';
import { BarraComposicion } from '@/components/costeo/barra-composicion';
import { DispersionProcedimiento } from '@/components/costeo/dispersion-procedimiento';
import { KpiCard } from '@/components/costeo/kpi-card';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    cop,
    etiqueta,
    fecha as formatearFecha,
    numero,
    pct,
    pctVariacion,
} from '@/lib/formato';
import { tonoMayorEsMejor, tonoMenorEsMejor, UMBRALES } from '@/lib/semaforo';
import type { Tono } from '@/lib/semaforo';
import { separarMarcaSemilla } from '@/lib/semilla';
import type {
    EstadisticasProcedimiento,
    FiltrosInstanciasCirugia,
    IndicadoresProcedimiento,
    PaginadoInstanciasCirugia,
    ProcedimientoCosteoInfo,
    PuntoSerieProcedimiento,
} from '@/types/costeo';

const TODOS = 'todos';

const TONO_VARIABILIDAD: Record<string, Tono> = {
    alta: 'alerta',
    media: 'atencion',
    baja: 'bien',
};

export default function ProcedimientoCosteoShow({
    procedimiento,
    estadisticas,
    indicadores,
    cirugias,
    serie,
    filtros,
    estados,
}: {
    procedimiento: ProcedimientoCosteoInfo;
    estadisticas: EstadisticasProcedimiento;
    indicadores: IndicadoresProcedimiento | null;
    cirugias: PaginadoInstanciasCirugia;
    serie: PuntoSerieProcedimiento[];
    filtros: FiltrosInstanciasCirugia;
    estados: string[];
}) {
    const aplicar = (parcial: Partial<FiltrosInstanciasCirugia>) => {
        const datos = { ...filtros, ...parcial };
        const query = Object.fromEntries(
            Object.entries(datos).filter(([, v]) => v !== ''),
        );
        router.get(`/costeo/procedimientos/${procedimiento.id}`, query, {
            preserveState: true,
            replace: true,
        });
    };

    const hayFiltros =
        filtros.desde !== '' || filtros.hasta !== '' || filtros.estado !== '';

    const nombre = separarMarcaSemilla(procedimiento.nombre).limpio;
    const ventana =
        filtros.desde || filtros.hasta
            ? `${filtros.desde ? formatearFecha(filtros.desde) : 'el inicio'} a ${filtros.hasta ? formatearFecha(filtros.hasta) : 'hoy'}`
            : 'toda la historia';

    return (
        <>
            <Head title={`${nombre} · Costeo`} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={nombre}
                        description={`CUPS ${procedimiento.codigo_cups} · ${procedimiento.especialidad} · complejidad ${procedimiento.complejidad} · duración estimada ${procedimiento.duracion_estimada_minutos} min`}
                    />
                    <Button asChild variant="outline">
                        <Link
                            href={`/costeo/procedimientos${filtros.desde || filtros.hasta ? `?${new URLSearchParams(Object.entries({ desde: filtros.desde, hasta: filtros.hasta }).filter(([, v]) => v !== '')).toString()}` : ''}`}
                        >
                            <ArrowLeft className="size-4" />
                            Volver
                        </Link>
                    </Button>
                </div>

                <p className="-mt-2 text-sm text-muted-foreground">
                    Indicadores de {ventana}
                    {indicadores === null &&
                        ': ninguna cirugía costeada en esas fechas'}
                    .
                </p>

                <div className="grid gap-[18px] sm:grid-cols-2 xl:grid-cols-5">
                    <KpiCard
                        titulo="Costo promedio"
                        valor={cop(indicadores?.costo_promedio)}
                        detalle={`${numero(indicadores?.n ?? 0)} cirugías costeadas${indicadores?.mediana != null ? ` · mediana ${cop(indicadores.mediana)}` : ''}`}
                    />
                    <KpiCard
                        titulo="Frente al estándar"
                        valor={
                            indicadores?.desviacion_pct != null
                                ? pctVariacion(indicadores.desviacion_pct)
                                : '—'
                        }
                        tono={tonoMenorEsMejor(
                            indicadores?.desviacion_pct,
                            UMBRALES.desviacionEstandar,
                        )}
                        detalle={
                            indicadores && indicadores.n_con_estandar > 0
                                ? `sobre lo que marca el protocolo · ${numero(indicadores.n_con_estandar)} cirugías`
                                : 'sin plantilla de protocolo: configúrela en Parámetros'
                        }
                    />
                    <KpiCard
                        titulo="Margen sobre lo facturado"
                        valor={
                            indicadores?.margen_pct != null
                                ? pct(indicadores.margen_pct)
                                : '—'
                        }
                        tono={tonoMayorEsMejor(
                            indicadores?.margen_pct,
                            UMBRALES.margen,
                        )}
                        detalle={
                            indicadores && indicadores.n_facturadas > 0
                                ? `resultado ${cop(indicadores.resultado)} · ${numero(indicadores.n_facturadas)} facturadas`
                                : 'sin cirugías facturadas'
                        }
                    />
                    <KpiCard
                        titulo="Variabilidad del costo"
                        valor={
                            indicadores?.coeficiente_variacion != null
                                ? pct(indicadores.coeficiente_variacion, 0)
                                : '—'
                        }
                        tono={
                            TONO_VARIABILIDAD[
                                indicadores?.nivel_variabilidad ?? ''
                            ] ?? 'neutro'
                        }
                        detalle={
                            indicadores
                                ? `${indicadores.nivel_variabilidad ?? 'sin dato'} · ${numero(indicadores.atipicos)} ${indicadores.atipicos === 1 ? 'cirugía atípica' : 'cirugías atípicas'}`
                                : undefined
                        }
                    />
                    <KpiCard
                        titulo="Duración promedio"
                        valor={
                            estadisticas.duracion_promedio_minutos !== null
                                ? `${estadisticas.duracion_promedio_minutos} min`
                                : '—'
                        }
                        detalle={`estimada en el protocolo: ${procedimiento.duracion_estimada_minutos} min`}
                    />
                </div>

                {indicadores && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                De qué está hecho el costo
                            </CardTitle>
                            <CardDescription>
                                Costo promedio por cirugía separado por
                                componente
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BarraComposicion
                                componentes={indicadores.componentes}
                            />
                        </CardContent>
                    </Card>
                )}

                <DispersionProcedimiento
                    serie={serie}
                    procedimientoId={procedimiento.id}
                    promedio={estadisticas.costo_promedio}
                />

                <div className="flex flex-wrap items-center gap-2">
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        Desde
                        <Input
                            type="date"
                            value={filtros.desde}
                            onChange={(e) => aplicar({ desde: e.target.value })}
                            className="w-40"
                            aria-label="Filtrar desde fecha"
                        />
                    </label>
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        Hasta
                        <Input
                            type="date"
                            value={filtros.hasta}
                            onChange={(e) => aplicar({ hasta: e.target.value })}
                            className="w-40"
                            aria-label="Filtrar hasta fecha"
                        />
                    </label>
                    <Select
                        value={filtros.estado || TODOS}
                        onValueChange={(v) =>
                            aplicar({ estado: v === TODOS ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-44"
                            aria-label="Filtrar por estado"
                        >
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>
                                Todos los estados
                            </SelectItem>
                            {estados.map((estado) => (
                                <SelectItem key={estado} value={estado}>
                                    {etiqueta(estado)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {hayFiltros && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                aplicar({ desde: '', hasta: '', estado: '' })
                            }
                        >
                            <X className="size-4" />
                            Limpiar filtros
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Fecha</th>
                                <th className="p-3 font-medium">Horario</th>
                                <th className="p-3 font-medium">Paciente</th>
                                <th className="p-3 font-medium">Sala</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">
                                    Duración
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Costo TDABC
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {cirugias.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        {hayFiltros
                                            ? 'Ninguna cirugía coincide con los filtros.'
                                            : 'Aún no hay cirugías registradas con este procedimiento como principal.'}
                                    </td>
                                </tr>
                            )}
                            {cirugias.data.map((cirugia) => (
                                <tr
                                    key={cirugia.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3 whitespace-nowrap tabular-nums">
                                        {formatearFecha(cirugia.fecha)}
                                    </td>
                                    <td className="p-3 tabular-nums">
                                        {cirugia.hora_inicio
                                            ? `${cirugia.hora_inicio} – ${cirugia.hora_fin ?? '¿?'}`
                                            : '—'}
                                    </td>
                                    <td className="p-3">
                                        {cirugia.paciente
                                            ? `${cirugia.paciente.nombres} ${cirugia.paciente.apellidos}`
                                            : '—'}
                                    </td>
                                    <td className="p-3">
                                        {cirugia.sala ?? '—'}
                                    </td>
                                    <td className="p-3">
                                        {etiqueta(cirugia.estado)}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {cirugia.duracion_minutos !== null
                                            ? `${cirugia.duracion_minutos} min`
                                            : '—'}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {cirugia.costo_total !== null ? (
                                            cop(Number(cirugia.costo_total))
                                        ) : (
                                            <Badge variant="outline">
                                                Sin costear
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="p-3 text-right">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Ver detalle de la cirugía"
                                        >
                                            <Link
                                                href={`/costeo/procedimientos/${procedimiento.id}/cirugias/${cirugia.id}`}
                                                prefetch
                                            >
                                                <Eye className="size-4" />
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion
                    links={cirugias.links}
                    total={cirugias.total}
                    from={cirugias.from}
                    to={cirugias.to}
                />
            </div>
        </>
    );
}

ProcedimientoCosteoShow.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Procedimientos', href: '/costeo/procedimientos' },
        { title: 'Detalle del procedimiento', href: '#' },
    ],
};
