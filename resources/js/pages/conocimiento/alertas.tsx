import { Head, Link, router, useForm } from '@inertiajs/react';
import { BookPlus, Check, ExternalLink, X } from 'lucide-react';
import type { CasoAlerta } from '@/components/alertas/filtro-caso';
import { FiltroCaso } from '@/components/alertas/filtro-caso';
import { AreaTexto } from '@/components/conocimiento/area-texto';
import { SeveridadBadge } from '@/components/conocimiento/severidad-badge';
import { KpiCard } from '@/components/costeo/kpi-card';
import InputError from '@/components/input-error';
import { NombreDato } from '@/components/nombre-dato';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cop, etiqueta, fechaHora } from '@/lib/formato';
import type {
    AlertaConocimiento,
    CausaCatalogo,
    Opcion,
    Paginado,
} from '@/types/conocimiento';

type Filtros = {
    estado: string;
    tipo: string;
    severidad: string;
    procedimiento: string;
    mes: string;
};

const SEVERIDADES: Opcion[] = [
    { valor: 'critica', etiqueta: 'Crítica' },
    { valor: 'advertencia', etiqueta: 'Advertencia' },
    { valor: 'informativa', etiqueta: 'Informativa' },
];

export default function AlertasConocimiento({
    alertas,
    filtros,
    tipos,
    estados,
    causas,
    resumen,
    caso,
}: {
    alertas: Paginado<AlertaConocimiento>;
    filtros: Filtros;
    tipos: Opcion[];
    estados: Opcion[];
    causas: CausaCatalogo[];
    resumen: {
        pendientes: number;
        impacto_pendiente: number;
        criticas: number;
    };
    caso: CasoAlerta;
}) {
    const filtrar = (cambios: Partial<Filtros>) =>
        router.get(
            '/conocimiento/alertas',
            Object.fromEntries(
                Object.entries({ ...filtros, ...cambios }).filter(
                    ([, v]) => v !== '',
                ),
            ),
            { preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Alertas de las reglas" />
            <div className="flex flex-col gap-4 p-4">
                <FiltroCaso
                    caso={caso}
                    hrefSinFiltro={`/conocimiento/alertas?estado=${filtros.estado}`}
                />
                <div className="grid gap-3 md:grid-cols-3">
                    <KpiCard
                        titulo="Pendientes"
                        valor={String(resumen.pendientes)}
                        detalle="Hallazgos de las reglas sin revisar"
                    />
                    <KpiCard
                        titulo="Críticas"
                        valor={String(resumen.criticas)}
                        detalle="Sobrecosto alto, margen negativo, complicaciones"
                    />
                    <KpiCard
                        titulo="Impacto pendiente"
                        valor={cop(resumen.impacto_pendiente)}
                        detalle="Pesos en juego que nadie ha explicado"
                    />
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-wrap gap-1">
                        {[
                            { valor: 'todas', etiqueta: 'Todas' },
                            ...estados,
                        ].map((o) => (
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
                        ))}
                    </div>
                    <Selector
                        etiqueta="Regla"
                        valor={filtros.tipo}
                        opciones={tipos}
                        alCambiar={(tipo) => filtrar({ tipo })}
                    />
                    <Selector
                        etiqueta="Severidad"
                        valor={filtros.severidad}
                        opciones={SEVERIDADES}
                        alCambiar={(severidad) => filtrar({ severidad })}
                    />
                    <Button asChild size="sm" variant="ghost">
                        <Link href="/conocimiento/reglas">Ajustar reglas</Link>
                    </Button>
                </div>

                {alertas.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            No hay alertas con estos filtros.
                        </CardContent>
                    </Card>
                ) : (
                    alertas.data.map((alerta) => (
                        <TarjetaAlerta
                            key={alerta.id}
                            alerta={alerta}
                            causas={causas}
                        />
                    ))
                )}

                <Paginacion
                    links={alertas.links}
                    total={alertas.total}
                    from={alertas.from}
                    to={alertas.to}
                />
            </div>
        </>
    );
}

function Selector({
    etiqueta: titulo,
    valor,
    opciones,
    alCambiar,
}: {
    etiqueta: string;
    valor: string;
    opciones: Opcion[];
    alCambiar: (v: string) => void;
}) {
    return (
        <div className="grid gap-1">
            <Label className="text-xs text-muted-foreground">{titulo}</Label>
            <Select
                value={valor || 'todas'}
                onValueChange={(v) => alCambiar(v === 'todas' ? '' : v)}
            >
                <SelectTrigger className="h-8 w-56">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="todas">Todas</SelectItem>
                    {opciones.map((o) => (
                        <SelectItem key={o.valor} value={o.valor}>
                            {o.etiqueta}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function TarjetaAlerta({
    alerta,
    causas,
}: {
    alerta: AlertaConocimiento;
    causas: CausaCatalogo[];
}) {
    const pendiente = alerta.estado === 'pendiente';
    const componentes = (alerta.datos?.componentes ?? []) as {
        componente: string;
        estandar: number;
        real: number;
        diferencia: number;
    }[];
    const insumos = (alerta.datos?.insumos ?? []) as {
        insumo_id: number;
        nombre: string | null;
        cantidad: number;
        costo: number;
    }[];

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-center gap-2">
                    <SeveridadBadge severidad={alerta.severidad} />
                    <Badge variant="outline">{alerta.tipo_nombre}</Badge>
                    {!pendiente && (
                        <Badge variant="secondary">
                            {etiqueta(alerta.estado)}
                        </Badge>
                    )}
                    {alerta.lecciones > 0 && (
                        <Badge variant="outline">
                            {alerta.lecciones} lección(es)
                        </Badge>
                    )}
                </div>
                <CardTitle className="text-base">
                    <NombreDato texto={alerta.titulo} />
                </CardTitle>
                <CardDescription>{alerta.descripcion}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <div className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    {alerta.impacto_cop !== null && (
                        <span>
                            Impacto:{' '}
                            <strong className="tabular-nums">
                                {cop(alerta.impacto_cop)}
                            </strong>
                        </span>
                    )}
                    {alerta.periodo && (
                        <span className="text-muted-foreground">
                            Periodo {alerta.periodo}
                        </span>
                    )}
                    {alerta.sala && (
                        <span className="text-muted-foreground">
                            {alerta.sala}
                        </span>
                    )}
                    {alerta.detectada_en && (
                        <span className="text-muted-foreground">
                            Detectada {fechaHora(alerta.detectada_en)}
                        </span>
                    )}
                </div>

                {componentes.length > 0 && (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-1 font-medium">Componente</th>
                                <th className="py-1 text-right font-medium">
                                    Estándar
                                </th>
                                <th className="py-1 text-right font-medium">
                                    Real
                                </th>
                                <th className="py-1 text-right font-medium">
                                    Diferencia
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {componentes.map((c) => (
                                <tr
                                    key={c.componente}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-1">
                                        {etiqueta(c.componente)}
                                    </td>
                                    <td className="py-1 text-right tabular-nums">
                                        {cop(c.estandar)}
                                    </td>
                                    <td className="py-1 text-right tabular-nums">
                                        {cop(c.real)}
                                    </td>
                                    <td
                                        className={`py-1 text-right tabular-nums ${c.diferencia > 0 ? 'text-[#9E3B3B]' : 'text-muted-foreground'}`}
                                    >
                                        {cop(c.diferencia)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                {insumos.length > 0 && (
                    <ul className="list-disc pl-5 text-sm text-muted-foreground">
                        {insumos.map((i) => (
                            <li key={i.insumo_id}>
                                {i.nombre} × {i.cantidad} · {cop(i.costo)}
                            </li>
                        ))}
                    </ul>
                )}

                {pendiente ? (
                    <FormularioRevision alerta={alerta} causas={causas} />
                ) : (
                    <div className="rounded-md border bg-muted/40 p-3 text-sm">
                        <p>
                            <strong>
                                {alerta.causa_etiqueta ??
                                    (alerta.estado === 'descartada'
                                        ? 'Descartada'
                                        : 'Revisada')}
                            </strong>
                        </p>
                        {alerta.comentario && (
                            <p className="mt-1 text-muted-foreground">
                                {alerta.comentario}
                            </p>
                        )}
                        <p className="mt-1 text-xs text-muted-foreground">
                            {alerta.revisor ?? '—'} ·{' '}
                            {alerta.revisado_en
                                ? fechaHora(alerta.revisado_en)
                                : ''}
                        </p>
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    <Button asChild size="sm" variant="outline">
                        <Link
                            href={`/conocimiento/lecciones/create?alerta_conocimiento=${alerta.id}`}
                        >
                            <BookPlus className="size-4" />
                            Escribir la lección
                        </Link>
                    </Button>
                    {alerta.cirugia_id && (
                        <Button asChild size="sm" variant="ghost">
                            <Link href={`/cirugias/${alerta.cirugia_id}`}>
                                <ExternalLink className="size-4" />
                                Ver la cirugía
                            </Link>
                        </Button>
                    )}
                    {alerta.procedimiento && (
                        <Button asChild size="sm" variant="ghost">
                            <Link
                                href={`/conocimiento/mejores-practicas/${alerta.procedimiento.id}`}
                            >
                                Mejores prácticas del procedimiento
                            </Link>
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function FormularioRevision({
    alerta,
    causas,
}: {
    alerta: AlertaConocimiento;
    causas: CausaCatalogo[];
}) {
    const { data, setData, patch, transform, processing, errors } = useForm({
        estado: 'revisada',
        causa: '',
        comentario: '',
    });

    const enviar = (estado: 'revisada' | 'descartada') => {
        transform((datos) => ({ ...datos, estado }));
        patch(`/conocimiento/alertas/${alerta.id}`, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                enviar('revisada');
            }}
            className="grid gap-3 rounded-md border p-3 md:grid-cols-2"
        >
            <div className="grid gap-1.5">
                <Label>Causa (opcional)</Label>
                <Select
                    value={data.causa}
                    onValueChange={(v) => setData('causa', v)}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="¿A qué se debe?" />
                    </SelectTrigger>
                    <SelectContent>
                        {causas.map((c) => (
                            <SelectItem key={c.valor} value={c.valor}>
                                {c.etiqueta}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.causa} />
            </div>
            <div className="grid gap-1.5">
                <Label>Comentario</Label>
                <AreaTexto
                    className="min-h-10"
                    value={data.comentario}
                    onChange={(e) => setData('comentario', e.target.value)}
                    placeholder="Qué se encontró o por qué se descarta"
                />
                <InputError message={errors.comentario} />
            </div>
            <div className="flex flex-wrap gap-2 md:col-span-2">
                <Button type="submit" size="sm" disabled={processing}>
                    <Check className="size-4" />
                    Marcar revisada
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={processing}
                    onClick={() => enviar('descartada')}
                >
                    <X className="size-4" />
                    Descartar
                </Button>
            </div>
        </form>
    );
}

AlertasConocimiento.layout = {
    breadcrumbs: [
        { title: 'Alertas', href: '/alertas' },
        { title: 'Alertas de las reglas', href: '/conocimiento/alertas' },
    ],
};
