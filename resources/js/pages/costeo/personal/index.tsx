import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { IndiceBadge } from '@/components/costeo/indice-badge';
import { KpiCard } from '@/components/costeo/kpi-card';
import type { Periodo } from '@/components/costeo/selector-periodo';
import {
    periodoDeLaUrl,
    SelectorPeriodo,
} from '@/components/costeo/selector-periodo';
import { NombreDato } from '@/components/nombre-dato';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cop, etiqueta, minutos, numero } from '@/lib/formato';
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
    /** Solo con «Ver todas las columnas»: la tabla tenía 12 columnas. */
    detallada?: boolean;
    ayuda?: string;
    celda: (persona: PersonalCosteoFila) => ReactNode;
};

const columnas: Columna[] = [
    {
        clave: 'nombre',
        titulo: 'Persona',
        celda: (persona) => (
            <>
                <Link
                    href={`/costeo/personal/${persona.id}`}
                    prefetch
                    className="font-medium hover:underline"
                >
                    <NombreDato texto={persona.nombre} />
                </Link>
                {persona.especialidad && (
                    <span className="block text-xs text-muted-foreground">
                        {persona.especialidad}
                    </span>
                )}
            </>
        ),
    },
    { clave: 'rol', titulo: 'Rol', celda: (p) => etiqueta(p.rol) },
    {
        clave: 'n_cirugias',
        titulo: 'Cirugías',
        numerica: true,
        celda: (p) => numero(p.n_cirugias),
    },
    {
        clave: 'minutos_total',
        titulo: 'Minutos',
        numerica: true,
        detallada: true,
        celda: (p) => numero(p.minutos_total),
    },
    {
        clave: 'minutos_promedio',
        titulo: 'Min/cirugía',
        numerica: true,
        celda: (p) => minutos(p.minutos_promedio),
    },
    {
        clave: 'costo_propio_total',
        titulo: 'Costo propio',
        numerica: true,
        ayuda: 'Lo que cuestan sus minutos al hospital; sumable entre personas',
        celda: (p) => (
            <span className="font-medium">{cop(p.costo_propio_total)}</span>
        ),
    },
    {
        clave: 'costo_propio_promedio',
        titulo: 'Propio/cirugía',
        numerica: true,
        celda: (p) => cop(p.costo_propio_promedio),
    },
    {
        clave: 'n_como_cirujano',
        titulo: 'Como cirujano',
        numerica: true,
        detallada: true,
        celda: (p) => numero(p.n_como_cirujano),
    },
    {
        clave: 'costo_inducido_total',
        titulo: 'Costo inducido',
        numerica: true,
        detallada: true,
        ayuda: 'Costo completo de las cirugías donde figura como cirujano; no es sumable entre personas',
        celda: (p) => cop(p.costo_inducido_total),
    },
    {
        clave: 'costo_inducido_promedio',
        titulo: 'Inducido/cirugía',
        numerica: true,
        detallada: true,
        celda: (p) => cop(p.costo_inducido_promedio),
    },
    {
        clave: 'indice_costo',
        titulo: 'Índice',
        numerica: true,
        ayuda: 'Costo de sus cirugías frente al promedio del mismo procedimiento (1,00× = igual)',
        celda: (p) => <IndiceBadge valor={p.indice_costo} />,
    },
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
    const [detallada, setDetallada] = useState(false);
    const primeraCarga = useRef(true);
    const periodoUrl = periodoDeLaUrl(usePage().url);
    const visibles = columnas.filter((c) => detallada || !c.detallada);

    const aplicar = (parcial: Partial<FiltrosPersonalCosteo>) => {
        const datos = { ...filtros, q, ...parcial, ...periodoUrl };
        const query = Object.fromEntries(
            Object.entries(datos).filter(([, v]) => v !== ''),
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
                <EncabezadoCosteo
                    titulo="Costo por persona"
                    descripcion="Cuánto le cuesta al hospital cada miembro del equipo quirúrgico y cuánto gasto moviliza. Entra a una persona para ver el histórico de tiempos de sus operaciones."
                />

                <SelectorPeriodo
                    url="/costeo/personal"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                    conservar={Object.fromEntries(
                        Object.entries(filtros).filter(([, v]) => v !== ''),
                    )}
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
                        valor={numero(totales.minutos_total)}
                        detalle="tiempo de participación acumulado"
                    />
                </div>

                <details className="rounded-lg border border-dashed px-3 py-2 text-[13px] text-muted-foreground">
                    <summary className="cursor-pointer font-medium text-foreground">
                        Cómo leer estas cifras
                    </summary>
                    <p className="mt-2">
                        El <em>costo propio</em> es el de sus minutos: lo que la
                        persona le cuesta al hospital, y es sumable entre
                        personas. El <em>costo inducido</em> es el costo
                        completo de las cirugías donde figura como cirujano
                        —sala, insumos, equipos y el resto del equipo
                        incluidos—; mide lo que moviliza, se le atribuye íntegro
                        a cada cirujano de la cirugía y por eso <em>no</em> es
                        sumable entre personas. El <em>índice</em> compara cada
                        cirugía contra el promedio de su mismo procedimiento, de
                        modo que operar casos complejos no penaliza; solo se
                        calcula sobre procedimientos con al menos{' '}
                        {minimoParaComparar} cirugías costeadas.
                    </p>
                </details>

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
                                <SelectItem key={rol} value={rol}>
                                    {etiqueta(rol)}
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
                    <Button
                        variant="outline"
                        size="sm"
                        className="ml-auto"
                        aria-pressed={detallada}
                        onClick={() => setDetallada(!detallada)}
                    >
                        {detallada
                            ? 'Ver menos columnas'
                            : 'Ver todas las columnas'}
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                {visibles.map((columna) => (
                                    <th
                                        key={columna.clave}
                                        className={`p-3 font-medium ${columna.numerica ? 'text-right' : ''}`}
                                    >
                                        <button
                                            type="button"
                                            title={columna.ayuda}
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
                                        colSpan={visibles.length + 1}
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
                                    {visibles.map((columna) => (
                                        <td
                                            key={columna.clave}
                                            className={`p-3 ${columna.numerica ? 'text-right tabular-nums' : ''}`}
                                        >
                                            {columna.celda(persona)}
                                        </td>
                                    ))}
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
