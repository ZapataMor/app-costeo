import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Plus, Search, X } from 'lucide-react';
import type { MouseEvent } from 'react';
import { useCallback, useState } from 'react';
import CirugiaController from '@/actions/App/Http/Controllers/Cirugias/CirugiaController';
import { BotonExportar } from '@/components/boton-exportar';
import { CerrarCirugiaModal } from '@/components/cirugias/cerrar-cirugia-modal';
import type { DatosDetalleCirugia } from '@/components/cirugias/detalle-cirugia';
import { ModalDetalleCirugia } from '@/components/cirugias/modal-detalle-cirugia';
import {
    NOMBRE_CLASIFICACION,
    TONO_CLASIFICACION,
} from '@/components/cirugias/resumen-costo';
import { NombreDato } from '@/components/nombre-dato';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    cop,
    etiqueta,
    fecha as formatearFecha,
    pctVariacion,
} from '@/lib/formato';
import { TEXTO_TONO } from '@/lib/semaforo';
import { separarMarcaSemilla } from '@/lib/semilla';
import type { CirugiaFila, PaginadoCirugias } from '@/types/cirugias';

type Filtros = {
    q: string;
    desde: string;
    hasta: string;
    paciente: string;
    vista: string;
};

type Conteos = { todas: number; pendientes: number; sin_facturar: number };

/** Los vacíos no viajan: mantiene la URL legible. */
const sinVacios = (valores: Record<string, string>) =>
    Object.fromEntries(Object.entries(valores).filter(([, v]) => v !== ''));

/**
 * Bandejas del listado. Reemplazan el filtro de estado, el aviso ámbar y el
 * botón «Pendientes», que eran tres controles para la misma pregunta: qué
 * le falta a cada cirugía para contar.
 */
const VISTAS: {
    clave: keyof Conteos;
    valor: string;
    titulo: string;
    ayuda: string;
}[] = [
    {
        clave: 'todas',
        valor: '',
        titulo: 'Todas',
        ayuda: 'Todas las cirugías del hospital.',
    },
    {
        clave: 'pendientes',
        valor: 'pendientes',
        titulo: 'Pendientes',
        ayuda: 'Abiertas o sin costear: no entran a los indicadores hasta completarse.',
    },
    {
        clave: 'sin_facturar',
        valor: 'sin_facturar',
        titulo: 'Sin facturar',
        ayuda: 'Completas pero sin facturación: no tienen margen, glosa ni recaudo.',
    },
];

function Pestanas({
    filtros,
    conteos,
}: {
    filtros: Filtros;
    conteos: Conteos;
}) {
    return (
        <nav
            aria-label="Bandejas de cirugías"
            className="-mx-1 flex gap-1 overflow-x-auto overflow-y-hidden px-1 shadow-[inset_0_-1px_0_var(--border)]"
        >
            {VISTAS.map((vista) => {
                const activa = filtros.vista === vista.valor;
                const n = conteos[vista.clave];

                return (
                    <Link
                        key={vista.clave}
                        href="/cirugias"
                        data={sinVacios({ ...filtros, vista: vista.valor })}
                        preserveScroll
                        aria-current={activa ? 'page' : undefined}
                        title={vista.ayuda}
                        className={`flex items-center gap-2 border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors ${
                            activa
                                ? 'border-foreground font-semibold text-foreground'
                                : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground'
                        }`}
                    >
                        {vista.titulo}
                        <span
                            className={`rounded-full px-1.5 py-0.5 text-[11px] leading-none font-medium tabular-nums ${
                                vista.clave === 'pendientes' && n > 0
                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                    : 'bg-muted text-muted-foreground'
                            }`}
                        >
                            {n.toLocaleString('es-CO')}
                        </span>
                    </Link>
                );
            })}
        </nav>
    );
}

/**
 * Búsqueda y periodo en una sola línea. Conservan la bandeja y el paciente
 * elegidos: buscar dentro de «Pendientes» no debe devolver a «Todas».
 */
function BarraFiltros({ filtros }: { filtros: Filtros }) {
    const [valores, setValores] = useState({
        q: filtros.q,
        desde: filtros.desde,
        hasta: filtros.hasta,
    });

    const aplicar = (siguiente: typeof valores) => {
        setValores(siguiente);
        router.get('/cirugias', sinVacios({ ...filtros, ...siguiente }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const hayFiltros =
        filtros.q !== '' || filtros.desde !== '' || filtros.hasta !== '';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    aplicar(valores);
                }}
                className="relative"
            >
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={valores.q}
                    onChange={(e) =>
                        setValores({ ...valores, q: e.target.value })
                    }
                    placeholder="Paciente, procedimiento o CUPS…"
                    aria-label="Buscar"
                    className="w-72 pl-8"
                />
            </form>
            {/* Las fechas se aplican al perder el foco para no lanzar una
                petición por cada tecla mientras se escriben. */}
            <div className="flex items-center gap-1.5">
                <Input
                    type="date"
                    aria-label="Desde"
                    className="w-40"
                    value={valores.desde}
                    onChange={(e) =>
                        setValores({ ...valores, desde: e.target.value })
                    }
                    onBlur={() => aplicar(valores)}
                />
                <span className="text-sm text-muted-foreground">a</span>
                <Input
                    type="date"
                    aria-label="Hasta"
                    className="w-40"
                    value={valores.hasta}
                    onChange={(e) =>
                        setValores({ ...valores, hasta: e.target.value })
                    }
                    onBlur={() => aplicar(valores)}
                />
            </div>
            {hayFiltros && (
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() => aplicar({ q: '', desde: '', hasta: '' })}
                >
                    <X className="size-4" />
                    Limpiar
                </Button>
            )}
        </div>
    );
}

const claseInsigniaPendiente =
    'border-amber-300/70 bg-amber-50 font-normal text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400';

/**
 * Qué le falta a la cirugía, en una sola señal. Una realizada, costeada y
 * facturada no muestra nada: «Realizada» en casi todas las filas era ruido.
 */
function Situacion({ cirugia }: { cirugia: CirugiaFila }) {
    if (cirugia.estado === 'cancelada') {
        return <span className="text-muted-foreground">Cancelada</span>;
    }

    // Cerrada y realizada: lo único que le falta es el costo (ver
    // motivoPendiente en CirugiaController).
    const faltaSoloCosto =
        cirugia.motivo_pendiente != null &&
        cirugia.hora_fin !== null &&
        cirugia.estado === 'realizada';

    if (faltaSoloCosto) {
        return (
            <Badge
                variant="outline"
                className={claseInsigniaPendiente}
                title="Cerrada pero sin costo: no entra a los indicadores hasta costearse"
            >
                Sin costear
            </Badge>
        );
    }

    if (cirugia.motivo_pendiente != null) {
        return (
            <Badge
                variant="outline"
                className={claseInsigniaPendiente}
                title={`${cirugia.motivo_pendiente}: no entra a los indicadores hasta cerrarse`}
            >
                {etiqueta(cirugia.estado)}
                {cirugia.hora_fin === null && ' · falta salida'}
            </Badge>
        );
    }

    if (!cirugia.facturada) {
        return (
            <span
                className="text-muted-foreground"
                title="Sin facturación registrada: no tiene margen"
            >
                Sin facturar
            </span>
        );
    }

    return null;
}

/**
 * La URL actual con `ver` puesto o quitado: el detalle abierto queda en la
 * dirección (se puede recargar o compartir) sin perder filtros ni página.
 */
const urlConDetalle = (id: number | null) => {
    const params = new URLSearchParams(window.location.search);

    if (id === null) {
        params.delete('ver');
    } else {
        params.set('ver', String(id));
    }

    const query = params.toString();

    return `/cirugias${query ? `?${query}` : ''}`;
};

/** Costo y, debajo, cuánto se salió de su protocolo. */
function Costo({ cirugia }: { cirugia: CirugiaFila }) {
    if (cirugia.costo_total === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    const clasificacion = cirugia.clasificacion_desviacion;

    return (
        <>
            {cop(Number(cirugia.costo_total))}
            {cirugia.desviacion_pct !== null && clasificacion !== null && (
                <span
                    className={`block text-xs ${TEXTO_TONO[TONO_CLASIFICACION[clasificacion]]}`}
                    title={`Frente al estándar del protocolo: ${NOMBRE_CLASIFICACION[clasificacion]}`}
                >
                    {pctVariacion(Number(cirugia.desviacion_pct))} vs. protocolo
                </span>
            )}
        </>
    );
}

export default function CirugiasIndex({
    cirugias,
    filtros,
    conteos,
    pacienteFiltrado,
    detalle,
}: {
    cirugias: PaginadoCirugias;
    filtros: Filtros;
    conteos: Conteos;
    pacienteFiltrado: {
        id: number;
        nombres: string;
        apellidos: string;
    } | null;
    detalle: DatosDetalleCirugia | null;
}) {
    // Cirugía abierta en el modal. Se abre al instante con un indicador de
    // carga; el detalle llega por recarga parcial (solo la prop `detalle`).
    const [abierta, setAbierta] = useState<number | null>(
        detalle?.cirugia.id ?? null,
    );
    const detalleAbierto =
        detalle !== null && detalle.cirugia.id === abierta ? detalle : null;

    const abrir = (id: number) => {
        setAbierta(id);
        router.get(
            urlConDetalle(id),
            {},
            { only: ['detalle'], preserveState: true, preserveScroll: true },
        );
    };

    const cerrar = useCallback(() => {
        setAbierta(null);
        router.get(
            urlConDetalle(null),
            {},
            {
                only: ['detalle'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, []);

    // Fecha, paciente, procedimiento, duración, costo, situación, acción.
    const colSpanVacio = 7;

    const vacio =
        filtros.vista === 'pendientes'
            ? 'No hay cirugías pendientes: todo lo registrado ya cuenta en los indicadores.'
            : filtros.vista === 'sin_facturar'
              ? 'Todas las cirugías completas tienen su facturación.'
              : filtros.q !== '' || filtros.desde !== '' || filtros.hasta !== ''
                ? 'Ninguna cirugía coincide con la búsqueda.'
                : 'No hay cirugías registradas. Usa «Registrar cirugía» para capturar la primera.';

    return (
        <>
            {/* Se llamaba «Procedimientos», igual que el catálogo CUPS de
                Parámetros y que el explorador de Costeo: tres módulos
                distintos con el mismo nombre. */}
            <Head title="Cirugías realizadas" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    titulo="Cirugías realizadas"
                    descripcion="Cada cirugía registrada, con su equipo, insumos y tiempos. Es lo que alimenta el costeo."
                    accion={
                        <div className="flex flex-wrap items-center gap-2">
                            {/* Exporta el periodo y la búsqueda en pantalla. */}
                            <BotonExportar
                                url="/exportar/cirugias"
                                filtros={{
                                    desde: filtros.desde,
                                    hasta: filtros.hasta,
                                }}
                            />
                            <Button asChild>
                                <Link
                                    href={CirugiaController.create.url()}
                                    prefetch
                                >
                                    <Plus className="size-4" />
                                    Registrar cirugía
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <Pestanas filtros={filtros} conteos={conteos} />

                <div className="flex flex-wrap items-center gap-2">
                    <BarraFiltros filtros={filtros} />
                    {pacienteFiltrado && (
                        <Badge
                            variant="secondary"
                            className="h-9 gap-1.5 rounded-md px-3 text-sm font-normal"
                        >
                            Cirugías de {pacienteFiltrado.nombres}{' '}
                            {pacienteFiltrado.apellidos}
                            <Link
                                href="/cirugias"
                                data={sinVacios({ ...filtros, paciente: '' })}
                                aria-label="Quitar el filtro de paciente"
                                className="rounded-sm text-muted-foreground hover:text-foreground"
                            >
                                <X className="size-3.5" />
                            </Link>
                        </Badge>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Fecha</th>
                                <th className="p-3 font-medium">Paciente</th>
                                <th className="p-3 font-medium">
                                    Procedimiento principal
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Duración
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Costo
                                </th>
                                <th className="p-3 font-medium">Pendiente</th>
                                <th className="p-3">
                                    <span className="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {cirugias.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={colSpanVacio}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        {vacio}
                                    </td>
                                </tr>
                            )}
                            {cirugias.data.map((cirugia) => {
                                const procedimiento =
                                    cirugia.procedimiento_principal;
                                const nombrePaciente = cirugia.paciente
                                    ? `${cirugia.paciente.nombres} ${cirugia.paciente.apellidos}`
                                    : null;
                                const urlDetalle = CirugiaController.show.url(
                                    cirugia.id,
                                );
                                const abrirDesdeEnlace = (e: MouseEvent) => {
                                    e.stopPropagation();

                                    // Ctrl/⌘/medio: la página en otra pestaña.
                                    if (
                                        e.ctrlKey ||
                                        e.metaKey ||
                                        e.shiftKey ||
                                        e.button !== 0
                                    ) {
                                        return;
                                    }

                                    e.preventDefault();
                                    abrir(cirugia.id);
                                };

                                return (
                                    // Toda la fila abre el detalle en un
                                    // modal; «Corregir» vive ahí. El enlace
                                    // del procedimiento es el camino por
                                    // teclado (y abre la página propia en
                                    // otra pestaña).
                                    <tr
                                        key={cirugia.id}
                                        onClick={() => abrir(cirugia.id)}
                                        className="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/40"
                                    >
                                        <td className="p-3 whitespace-nowrap tabular-nums">
                                            {formatearFecha(cirugia.fecha)}
                                        </td>
                                        <td className="p-3">
                                            <div
                                                className="max-w-[11rem] truncate"
                                                title={
                                                    nombrePaciente ?? undefined
                                                }
                                            >
                                                {nombrePaciente ?? '—'}
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            {procedimiento ? (
                                                <>
                                                    <a
                                                        href={urlDetalle}
                                                        onClick={
                                                            abrirDesdeEnlace
                                                        }
                                                        className="block max-w-[17rem] truncate hover:underline"
                                                        title={
                                                            separarMarcaSemilla(
                                                                procedimiento.nombre,
                                                            ).limpio
                                                        }
                                                    >
                                                        <NombreDato
                                                            texto={
                                                                procedimiento.nombre
                                                            }
                                                        />
                                                    </a>
                                                    <span className="text-xs text-muted-foreground">
                                                        <span className="font-mono">
                                                            {
                                                                procedimiento.codigo_cups
                                                            }
                                                        </span>{' '}
                                                        ·{' '}
                                                        {etiqueta(cirugia.tipo)}
                                                    </span>
                                                </>
                                            ) : (
                                                <a
                                                    href={urlDetalle}
                                                    onClick={abrirDesdeEnlace}
                                                    className="text-muted-foreground hover:underline"
                                                >
                                                    Sin procedimiento
                                                </a>
                                            )}
                                        </td>
                                        <td className="p-3 text-right whitespace-nowrap tabular-nums">
                                            {cirugia.duracion_minutos !== null
                                                ? `${cirugia.duracion_minutos} min`
                                                : '—'}
                                        </td>
                                        <td className="p-3 text-right whitespace-nowrap tabular-nums">
                                            <Costo cirugia={cirugia} />
                                        </td>
                                        <td className="p-3 whitespace-nowrap">
                                            <Situacion cirugia={cirugia} />
                                        </td>
                                        {/* El modal vive dentro de la fila:
                                            sus clics no deben abrir el
                                            detalle. */}
                                        <td
                                            className="p-3 text-right whitespace-nowrap"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            {cirugia.puede_cerrarse ? (
                                                <CerrarCirugiaModal
                                                    cirugiaId={cirugia.id}
                                                    paso={
                                                        cirugia.paso_cierre ??
                                                        'sala'
                                                    }
                                                    horaInicio={
                                                        cirugia.hora_inicio
                                                    }
                                                    horaFin={cirugia.hora_fin}
                                                    conTexto
                                                />
                                            ) : (
                                                <ChevronRight
                                                    aria-hidden
                                                    className="ml-auto size-4 text-muted-foreground/60"
                                                />
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
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

            <ModalDetalleCirugia
                abierto={abierta !== null}
                detalle={detalleAbierto}
                onCerrar={cerrar}
            />
        </>
    );
}

CirugiasIndex.layout = {
    breadcrumbs: [{ title: 'Cirugías realizadas', href: '/cirugias' }],
};
