import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import CirugiaController from '@/actions/App/Http/Controllers/Cirugias/CirugiaController';
import { BotonExportar } from '@/components/boton-exportar';
import { CerrarCirugiaModal } from '@/components/cirugias/cerrar-cirugia-modal';
import { FiltrosListado } from '@/components/filtros-listado';
import { NombreDato } from '@/components/nombre-dato';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { opcionesDesdeValores } from '@/lib/filtros';
import { cop, etiqueta, fecha as formatearFecha } from '@/lib/formato';
import { separarMarcaSemilla } from '@/lib/semilla';
import type { PaginadoCirugias } from '@/types/cirugias';

/**
 * Rango de fechas del listado. Se aplica al perder el foco para no lanzar
 * una petición por cada tecla mientras se escribe la fecha.
 */
function RangoFechas({ filtros }: { filtros: Record<string, string> }) {
    const [rango, setRango] = useState({
        desde: filtros.desde ?? '',
        hasta: filtros.hasta ?? '',
    });

    const aplicar = (siguiente: { desde: string; hasta: string }) => {
        setRango(siguiente);
        router.get(
            '/cirugias',
            Object.fromEntries(
                Object.entries({ ...filtros, ...siguiente }).filter(
                    ([, v]) => v !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="flex items-center gap-1.5">
            <Input
                type="date"
                aria-label="Desde"
                className="w-40"
                value={rango.desde}
                onChange={(e) => setRango({ ...rango, desde: e.target.value })}
                onBlur={() => aplicar(rango)}
            />
            <span className="text-sm text-muted-foreground">a</span>
            <Input
                type="date"
                aria-label="Hasta"
                className="w-40"
                value={rango.hasta}
                onChange={(e) => setRango({ ...rango, hasta: e.target.value })}
                onBlur={() => aplicar(rango)}
            />
        </div>
    );
}

const claseInsigniaPendiente =
    'border-amber-300/70 bg-amber-50 font-normal text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400';

export default function CirugiasIndex({
    cirugias,
    estados,
    filtros,
    totalPendientes,
}: {
    cirugias: PaginadoCirugias;
    estados: string[];
    filtros: Record<string, string>;
    totalPendientes: number;
}) {
    // Fecha, paciente, procedimiento, tipo, estado, duración, costo, acciones.
    const colSpanVacio = 8;

    const viendoPendientes = filtros.pendientes === '1';

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
                    hrefNuevo={CirugiaController.create.url()}
                    textoNuevo="Registrar cirugía"
                />

                {totalPendientes > 0 && !viendoPendientes && (
                    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-amber-300/70 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <span>
                            {totalPendientes === 1
                                ? 'Hay 1 cirugía sin completar: no entra a los indicadores hasta que se cierre o se costee.'
                                : `Hay ${totalPendientes} cirugías sin completar: no entran a los indicadores hasta que se cierren o se costeen.`}
                        </span>
                        <Button asChild variant="outline" size="sm">
                            <Link href="/cirugias?pendientes=1">
                                Ver pendientes
                            </Link>
                        </Button>
                    </div>
                )}

                <FiltrosListado
                    url="/cirugias"
                    valores={filtros}
                    placeholderBusqueda="Paciente, procedimiento o CUPS…"
                    filtros={[
                        {
                            clave: 'estado',
                            etiqueta: 'Estado',
                            opciones: opcionesDesdeValores(estados),
                        },
                    ]}
                    extra={
                        <>
                            <RangoFechas filtros={filtros} />
                            <BotonExportar
                                url="/exportar/cirugias"
                                filtros={{
                                    estado: filtros.estado ?? '',
                                    desde: filtros.desde ?? '',
                                    hasta: filtros.hasta ?? '',
                                }}
                            />
                            <Button
                                type="button"
                                variant={
                                    viendoPendientes ? 'default' : 'outline'
                                }
                                onClick={() =>
                                    router.get(
                                        '/cirugias',
                                        viendoPendientes
                                            ? {}
                                            : { pendientes: '1' },
                                        { preserveState: true, replace: true },
                                    )
                                }
                            >
                                <TriangleAlert className="size-4" />
                                Pendientes ({totalPendientes})
                            </Button>
                        </>
                    }
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Fecha</th>
                                <th className="p-3 font-medium">Paciente</th>
                                <th className="p-3 font-medium">
                                    Procedimiento principal
                                </th>
                                <th className="p-3 font-medium">Tipo</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">
                                    Duración
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Costo
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
                                        colSpan={colSpanVacio}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No hay cirugías registradas. Usa
                                        «Registrar cirugía» para capturar la
                                        primera.
                                    </td>
                                </tr>
                            )}
                            {cirugias.data.map((cirugia) => {
                                const procedimiento =
                                    cirugia.procedimiento_principal;
                                const nombrePaciente = cirugia.paciente
                                    ? `${cirugia.paciente.nombres} ${cirugia.paciente.apellidos}`
                                    : null;
                                // Cerrada y realizada: lo único que le falta
                                // es el costo (ver motivoPendiente en
                                // CirugiaController).
                                const faltaSoloCosto =
                                    cirugia.motivo_pendiente != null &&
                                    cirugia.hora_fin !== null &&
                                    cirugia.estado === 'realizada';
                                const pendienteDeCierre =
                                    cirugia.motivo_pendiente != null &&
                                    !faltaSoloCosto;

                                return (
                                    <tr
                                        key={cirugia.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 whitespace-nowrap tabular-nums">
                                            {formatearFecha(cirugia.fecha)}
                                        </td>
                                        <td className="p-3">
                                            <div
                                                className="max-w-[10rem] truncate"
                                                title={
                                                    nombrePaciente ?? undefined
                                                }
                                            >
                                                {nombrePaciente ?? '—'}
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            {procedimiento ? (
                                                <div
                                                    className="max-w-[14rem] truncate"
                                                    title={`${procedimiento.codigo_cups} · ${separarMarcaSemilla(procedimiento.nombre).limpio}`}
                                                >
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {
                                                            procedimiento.codigo_cups
                                                        }
                                                    </span>{' '}
                                                    <NombreDato
                                                        texto={
                                                            procedimiento.nombre
                                                        }
                                                    />
                                                </div>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="p-3">
                                            {etiqueta(cirugia.tipo)}
                                        </td>
                                        <td className="p-3 whitespace-nowrap">
                                            {/* Una sola señal por fila. Antes salía
                                            «En recuperación» + «Está en «en
                                            recuperación»» + «Sin costear»: el
                                            estado tres veces. Si falta cerrarla,
                                            el estado va en ámbar con el motivo
                                            al pasar el cursor; si solo falta el
                                            costo, la señal está en su columna. */}
                                            {pendienteDeCierre ? (
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        claseInsigniaPendiente
                                                    }
                                                    title={`${cirugia.motivo_pendiente}: no entra a los indicadores hasta cerrarse`}
                                                >
                                                    {etiqueta(cirugia.estado)}
                                                </Badge>
                                            ) : (
                                                etiqueta(cirugia.estado)
                                            )}
                                            {cirugia.hora_fin === null && (
                                                <span className="block text-xs text-muted-foreground">
                                                    Falta la salida de sala
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-3 text-right whitespace-nowrap tabular-nums">
                                            {cirugia.duracion_minutos !== null
                                                ? `${cirugia.duracion_minutos} min`
                                                : '—'}
                                        </td>
                                        <td className="p-3 text-right whitespace-nowrap tabular-nums">
                                            {cirugia.costo_total !== null ? (
                                                cop(Number(cirugia.costo_total))
                                            ) : faltaSoloCosto ? (
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        claseInsigniaPendiente
                                                    }
                                                    title="Cerrada pero sin costo: no entra a los indicadores hasta costearse"
                                                >
                                                    Sin costear
                                                </Badge>
                                            ) : (
                                                <span
                                                    className="text-muted-foreground"
                                                    title="Se costea al cerrarse"
                                                >
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-3 text-right whitespace-nowrap">
                                            {cirugia.puede_cerrarse && (
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
                                                />
                                            )}
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Corregir"
                                                title="Corregir"
                                            >
                                                <Link
                                                    href={CirugiaController.edit.url(
                                                        cirugia.id,
                                                    )}
                                                    prefetch
                                                >
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Ver detalle"
                                                title="Ver detalle"
                                            >
                                                <Link
                                                    href={CirugiaController.show.url(
                                                        cirugia.id,
                                                    )}
                                                    prefetch
                                                >
                                                    <Eye className="size-4" />
                                                </Link>
                                            </Button>
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
        </>
    );
}

CirugiasIndex.layout = {
    breadcrumbs: [{ title: 'Cirugías realizadas', href: '/cirugias' }],
};
