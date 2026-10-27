import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    BarraComposicion,
    LeyendaComposicion,
} from '@/components/costeo/barra-composicion';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import type { Periodo } from '@/components/costeo/selector-periodo';
import {
    consultaDePeriodo,
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cop, numero, pct, pctVariacion } from '@/lib/formato';
import { COMPONENTES } from '@/lib/graficas';
import type { Tono } from '@/lib/semaforo';
import {
    TEXTO_TONO,
    tonoMayorEsMejor,
    tonoMenorEsMejor,
    UMBRALES,
} from '@/lib/semaforo';
import type {
    FiltrosProcedimientosCosteo,
    IndicadoresProcedimiento,
    ProcedimientoCosteoFila,
} from '@/types/costeo';

const TODOS = 'todos';

type Vista = 'indicadores' | 'composicion';

type ClaveOrden =
    | 'nombre'
    | 'n'
    | 'costo_promedio'
    | 'desviacion_pct'
    | 'margen_pct'
    | 'resultado'
    | 'coeficiente_variacion'
    | 'atipicos';

const TONO_VARIABILIDAD: Record<string, Tono> = {
    alta: 'alerta',
    media: 'atencion',
    baja: 'bien',
};

function valorOrden(
    fila: ProcedimientoCosteoFila,
    clave: ClaveOrden,
): number | string | null {
    if (clave === 'nombre') {
        return fila.nombre.toLocaleLowerCase('es');
    }

    return fila.indicadores?.[clave as keyof IndicadoresProcedimiento] as
        number | null;
}

type Orden = { clave: ClaveOrden; asc: boolean };

/** Encabezado ordenable de la tabla maestra. */
function Encabezado({
    clave,
    children,
    alinear = 'right',
    ayuda,
    orden,
    onOrdenar,
}: {
    clave: ClaveOrden;
    children: string;
    alinear?: 'left' | 'right';
    ayuda?: string;
    orden: Orden;
    onOrdenar: (orden: Orden) => void;
}) {
    const activa = orden.clave === clave;

    return (
        <th
            className={`p-3 font-medium ${alinear === 'right' ? 'text-right' : 'text-left'}`}
            aria-sort={
                activa ? (orden.asc ? 'ascending' : 'descending') : 'none'
            }
        >
            <button
                type="button"
                title={ayuda}
                onClick={() =>
                    onOrdenar({
                        clave,
                        // Primer clic: de mayor a menor (lo que se busca),
                        // salvo el nombre, que va alfabético.
                        asc: activa ? !orden.asc : clave === 'nombre',
                    })
                }
                className={`inline-flex items-center gap-1 hover:text-foreground ${activa ? 'text-foreground' : ''}`}
            >
                {children}
                {activa &&
                    (orden.asc ? (
                        <ArrowUp className="size-3.5" />
                    ) : (
                        <ArrowDown className="size-3.5" />
                    ))}
            </button>
        </th>
    );
}

function Vacio() {
    return <span className="text-muted-foreground">—</span>;
}

/**
 * Tabla maestra de procedimientos: todo lo que se sabe de cada uno en el
 * periodo, en una sola fila y ordenable por cualquier columna.
 *
 * Antes eran cinco pestañas (costo, rentabilidad, composición, variabilidad y
 * atípicos) con la misma lista de procedimientos y otras columnas.
 */
export default function ProcedimientosCosteoIndex({
    procedimientos,
    filtros,
    especialidades,
    complejidades,
    vista: vistaInicial,
    periodo,
    periodoEtiqueta,
}: {
    procedimientos: ProcedimientoCosteoFila[];
    filtros: FiltrosProcedimientosCosteo;
    especialidades: string[];
    complejidades: string[];
    vista: Vista;
    periodo: Periodo;
    periodoEtiqueta: string;
}) {
    const [q, setQ] = useState(filtros.q);
    const [vista, setVista] = useState<Vista>(vistaInicial);
    const [conSinDatos, setConSinDatos] = useState(false);
    const [orden, setOrden] = useState<Orden>({
        clave: 'n',
        asc: false,
    });
    const primeraCarga = useRef(true);
    const consulta = consultaDePeriodo(periodo);
    const periodoUrl = periodoDeLaUrl(usePage().url);

    const aplicar = (parcial: Partial<FiltrosProcedimientosCosteo>) => {
        const datos = {
            ...filtros,
            q,
            ...parcial,
            ...periodoUrl,
            vista: vista === 'composicion' ? 'composicion' : '',
        };
        const query = Object.fromEntries(
            Object.entries(datos).filter(([, v]) => v !== ''),
        );
        router.get('/costeo/procedimientos', query, {
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

    const hayFiltros =
        filtros.q !== '' ||
        filtros.especialidad !== '' ||
        filtros.complejidad !== '';

    const conDatos = procedimientos.filter((p) => p.indicadores !== null);
    const sinDatos = procedimientos.length - conDatos.length;

    const filas = useMemo(() => {
        const visibles = conSinDatos ? procedimientos : conDatos;

        return [...visibles].sort((a, b) => {
            const va = valorOrden(a, orden.clave);
            const vb = valorOrden(b, orden.clave);

            // Los vacíos siempre al final, ordene como ordene.
            if (va === null || va === undefined) {
                return vb === null || vb === undefined ? 0 : 1;
            }

            if (vb === null || vb === undefined) {
                return -1;
            }

            const comparacion =
                typeof va === 'string'
                    ? va.localeCompare(String(vb), 'es')
                    : va - Number(vb);

            return orden.asc ? comparacion : -comparacion;
        });
    }, [procedimientos, conDatos, conSinDatos, orden]);

    const pierden = conDatos.filter(
        (p) => (p.indicadores?.resultado ?? 0) < 0,
    ).length;
    const variables = conDatos.filter(
        (p) => p.indicadores?.nivel_variabilidad === 'alta',
    ).length;
    // En la vista de composición, un componente en cero en todos los
    // procedimientos (p. ej. indirectos antes de activar las bolsas) es una
    // columna de «$ 0 (0 %)» que no dice nada: se oculta y se explica.
    const componentesVisibles = COMPONENTES.filter((c) =>
        conDatos.some((p) => (p.indicadores?.componentes[c.clave] ?? 0) > 0),
    );
    const componentesEnCero = COMPONENTES.filter(
        (c) => !componentesVisibles.includes(c),
    );

    const conAtipicos = conDatos.filter(
        (p) => (p.indicadores?.atipicos ?? 0) > 0,
    ).length;

    const cambiarVista = (siguiente: Vista) => {
        setVista(siguiente);

        // La vista viaja en la URL para que «Composición» se pueda compartir.
        const url = new URL(window.location.href);

        if (siguiente === 'composicion') {
            url.searchParams.set('vista', 'composicion');
        } else {
            url.searchParams.delete('vista');
        }

        window.history.replaceState(window.history.state, '', url);
    };

    return (
        <>
            <Head title="Procedimientos · Costeo" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Procedimientos"
                    descripcion="Cuánto cuesta cada procedimiento, cuánto se aparta de su protocolo, cuánto deja y qué tan parejo se opera. Ordene por cualquier columna y entre a uno para ver sus cirugías."
                />

                <SelectorPeriodo
                    url="/costeo/procedimientos"
                    periodo={periodo}
                    etiqueta={periodoEtiqueta}
                    conservar={Object.fromEntries(
                        Object.entries({
                            ...filtros,
                            vista: vista === 'composicion' ? 'composicion' : '',
                        }).filter(([, v]) => v !== ''),
                    )}
                />

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar por nombre o código CUPS…"
                            className="pl-8"
                            aria-label="Buscar procedimiento"
                        />
                    </div>
                    <Select
                        value={filtros.especialidad || TODOS}
                        onValueChange={(v) =>
                            aplicar({ especialidad: v === TODOS ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label="Filtrar por especialidad"
                        >
                            <SelectValue placeholder="Especialidad" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>
                                Todas las especialidades
                            </SelectItem>
                            {especialidades.map((especialidad) => (
                                <SelectItem
                                    key={especialidad}
                                    value={especialidad}
                                >
                                    {especialidad}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filtros.complejidad || TODOS}
                        onValueChange={(v) =>
                            aplicar({ complejidad: v === TODOS ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-44"
                            aria-label="Filtrar por complejidad"
                        >
                            <SelectValue placeholder="Complejidad" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>
                                Toda complejidad
                            </SelectItem>
                            {complejidades.map((complejidad) => (
                                <SelectItem
                                    key={complejidad}
                                    value={complejidad}
                                    className="capitalize"
                                >
                                    {complejidad}
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
                                aplicar({
                                    q: '',
                                    especialidad: '',
                                    complejidad: '',
                                });
                            }}
                        >
                            <X className="size-4" />
                            Limpiar filtros
                        </Button>
                    )}

                    <ToggleGroup
                        type="single"
                        size="sm"
                        variant="outline"
                        value={vista}
                        onValueChange={(v) => v && cambiarVista(v as Vista)}
                        className="ml-auto"
                    >
                        <ToggleGroupItem value="indicadores">
                            Indicadores
                        </ToggleGroupItem>
                        <ToggleGroupItem value="composicion">
                            Composición del costo
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                    <p>
                        <span className="font-medium text-foreground">
                            {numero(conDatos.length)}
                        </span>{' '}
                        {conDatos.length === 1
                            ? 'procedimiento operado'
                            : 'procedimientos operados'}{' '}
                        en el periodo
                        {pierden > 0 && (
                            <>
                                {' · '}
                                <span className={TEXTO_TONO.alerta}>
                                    {numero(pierden)}{' '}
                                    {pierden === 1
                                        ? 'da pérdida'
                                        : 'dan pérdida'}
                                </span>
                            </>
                        )}
                        {variables > 0 && (
                            <>
                                {' · '}
                                <span className={TEXTO_TONO.atencion}>
                                    {numero(variables)} con variabilidad alta
                                </span>
                            </>
                        )}
                        {conAtipicos > 0 && (
                            <> · {numero(conAtipicos)} con cirugías atípicas</>
                        )}
                    </p>
                    {sinDatos > 0 && (
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={conSinDatos}
                                onChange={(e) =>
                                    setConSinDatos(e.target.checked)
                                }
                                className="size-4 accent-foreground"
                            />
                            Mostrar {numero(sinDatos)} sin cirugías en el
                            periodo
                        </label>
                    )}
                </div>

                {vista === 'composicion' && (
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <LeyendaComposicion />
                        {componentesEnCero.length > 0 &&
                            conDatos.length > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    Sin valor en el periodo:{' '}
                                    {componentesEnCero
                                        .map((c) =>
                                            c.nombre.toLocaleLowerCase('es'),
                                        )
                                        .join(', ')}
                                    .
                                </p>
                            )}
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[960px] text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <Encabezado
                                    orden={orden}
                                    onOrdenar={setOrden}
                                    clave="nombre"
                                    alinear="left"
                                >
                                    Procedimiento
                                </Encabezado>
                                <Encabezado
                                    orden={orden}
                                    onOrdenar={setOrden}
                                    clave="n"
                                    ayuda="Cirugías realizadas y costeadas en el periodo"
                                >
                                    Cirugías
                                </Encabezado>
                                <Encabezado
                                    orden={orden}
                                    onOrdenar={setOrden}
                                    clave="costo_promedio"
                                >
                                    Costo promedio
                                </Encabezado>
                                {vista === 'indicadores' ? (
                                    <>
                                        <Encabezado
                                            orden={orden}
                                            onOrdenar={setOrden}
                                            clave="desviacion_pct"
                                            ayuda="Costo real frente a lo que debió costar según la plantilla del protocolo"
                                        >
                                            Vs. estándar
                                        </Encabezado>
                                        <Encabezado
                                            orden={orden}
                                            onOrdenar={setOrden}
                                            clave="margen_pct"
                                            ayuda="Facturado menos costo, sobre lo facturado (solo cirugías facturadas)"
                                        >
                                            Margen
                                        </Encabezado>
                                        <Encabezado
                                            orden={orden}
                                            onOrdenar={setOrden}
                                            clave="resultado"
                                            ayuda="Lo que se ganó o perdió en total con las cirugías facturadas"
                                        >
                                            Resultado
                                        </Encabezado>
                                        <Encabezado
                                            orden={orden}
                                            onOrdenar={setOrden}
                                            clave="coeficiente_variacion"
                                            ayuda="Coeficiente de variación del costo: alta > 30 %, media > 15 %"
                                        >
                                            Variabilidad
                                        </Encabezado>
                                        <Encabezado
                                            orden={orden}
                                            onOrdenar={setOrden}
                                            clave="atipicos"
                                            ayuda="Cirugías cuyo costo se sale del rango habitual del procedimiento"
                                        >
                                            Atípicas
                                        </Encabezado>
                                        <th className="w-36 p-3 font-medium">
                                            Composición
                                        </th>
                                    </>
                                ) : (
                                    componentesVisibles.map((c) => (
                                        <th
                                            key={c.clave}
                                            className="p-3 text-right font-medium"
                                        >
                                            {c.nombre}
                                        </th>
                                    ))
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {filas.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        {hayFiltros
                                            ? 'Ningún procedimiento coincide con los filtros.'
                                            : procedimientos.length === 0
                                              ? 'Aún no hay procedimientos registrados en este hospital.'
                                              : 'Ninguna cirugía costeada en este periodo. Pruebe con «Toda la historia».'}
                                    </td>
                                </tr>
                            )}
                            {filas.map((fila) => {
                                const ind = fila.indicadores;

                                return (
                                    <tr
                                        key={fila.id}
                                        className={`border-b last:border-0 ${ind === null ? 'text-muted-foreground' : ''}`}
                                    >
                                        <td className="p-3">
                                            <Link
                                                href={`/costeo/procedimientos/${fila.id}${consulta}`}
                                                prefetch
                                                className="font-medium hover:underline"
                                            >
                                                <NombreDato
                                                    texto={fila.nombre}
                                                />
                                            </Link>
                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                <span className="font-mono">
                                                    {fila.codigo_cups}
                                                </span>{' '}
                                                · {fila.especialidad} ·{' '}
                                                {fila.complejidad}
                                            </div>
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {ind ? numero(ind.n) : '0'}
                                        </td>
                                        <td className="p-3 text-right font-medium tabular-nums">
                                            {ind ? (
                                                cop(ind.costo_promedio)
                                            ) : (
                                                <Vacio />
                                            )}
                                        </td>
                                        {vista === 'indicadores' ? (
                                            <>
                                                <td
                                                    className={`p-3 text-right tabular-nums ${TEXTO_TONO[tonoMenorEsMejor(ind?.desviacion_pct, UMBRALES.desviacionEstandar)]}`}
                                                    title={
                                                        ind &&
                                                        ind.n_con_estandar > 0
                                                            ? `${ind.n_con_estandar} cirugías con costo estándar`
                                                            : 'Sin plantilla de protocolo'
                                                    }
                                                >
                                                    {ind?.desviacion_pct !=
                                                    null ? (
                                                        pctVariacion(
                                                            ind.desviacion_pct,
                                                        )
                                                    ) : (
                                                        <Vacio />
                                                    )}
                                                </td>
                                                <td
                                                    className={`p-3 text-right tabular-nums ${TEXTO_TONO[tonoMayorEsMejor(ind?.margen_pct, UMBRALES.margen)]}`}
                                                    title={
                                                        ind &&
                                                        ind.n_facturadas > 0
                                                            ? `${ind.n_facturadas} cirugías facturadas`
                                                            : 'Sin cirugías facturadas'
                                                    }
                                                >
                                                    {ind?.margen_pct != null ? (
                                                        pct(ind.margen_pct)
                                                    ) : (
                                                        <Vacio />
                                                    )}
                                                </td>
                                                <td
                                                    className={`p-3 text-right tabular-nums ${ind?.resultado != null && ind.resultado < 0 ? TEXTO_TONO.alerta : ''}`}
                                                >
                                                    {ind?.resultado != null ? (
                                                        cop(ind.resultado)
                                                    ) : (
                                                        <Vacio />
                                                    )}
                                                </td>
                                                <td className="p-3 text-right tabular-nums">
                                                    {ind?.coeficiente_variacion !=
                                                    null ? (
                                                        <span
                                                            className={
                                                                TEXTO_TONO[
                                                                    TONO_VARIABILIDAD[
                                                                        ind.nivel_variabilidad ??
                                                                            ''
                                                                    ] ??
                                                                        'neutro'
                                                                ]
                                                            }
                                                        >
                                                            {pct(
                                                                ind.coeficiente_variacion,
                                                                0,
                                                            )}{' '}
                                                            <span className="text-xs">
                                                                {
                                                                    ind.nivel_variabilidad
                                                                }
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <Vacio />
                                                    )}
                                                </td>
                                                <td className="p-3 text-right tabular-nums">
                                                    {ind && ind.atipicos > 0 ? (
                                                        <Link
                                                            href={`/costeo/variabilidad${consulta}`}
                                                            className={`font-medium hover:underline ${TEXTO_TONO.alerta}`}
                                                        >
                                                            {ind.atipicos}
                                                        </Link>
                                                    ) : ind ? (
                                                        '0'
                                                    ) : (
                                                        <Vacio />
                                                    )}
                                                </td>
                                                <td className="p-3">
                                                    {ind ? (
                                                        <BarraComposicion
                                                            componentes={
                                                                ind.componentes
                                                            }
                                                            compacta
                                                        />
                                                    ) : (
                                                        <Vacio />
                                                    )}
                                                </td>
                                            </>
                                        ) : (
                                            componentesVisibles.map((c) => (
                                                <td
                                                    key={c.clave}
                                                    className="p-3 text-right tabular-nums"
                                                >
                                                    {ind ? (
                                                        <>
                                                            {cop(
                                                                ind.componentes[
                                                                    c.clave
                                                                ],
                                                            )}
                                                            <div className="text-xs text-muted-foreground">
                                                                {pct(
                                                                    ind.costo_promedio >
                                                                        0
                                                                        ? ind
                                                                              .componentes[
                                                                              c
                                                                                  .clave
                                                                          ] /
                                                                              ind.costo_promedio
                                                                        : null,
                                                                    0,
                                                                )}
                                                            </div>
                                                        </>
                                                    ) : (
                                                        <Vacio />
                                                    )}
                                                </td>
                                            ))
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ProcedimientosCosteoIndex.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Procedimientos', href: '/costeo/procedimientos' },
    ],
};
