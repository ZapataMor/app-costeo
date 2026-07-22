import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import ProcedimientoQuirurgicoController from '@/actions/App/Http/Controllers/Parametros/ProcedimientoQuirurgicoController';
import { FiltrosListado } from '@/components/filtros-listado';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { ProcedimientoForm } from '@/components/parametros/forms/procedimiento-form';
import { ModalFormulario } from '@/components/parametros/modal-formulario';
import { NivelConfiabilidadBadge } from '@/components/parametros/nivel-confiabilidad-badge';
import { Paginacion } from '@/components/parametros/paginacion';
import { Button } from '@/components/ui/button';
import { opcionesDesdeValores } from '@/lib/filtros';
import { cop } from '@/lib/formato';
import type { Paginado, ProcedimientoParam } from '@/types/parametros';

export default function ProcedimientosIndex({
    procedimientos,
    complejidades,
    nivelesConfiabilidad,
    filtros,
    especialidades,
}: {
    procedimientos: Paginado<ProcedimientoParam>;
    complejidades: string[];
    nivelesConfiabilidad: string[];
    filtros: Record<string, string>;
    especialidades: string[];
}) {
    return (
        <>
            <Head title="Procedimientos quirúrgicos" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    hrefAtras="/parametros"
                    titulo="Procedimientos quirúrgicos"
                    descripcion="Protocolos con código CUPS, duración estimada y tarifa de referencia (parámetro de Capa 1)."
                    accion={
                        <ModalFormulario
                            titulo="Nuevo procedimiento quirúrgico"
                            textoBoton="Nuevo procedimiento"
                        >
                            {(cerrar) => (
                                <ProcedimientoForm
                                    action={ProcedimientoQuirurgicoController.store.form()}
                                    complejidades={complejidades}
                                    nivelesConfiabilidad={nivelesConfiabilidad}
                                    onSuccess={cerrar}
                                />
                            )}
                        </ModalFormulario>
                    }
                />

                <FiltrosListado
                    url="/parametros/procedimientos"
                    valores={filtros}
                    placeholderBusqueda="Nombre, código CUPS o especialidad…"
                    filtros={[
                        {
                            clave: 'especialidad',
                            etiqueta: 'Especialidad',
                            opciones: opcionesDesdeValores(especialidades),
                        },
                        {
                            clave: 'complejidad',
                            etiqueta: 'Complejidad',
                            opciones: opcionesDesdeValores(complejidades),
                        },
                    ]}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">CUPS</th>
                                <th className="p-3 font-medium">Nombre</th>
                                <th className="p-3 font-medium">
                                    Especialidad
                                </th>
                                <th className="p-3 font-medium">Complejidad</th>
                                <th className="p-3 text-right font-medium">
                                    Duración est.
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Tarifa SOAT
                                </th>
                                <th className="p-3 font-medium">
                                    Confiabilidad
                                </th>
                                <th className="p-3 font-medium">Fuente</th>
                                <th className="p-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {procedimientos.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No hay procedimientos registrados.
                                    </td>
                                </tr>
                            )}
                            {procedimientos.data.map((proc) => (
                                <tr
                                    key={proc.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3 font-mono text-xs">
                                        {proc.codigo_cups}
                                    </td>
                                    <td className="p-3">{proc.nombre}</td>
                                    <td className="p-3">{proc.especialidad}</td>
                                    <td className="p-3 capitalize">
                                        {proc.complejidad}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {proc.duracion_estimada_minutos} min
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {proc.tarifa_soat
                                            ? cop(Number(proc.tarifa_soat))
                                            : '—'}
                                    </td>
                                    <td className="p-3">
                                        <NivelConfiabilidadBadge
                                            nivel={proc.nivel_confiabilidad}
                                        />
                                    </td>
                                    <td
                                        className="max-w-48 truncate p-3 text-muted-foreground"
                                        title={proc.fuente ?? ''}
                                    >
                                        {proc.fuente ?? '—'}
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Editar"
                                        >
                                            <Link
                                                href={ProcedimientoQuirurgicoController.edit.url(
                                                    proc.id,
                                                )}
                                                prefetch
                                            >
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <ConfirmarEliminacion
                                            url={ProcedimientoQuirurgicoController.destroy.url(
                                                proc.id,
                                            )}
                                            descripcion={`Se eliminará el procedimiento «${proc.nombre}». Esta acción no se puede deshacer.`}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion
                    links={procedimientos.links}
                    total={procedimientos.total}
                    from={procedimientos.from}
                    to={procedimientos.to}
                />
            </div>
        </>
    );
}

ProcedimientosIndex.layout = {
    breadcrumbs: [
        { title: 'Procedimientos', href: '/parametros/procedimientos' },
    ],
};
