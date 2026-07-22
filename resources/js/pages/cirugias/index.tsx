import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import CirugiaController from '@/actions/App/Http/Controllers/Cirugias/CirugiaController';
import { BotonExportar } from '@/components/boton-exportar';
import { CerrarCirugiaModal } from '@/components/cirugias/cerrar-cirugia-modal';
import { FiltrosListado } from '@/components/filtros-listado';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { opcionesDesdeValores } from '@/lib/filtros';
import { cop, etiqueta, fecha as formatearFecha } from '@/lib/formato';
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
                    descripcion="Cada procedimiento registrado, con su equipo, insumos y tiempos. Es lo que alimenta el costeo TDABC."
                    hrefNuevo={CirugiaController.create.url()}
                    textoNuevo="Registrar procedimiento"
                />

                {totalPendientes > 0 && !viendoPendientes && (
                    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-amber-300/70 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <span>
                            {totalPendientes === 1
                                ? 'Hay 1 procedimiento sin completar: no entra a los indicadores hasta que se cierre o se costee.'
                                : `Hay ${totalPendientes} procedimientos sin completar: no entran a los indicadores hasta que se cierren o se costeen.`}
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
                                        colSpan={colSpanVacio}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No hay procedimientos registrados. Usa
                                        «Registrar procedimiento» para capturar
                                        el primero.
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
                                    <td className="p-3">
                                        {cirugia.paciente
                                            ? `${cirugia.paciente.nombres} ${cirugia.paciente.apellidos}`
                                            : '—'}
                                    </td>
                                    <td className="p-3">
                                        {cirugia.procedimiento_principal ? (
                                            <>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {
                                                        cirugia
                                                            .procedimiento_principal
                                                            .codigo_cups
                                                    }
                                                </span>{' '}
                                                {
                                                    cirugia
                                                        .procedimiento_principal
                                                        .nombre
                                                }
                                            </>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="p-3">
                                        {etiqueta(cirugia.tipo)}
                                    </td>
                                    <td className="p-3">
                                        <span>{etiqueta(cirugia.estado)}</span>
                                        {/* La insignia decía solo «No
                                            contabilizada» y había que adivinar
                                            qué faltaba: ahora lleva el motivo. */}
                                        {cirugia.motivo_pendiente && (
                                            <Badge
                                                variant="outline"
                                                className="ml-2 border-amber-300/70 bg-amber-50 font-normal text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400"
                                                title="No entra a los indicadores hasta completarse"
                                            >
                                                {cirugia.motivo_pendiente}
                                            </Badge>
                                        )}
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
                                    <td className="p-3 text-right whitespace-nowrap">
                                        {cirugia.puede_cerrarse && (
                                            <CerrarCirugiaModal
                                                cirugiaId={cirugia.id}
                                                paso={
                                                    cirugia.paso_cierre ??
                                                    'sala'
                                                }
                                                horaInicio={cirugia.hora_inicio}
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

CirugiasIndex.layout = {
    breadcrumbs: [{ title: 'Cirugías realizadas', href: '/cirugias' }],
};
