import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, RefreshCw, ThumbsDown, ThumbsUp } from 'lucide-react';
import { useState } from 'react';
import { AreaTexto } from '@/components/conocimiento/area-texto';
import { KpiCard } from '@/components/costeo/kpi-card';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cop, etiqueta, fecha, fechaHora, numero } from '@/lib/formato';
import type { Opcion, Paginado, Recomendacion } from '@/types/conocimiento';

type Estado = Opcion & { n: number; ahorro: number };

export default function Recomendaciones({
    recomendaciones,
    filtros,
    tipos,
    estados,
    responsables,
}: {
    recomendaciones: Paginado<Recomendacion>;
    filtros: { estado: string; tipo: string };
    tipos: Opcion[];
    estados: Estado[];
    responsables: { id: number; name: string }[];
}) {
    const filtrar = (cambios: Partial<typeof filtros>) =>
        router.get(
            '/conocimiento/recomendaciones',
            { ...filtros, ...cambios },
            { preserveScroll: true, replace: true },
        );
    const de = (estado: string) => estados.find((e) => e.valor === estado);

    return (
        <>
            <Head title="Recomendaciones" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Recomendaciones
                        </h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Acciones que el histórico de los últimos seis meses
                            permite sostener, con su ahorro mensual estimado y
                            la evidencia detrás. Se regeneran al pedirlo; las
                            que ya se gestionaron conservan la evidencia con la
                            que se decidieron.
                        </p>
                    </div>
                    <Button
                        onClick={() =>
                            router.post(
                                '/conocimiento/recomendaciones/generar',
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <RefreshCw className="size-4" />
                        Analizar el histórico
                    </Button>
                </div>

                <div className="grid gap-3 md:grid-cols-3">
                    <KpiCard
                        titulo="Ahorro potencial"
                        valor={`${cop(de('nueva')?.ahorro ?? 0)}/mes`}
                        detalle={`${de('nueva')?.n ?? 0} recomendaciones nuevas`}
                    />
                    <KpiCard
                        titulo="En implementación"
                        valor={`${cop(de('aceptada')?.ahorro ?? 0)}/mes`}
                        detalle={`${de('aceptada')?.n ?? 0} aceptadas con responsable`}
                    />
                    <KpiCard
                        titulo="Implementado"
                        valor={`${cop(de('implementada')?.ahorro ?? 0)}/mes`}
                        detalle={`${de('implementada')?.n ?? 0} cerradas · ${de('descartada')?.n ?? 0} descartadas`}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {[{ valor: 'todas', etiqueta: 'Todas' }, ...estados].map(
                        (o) => (
                            <Button
                                key={o.valor}
                                size="sm"
                                variant={
                                    filtros.estado === o.valor
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => filtrar({ estado: o.valor })}
                            >
                                {o.etiqueta}
                            </Button>
                        ),
                    )}
                    <Select
                        value={filtros.tipo || 'todos'}
                        onValueChange={(v) =>
                            filtrar({ tipo: v === 'todos' ? '' : v })
                        }
                    >
                        <SelectTrigger className="h-8 w-60">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">
                                Todos los tipos
                            </SelectItem>
                            {tipos.map((t) => (
                                <SelectItem key={t.valor} value={t.valor}>
                                    {t.etiqueta}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {recomendaciones.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            No hay recomendaciones en este estado.
                        </CardContent>
                    </Card>
                ) : (
                    recomendaciones.data.map((r) => (
                        <TarjetaRecomendacion
                            key={r.id}
                            recomendacion={r}
                            responsables={responsables}
                        />
                    ))
                )}

                <Paginacion
                    links={recomendaciones.links}
                    total={recomendaciones.total}
                    from={recomendaciones.from}
                    to={recomendaciones.to}
                />
            </div>
        </>
    );
}

function TarjetaRecomendacion({
    recomendacion: r,
    responsables,
}: {
    recomendacion: Recomendacion;
    responsables: { id: number; name: string }[];
}) {
    const [accion, setAccion] = useState<'aceptar' | 'descartar' | null>(null);

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{r.tipo_etiqueta}</Badge>
                    <Badge
                        variant={r.estado === 'nueva' ? 'default' : 'secondary'}
                    >
                        {r.estado_etiqueta}
                    </Badge>
                    {r.ahorro_mensual_estimado > 0 && (
                        <span className="text-sm font-semibold text-[#4C837C] tabular-nums">
                            {cop(r.ahorro_mensual_estimado)}/mes
                        </span>
                    )}
                </div>
                <CardTitle className="text-base">{r.titulo}</CardTitle>
                <CardDescription>{r.descripcion}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {r.evidencia && <Evidencia evidencia={r.evidencia} />}

                {r.estado !== 'nueva' && (
                    <p className="text-xs text-muted-foreground">
                        {r.responsable && `Responsable: ${r.responsable} · `}
                        {r.fecha_compromiso &&
                            `Compromiso: ${fecha(r.fecha_compromiso)} · `}
                        {r.implementada_en &&
                            `Implementada: ${fecha(r.implementada_en)} · `}
                        {r.gestionada_en &&
                            `Gestionada ${fechaHora(r.gestionada_en)}`}
                        {r.nota && (
                            <span className="block pt-1">Nota: {r.nota}</span>
                        )}
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    {r.estado === 'nueva' && (
                        <>
                            <Button
                                size="sm"
                                onClick={() => setAccion('aceptar')}
                            >
                                <ThumbsUp className="size-4" />
                                Aceptar
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setAccion('descartar')}
                            >
                                <ThumbsDown className="size-4" />
                                Descartar
                            </Button>
                        </>
                    )}
                    {r.estado === 'aceptada' && (
                        <Button
                            size="sm"
                            onClick={() =>
                                router.patch(
                                    `/conocimiento/recomendaciones/${r.id}`,
                                    { estado: 'implementada' },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCircle2 className="size-4" />
                            Marcar implementada
                        </Button>
                    )}
                    {r.procedimiento && (
                        <Button asChild size="sm" variant="ghost">
                            <Link
                                href={`/conocimiento/mejores-practicas/${r.procedimiento.id}`}
                            >
                                Ver el procedimiento
                            </Link>
                        </Button>
                    )}
                    {r.tipo === 'ajustar_protocolo' && r.procedimiento && (
                        <Button asChild size="sm" variant="ghost">
                            <Link
                                href={`/parametros/procedimientos/${r.procedimiento.id}/plantilla`}
                            >
                                Editar el protocolo
                            </Link>
                        </Button>
                    )}
                    <Button asChild size="sm" variant="ghost">
                        <Link
                            href={`/conocimiento/comunidades?enlazable_tipo=recomendacion&enlazable_id=${r.id}`}
                        >
                            Discutir
                        </Link>
                    </Button>
                </div>

                {accion && (
                    <Gestion
                        recomendacion={r}
                        accion={accion}
                        responsables={responsables}
                        alTerminar={() => setAccion(null)}
                    />
                )}
            </CardContent>
        </Card>
    );
}

function Gestion({
    recomendacion,
    accion,
    responsables,
    alTerminar,
}: {
    recomendacion: Recomendacion;
    accion: 'aceptar' | 'descartar';
    responsables: { id: number; name: string }[];
    alTerminar: () => void;
}) {
    const { data, setData, patch, processing, errors } = useForm({
        estado: accion === 'aceptar' ? 'aceptada' : 'descartada',
        responsable_id: '',
        fecha_compromiso: '',
        nota: '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                patch(`/conocimiento/recomendaciones/${recomendacion.id}`, {
                    preserveScroll: true,
                    onSuccess: alTerminar,
                });
            }}
            className="grid gap-3 rounded-md border p-3 sm:grid-cols-2"
        >
            {accion === 'aceptar' && (
                <>
                    <div className="grid gap-1.5">
                        <Label>Responsable</Label>
                        <Select
                            value={data.responsable_id}
                            onValueChange={(v) => setData('responsable_id', v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Quién la lleva" />
                            </SelectTrigger>
                            <SelectContent>
                                {responsables.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.responsable_id} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Fecha de compromiso</Label>
                        <Input
                            type="date"
                            value={data.fecha_compromiso}
                            onChange={(e) =>
                                setData('fecha_compromiso', e.target.value)
                            }
                        />
                        <InputError message={errors.fecha_compromiso} />
                    </div>
                </>
            )}
            <div className="grid gap-1.5 sm:col-span-2">
                <Label>
                    {accion === 'aceptar'
                        ? 'Nota (opcional)'
                        : 'Motivo del descarte'}
                </Label>
                <AreaTexto
                    className="min-h-14"
                    value={data.nota}
                    onChange={(e) => setData('nota', e.target.value)}
                />
                <InputError message={errors.nota} />
            </div>
            <div className="flex gap-2 sm:col-span-2">
                <Button size="sm" disabled={processing}>
                    {accion === 'aceptar' ? 'Aceptar' : 'Descartar'}
                </Button>
                <Button
                    size="sm"
                    type="button"
                    variant="ghost"
                    onClick={alTerminar}
                >
                    Cancelar
                </Button>
            </div>
        </form>
    );
}

/** La evidencia llega como pares clave → valor; los pesos se formatean. */
function Evidencia({ evidencia }: { evidencia: Record<string, unknown> }) {
    const entradas = Object.entries(evidencia).filter(
        (par): par is [string, number | string] =>
            typeof par[1] === 'number' || typeof par[1] === 'string',
    );
    const listas = Object.entries(evidencia).filter(([, v]) =>
        Array.isArray(v),
    );
    const esPeso = (clave: string) =>
        /costo|tarifa|precio|minuto$/.test(clave) &&
        !clave.includes('duracion');

    return (
        <div className="rounded-md bg-muted/40 p-3 text-xs">
            <p className="mb-1 font-semibold tracking-[1px] text-[#5B687C] uppercase">
                Evidencia
            </p>
            <div className="grid gap-x-6 gap-y-1 sm:grid-cols-3">
                {entradas.map(([clave, valor]) => (
                    <div key={clave}>
                        <span className="text-muted-foreground">
                            {etiqueta(clave)}:{' '}
                        </span>
                        <span className="tabular-nums">
                            {typeof valor === 'number'
                                ? esPeso(clave)
                                    ? cop(valor)
                                    : clave === 'utilizacion' ||
                                        clave === 'indice'
                                      ? valor.toLocaleString('es-CO')
                                      : numero(valor)
                                : valor}
                        </span>
                    </div>
                ))}
            </div>
            {listas.map(([clave, valor]) =>
                (valor as { insumo: string; frecuencia: number }[]).length >
                0 ? (
                    <p key={clave} className="mt-1">
                        <span className="text-muted-foreground">
                            {etiqueta(clave)}:{' '}
                        </span>
                        {(valor as { insumo: string; frecuencia: number }[])
                            .map(
                                (i) =>
                                    `${i.insumo} (${Math.round(i.frecuencia * 100)} %)`,
                            )
                            .join('; ')}
                    </p>
                ) : null,
            )}
        </div>
    );
}

Recomendaciones.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento' },
        { title: 'Recomendaciones', href: '/conocimiento/recomendaciones' },
    ],
};
