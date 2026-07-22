import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { IndiceBadge } from '@/components/costeo/indice-badge';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import { SelectorPeriodo } from '@/components/costeo/selector-periodo';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cop } from '@/lib/formato';
import type {
    FiltrosPersonalCosteo,
    PersonalCosteoFila,
    TotalesPersonal,
} from '@/types/costeo';

const TODOS = 'todos';

type Columna = {
    clave: keyof PersonalCosteoFila;
    titulo: string;
    numerica?: boolean;
};

const columnas: Columna[] = [
    { clave: 'nombre', titulo: 'Persona' },
    { clave: 'rol', titulo: 'Rol' },
    { clave: 'n_cirugias', titulo: 'Cirugías', numerica: true },
    { clave: 'minutos_total', titulo: 'Minutos', numerica: true },
    { clave: 'minutos_promedio', titulo: 'Min/cirugía', numerica: true },
    { clave: 'costo_propio_total', titulo: 'Costo propio', numerica: true },
    {
        clave: 'costo_propio_promedio',
        titulo: 'Propio/cirugía',
        numerica: true,
    },
    { clave: 'n_como_cirujano', titulo: 'Como cirujano', numerica: true },
    { clave: 'costo_inducido_total', titulo: 'Costo inducido', numerica: true },
    {
        clave: 'costo_inducido_promedio',
        titulo: 'Inducido/cirugía',
        numerica: true,
    },
    { clave: 'indice_costo', titulo: 'Índice', numerica: true },
];

/** Los nulos siempre al final, sea cual sea el sentido del orden. */
function comparar(
    a: PersonalCosteoFila,
    b: PersonalCosteoFila,
    clave: keyof PersonalCosteoFila,
    ascendente: boolean,
): number {
    const va = a[clave];
    const vb = b[clave];

    if (va === vb) {
        return 0;
    }

    if (va === null || va === undefined) {
        return 1;
    }

    if (vb === null || vb === undefined) {
        return -1;
    }

    const signo = ascendente ? 1 : -1;

    return typeof va === 'string' && typeof vb === 'string'
        ? signo * va.localeCompare(vb, 'es')
        : signo * (Number(va) - Number(vb));
}

export default function PersonalCosteoIndex({
    personal,
    totales,
    filtros,
    roles,
    minimoParaComparar,
    periodo,
    periodoEtiqueta,
}: {
    personal: PersonalCosteoFila[];
    totales: TotalesPersonal;
    filtros: FiltrosPersonalCosteo;
    roles: string[];
    minimoParaComparar: number;
    periodo: Periodo;
    periodoEtiqueta: string;
}) {
    const [q, setQ] = useState(filtros.q);
    const [orden, setOrden] = useState<{
        clave: keyof PersonalCosteoFila;
        ascendente: boolean;
    }>({ clave: 'costo_propio_total', ascendente: false });
    const primeraCarga = useRef(true);

    const aplicar = (parcial: Partial<FiltrosPersonalCosteo>) => {
        const datos = { ...filtros, q, ...parcial, ...periodo };
        const query = Object.fromEntries(
            Object.entries(datos).filter(([, v]) => v !== '' && v !== null),
        );
        router.get('/costeo/personal', query, {
            preserveState: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (primeraCarga.current) {
            primeraCarga.current = false;

            return;
        }

        const temporizador = setTimeout(() => aplicar({ q }), 350);

        return () => clearTimeout(temporizador);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const ordenar = (clave: keyof PersonalCosteoFila) =>
        setOrden((actual) =>
            actual.clave === clave
                ? { clave, ascendente: !actual.ascendente }
                : { clave, ascendente: false },
        );

    const filas = [...personal].sort((a, b) =>
        comparar(a, b, orden.clave, orden.ascendente),
    );

    const hayFiltros = filtros.q !== '' || filtros.rol !== '';

    return (
        <>
            <Head title="Personal · Costeo" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Costo por persona"
                    description="Cuánto le cuesta al hospital cada miembro del equipo quirúrgico y cuánto gasto moviliza. Entra a una persona para ver el histórico de tiempos de sus operaciones."
                />

                <SelectorPeriodo
                    url="/costeo/personal"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                />

                <div className="grid gap-[18px] md:grid-cols-3">
                    <KpiCard
                        titulo="Personal con actividad"
                        valor={String(totales.n_personas_con_actividad)}
                        detalle="con al menos una cirugía en el periodo"
                    />
                    <KpiCard
                        titulo="Costo total del talento humano"
                        valor={cop(totales.costo_propio_total)}
                        detalle="suma del costo TDABC de sus minutos"
                    />
                    <KpiCard
                        titulo="Minutos registrados"
                        valor={totales.minutos_total.toLocaleString('es-CO')}
                        detalle="tiempo de participación acumulado"
                    />
                </div>

                <div className="rounded-lg border border-dashed p-3 text-[13px] text-muted-foreground">
                    <strong className="font-medium text-foreground">
                        Cómo leer estas cifras.
                    </strong>{' '}
                    El <em>costo propio</em> es el de sus minutos: lo que la
                    persona le cuesta al hospital, y es sumable entre personas.
                    El <em>costo inducido</em> es el costo completo de las
                    cirugías donde figura como cirujano —sala, insumos, equipos
                    y el resto del equipo incluidos—; mide lo que moviliza, se
                    le atribuye íntegro a cada cirujano de la cirugía y por eso{' '}
                    <em>no</em> es sumable entre personas. El <em>índice</em>{' '}
                    compara cada cirugía contra el promedio de su mismo
                    procedimiento, de modo que operar casos complejos no
                    penaliza; solo se calcula sobre procedimientos con al menos{' '}
                    {minimoParaComparar} cirugías costeadas.
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar por nombre o especialidad…"
                            className="pl-8"
                            aria-label="Buscar persona"
                        />
                    </div>
                    <Select
                        value={filtros.rol || TODOS}
                        onValueChange={(v) =>
                            aplicar({ rol: v === TODOS ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label="Filtrar por rol"
                        >
                            <SelectValue placeholder="Rol" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>
                                Todos los roles
                            </SelectItem>
                            {roles.map((rol) => (
                                <SelectItem
                                    key={rol}
                                    value={rol}
                                    className="capitalize"
                                >
                                    {rol}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {hayFiltros && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setQ('');
                                aplicar({ q: '', rol: '' });
                            }}
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
                                {columnas.map((columna) => (
                                    <th
                                        key={columna.clave}
                                        className={`p-3 font-medium ${columna.numerica ? 'text-right' : ''}`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() =>
                                                ordenar(columna.clave)
                                            }
                                            className="inline-flex items-center gap-1 hover:text-foreground"
                                        >
                                            {columna.titulo}
                                            {orden.clave === columna.clave &&
                                                (orden.ascendente ? (
                                                    <ArrowUp className="size-3" />
                                                ) : (
                                                    <ArrowDown className="size-3" />
                                                ))}
                                        </button>
                                    </th>
                                ))}
                                <th className="p-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={columnas.length + 1}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        {hayFiltros
                                            ? 'Nadie coincide con los filtros.'
                                            : 'Aún no hay personal registrado en este hospital.'}
                                    </td>
                                </tr>
                            )}
                            {filas.map((persona) => (
                                <tr
                                    key={persona.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3">
                                        <Link
                                            href={`/costeo/personal/${persona.id}`}
                                            prefetch
                                            className="font-medium hover:underline"
                                        >
                                            {persona.nombre}
                                        </Link>
                                        {persona.especialidad && (
                                            <span className="block text-xs text-muted-foreground">
                                                {persona.especialidad}
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-3 capitalize">
                                        {persona.rol}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {persona.n_cirugias}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {persona.minutos_total.toLocaleString(
                                            'es-CO',
                                        )}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {persona.minutos_promedio !== null
                                            ? `${persona.minutos_promedio} min`
                                            : '—'}
                                    </td>
                                    <td className="p-3 text-right font-medium tabular-nums">
                                        {cop(persona.costo_propio_total)}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {cop(persona.costo_propio_promedio)}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {persona.n_como_cirujano}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {cop(persona.costo_inducido_total)}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {cop(persona.costo_inducido_promedio)}
                                    </td>
                                    <td className="p-3 text-right">
                                        <IndiceBadge
                                            valor={persona.indice_costo}
                                        />
                                    </td>
                                    <td className="p-3 text-right">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Ver ficha de costo de la persona"
                                        >
                                            <Link
                                                href={`/costeo/personal/${persona.id}`}
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
            </div>
        </>
    );
}

PersonalCosteoIndex.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Personal', href: '/costeo/personal' },
    ],
};
