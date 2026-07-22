import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Check, Search, X } from 'lucide-react';
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
import { cop, pct } from '@/lib/formato';
import type {
    AlertaSobrecosto,
    CausaCatalogo,
    PaginadoAlertas,
    ResumenAlertas,
} from '@/types/costeo';

type OpcionEstado = { valor: string; etiqueta: string };

export default function Alertas({
    alertas,
    filtros,
    estados,
    causas,
    resumen,
}: {
    alertas: PaginadoAlertas;
    filtros: { estado: string };
    estados: OpcionEstado[];
    causas: CausaCatalogo[];
    resumen: ResumenAlertas;
}) {
    const filtrar = (estado: string) =>
        router.get(
            '/costeo/alertas',
            { estado },
            { preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Alertas de sobrecosto" />
            <div className="flex flex-col gap-4 p-4">
                <div className="grid gap-3 md:grid-cols-4">
                    <KpiCard
                        titulo="Sin revisar"
                        valor={String(resumen.pendientes)}
                        detalle="Sobrecostos detectados que nadie ha explicado"
                    />
                    <KpiCard
                        titulo="Revisadas"
                        valor={String(resumen.revisadas)}
                        detalle="Con causa atribuida"
                    />
                    <KpiCard
                        titulo="Exceso detectado"
                        valor={cop(resumen.exceso_total)}
                        detalle="Sobre el costo habitual de cada procedimiento"
                    />
                    <KpiCard
                        titulo="Exceso evitable"
                        valor={cop(resumen.exceso_evitable)}
                        detalle="Lo que sí era gestionable, según las causas revisadas"
                    />
                </div>

                <p className="text-sm text-muted-foreground">
                    Cada alerta es una cirugía cuyo costo se salió del rango de
                    su procedimiento (z-score e IQR sobre las cirugías
                    anteriores del mismo CUPS). El exceso viene desglosado por
                    componente para no tener que reconstruir a mano de dónde
                    salió. Atribuirle una causa es lo que convierte el dato en
                    conocimiento: el «exceso evitable» solo se puede calcular
                    con alertas revisadas.
                </p>

                <div className="flex flex-wrap gap-1">
                    {[{ valor: 'todas', etiqueta: 'Todas' }, ...estados].map(
                        (opcion) => (
                            <Button
                                key={opcion.valor}
                                size="sm"
                                variant={
                                    filtros.estado === opcion.valor
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => filtrar(opcion.valor)}
                            >
                                {opcion.etiqueta}
                            </Button>
                        ),
                    )}
                </div>

                {alertas.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            No hay alertas en este estado. Con menos de 5
                            cirugías costeadas de un procedimiento no se generan
                            alertas: sin comparables, cualquier caso parecería
                            atípico.
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

                {resumen.por_causa.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Causas acumuladas
                            </CardTitle>
                            <CardDescription>
                                A qué se deben los sobrecostos del hospital, en
                                plata. Es la salida de este módulo: el ranking
                                dice dónde intervenir primero, y el marcado
                                «evitable» separa lo recuperable del costo
                                legítimo de un caso difícil.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2 font-medium">
                                            Causa
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Casos
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Exceso
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {resumen.por_causa.map((fila) => (
                                        <tr
                                            key={fila.causa}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2">
                                                {fila.etiqueta}{' '}
                                                {fila.evitable && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="ml-1"
                                                    >
                                                        evitable
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {fila.n}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {cop(fila.exceso)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function TarjetaAlerta({
    alerta,
    causas,
}: {
    alerta: AlertaSobrecosto;
    causas: CausaCatalogo[];
}) {
    const pendiente = alerta.estado === 'pendiente';

    // Las causas típicas del componente que dominó el exceso van primero: si
    // el sobrecosto fue de insumos, «desperdicio de insumos» debería estar a
    // la mano y no perdida entre nueve opciones en orden arbitrario.
    const causasOrdenadas = [
        ...causas.filter((c) => alerta.causas_sugeridas.includes(c.valor)),
        ...causas.filter((c) => !alerta.causas_sugeridas.includes(c.valor)),
    ];

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-center gap-2">
                    <CardTitle className="text-base">
                        {alerta.procedimiento.nombre ?? 'Procedimiento'}
                    </CardTitle>
                    <span className="font-mono text-xs text-muted-foreground">
                        {alerta.procedimiento.codigo_cups}
                    </span>
                    <Badge variant={pendiente ? 'destructive' : 'secondary'}>
                        {alerta.estado_etiqueta}
                    </Badge>
                    {alerta.criterios.map((criterio) => (
                        <Badge key={criterio} variant="outline">
                            {criterio === 'z' ? 'z-score' : 'IQR'}
                        </Badge>
                    ))}
                </div>
                <CardDescription>
                    Cirugía #{alerta.cirugia_id}
                    {alerta.fecha ? ` · ${alerta.fecha}` : ''} · comparada
                    contra {alerta.n_baseline} cirugías del mismo procedimiento
                    {alerta.z !== null ? ` · z = ${alerta.z}` : ''}
                </CardDescription>
            </CardHeader>

            <CardContent className="flex flex-col gap-4">
                <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                    <span className="text-sm text-muted-foreground">
                        Costó{' '}
                        <strong className="text-foreground tabular-nums">
                            {cop(alerta.costo_total)}
                        </strong>{' '}
                        frente a los{' '}
                        <strong className="text-foreground tabular-nums">
                            {cop(alerta.costo_esperado)}
                        </strong>{' '}
                        habituales
                    </span>
                    <span className="flex items-center gap-1 text-sm font-medium text-[#9E3B3B]">
                        <AlertTriangle className="size-4" />
                        {cop(alerta.exceso)} de más ({pct(alerta.exceso_pct, 0)}
                        )
                    </span>
                </div>

                <div>
                    <p className="mb-2 text-xs font-semibold tracking-[1px] text-[#5B687C] uppercase">
                        De dónde salió el exceso
                    </p>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-1.5 font-medium">
                                    Componente
                                </th>
                                <th className="py-1.5 text-right font-medium">
                                    Esta cirugía
                                </th>
                                <th className="py-1.5 text-right font-medium">
                                    Habitual
                                </th>
                                <th className="py-1.5 text-right font-medium">
                                    Diferencia
                                </th>
                                <th className="py-1.5 text-right font-medium">
                                    Aporte
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {alerta.atribucion.map((linea) => (
                                <tr
                                    key={linea.componente}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-1.5">
                                        {linea.etiqueta}
                                        {linea.componente ===
                                            alerta.componente_dominante && (
                                            <Badge
                                                variant="destructive"
                                                className="ml-2"
                                            >
                                                principal
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="py-1.5 text-right tabular-nums">
                                        {cop(linea.costo)}
                                    </td>
                                    <td className="py-1.5 text-right text-muted-foreground tabular-nums">
                                        {cop(linea.esperado)}
                                    </td>
                                    <td
                                        className={`py-1.5 text-right tabular-nums ${
                                            linea.exceso > 0
                                                ? 'text-[#9E3B3B]'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {linea.exceso > 0 ? '+' : ''}
                                        {cop(linea.exceso)}
                                    </td>
                                    <td className="py-1.5 text-right text-muted-foreground tabular-nums">
                                        {linea.exceso > 0
                                            ? pct(linea.aporte_pct, 0)
                                            : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {pendiente ? (
                    <FormularioRevision
                        alerta={alerta}
                        causas={causasOrdenadas}
                    />
                ) : (
                    <div className="rounded-md border bg-muted/40 p-3 text-sm">
                        <p>
                            <strong>
                                {alerta.causa_etiqueta ?? 'Sin causa'}
                            </strong>
                            {alerta.causa_evitable && (
                                <Badge variant="destructive" className="ml-2">
                                    evitable
                                </Badge>
                            )}
                        </p>
                        {alerta.causa_detalle && (
                            <p className="mt-1 text-muted-foreground">
                                {alerta.causa_detalle}
                            </p>
                        )}
                        <p className="mt-1 text-xs text-muted-foreground">
                            {alerta.revisor ?? 'Alguien'} ·{' '}
                            {alerta.revisado_en ?? ''}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function FormularioRevision({
    alerta,
    causas,
}: {
    alerta: AlertaSobrecosto;
    causas: CausaCatalogo[];
}) {
    const { data, setData, patch, transform, processing, errors } = useForm({
        estado: 'revisada',
        causa: '',
        causa_detalle: '',
    });

    const enviar = (estado: 'revisada' | 'descartada') => {
        // El destino se inyecta con `transform` y no con `setData`: un
        // `setData` seguido de `patch` en el mismo manejador envía el estado
        // anterior, porque React todavía no re-renderizó.
        transform((datos) => ({ ...datos, estado }));
        patch(`/costeo/alertas/${alerta.id}`, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                enviar('revisada');
            }}
            className="flex flex-col gap-3 rounded-md border p-3"
        >
            <p className="text-sm font-medium">
                ¿Por qué costó de más? Empiece por{' '}
                {alerta.componente_dominante_etiqueta.toLowerCase()}.
            </p>

            <div className="grid gap-2 md:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`causa-${alerta.id}`}>Causa</Label>
                    <Select
                        value={data.causa}
                        onValueChange={(v) => setData('causa', v)}
                    >
                        <SelectTrigger id={`causa-${alerta.id}`}>
                            <SelectValue placeholder="Seleccione la causa" />
                        </SelectTrigger>
                        <SelectContent>
                            {causas.map((causa) => (
                                <SelectItem
                                    key={causa.valor}
                                    value={causa.valor}
                                >
                                    {causa.etiqueta}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.causa} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor={`detalle-${alerta.id}`}>
                        Detalle (opcional)
                    </Label>
                    <Input
                        id={`detalle-${alerta.id}`}
                        value={data.causa_detalle}
                        onChange={(e) =>
                            setData('causa_detalle', e.target.value)
                        }
                        placeholder="¿Qué pasó exactamente?"
                    />
                    <InputError message={errors.causa_detalle} />
                </div>
            </div>

            <div className="flex flex-wrap gap-2">
                <Button type="submit" size="sm" disabled={processing}>
                    <Check className="size-4" />
                    Registrar causa
                </Button>
                {/* Descartar existe para el falso positivo —baseline aún
                    pobre, caso mal comparado— y por eso no pide causa: no
                    describe un sobrecosto real, así que tampoco suma al
                    exceso evitable. */}
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
                <Button type="button" size="sm" variant="ghost" asChild>
                    <a href={`/cirugias/${alerta.cirugia_id}`}>
                        <Search className="size-4" />
                        Ver la cirugía
                    </a>
                </Button>
            </div>
        </form>
    );
}

Alertas.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Alertas de sobrecosto', href: '/costeo/alertas' },
    ],
};
