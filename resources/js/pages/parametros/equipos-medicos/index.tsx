import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import EquipoMedicoController from '@/actions/App/Http/Controllers/Parametros/EquipoMedicoController';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { ModalFormulario } from '@/components/parametros/modal-formulario';
import { EquipoMedicoForm } from '@/components/parametros/forms/equipo-medico-form';
import { NivelConfiabilidadBadge } from '@/components/parametros/nivel-confiabilidad-badge';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cop } from '@/lib/formato';
import type { EquipoMedicoParam, Paginado } from '@/types/parametros';

export default function EquiposMedicosIndex({ equipos, nivelesConfiabilidad }: { equipos: Paginado<EquipoMedicoParam>; nivelesConfiabilidad: string[] }) {
    return (
        <>
            <Head title="Equipos médicos" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    hrefAtras="/parametros"
                    titulo="Equipos médicos"
                    descripcion="Equipos con costo por hora de uso y datos de depreciación (parámetro de Capa 1)."
                    accion={
                        <ModalFormulario titulo="Nuevo equipo médico" textoBoton="Nuevo equipo">
                            {(cerrar) => (
                                <EquipoMedicoForm action={EquipoMedicoController.store.form()} nivelesConfiabilidad={nivelesConfiabilidad} onSuccess={cerrar} />
                            )}
                        </ModalFormulario>
                    }
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Nombre</th>
                                <th className="p-3 font-medium">Código</th>
                                <th className="p-3 text-right font-medium">Valor adquisición</th>
                                <th className="p-3 text-right font-medium">Vida útil</th>
                                <th className="p-3 text-right font-medium">Costo/hora</th>
                                <th className="p-3 font-medium">Confiabilidad</th>
                                <th className="p-3 font-medium">Fuente</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {equipos.data.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="p-6 text-center text-muted-foreground">
                                        No hay equipos médicos registrados.
                                    </td>
                                </tr>
                            )}
                            {equipos.data.map((equipo) => (
                                <tr key={equipo.id} className="border-b last:border-0">
                                    <td className="p-3">{equipo.nombre}</td>
                                    <td className="p-3 font-mono text-xs">{equipo.codigo ?? '—'}</td>
                                    <td className="p-3 text-right tabular-nums">
                                        {equipo.valor_adquisicion ? cop(Number(equipo.valor_adquisicion)) : '—'}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {equipo.vida_util_anios ? `${equipo.vida_util_anios} años` : '—'}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">{cop(Number(equipo.costo_hora))}</td>
                                    <td className="p-3">
                                        <NivelConfiabilidadBadge nivel={equipo.nivel_confiabilidad} />
                                    </td>
                                    <td className="max-w-48 truncate p-3 text-muted-foreground" title={equipo.fuente ?? ''}>
                                        {equipo.fuente ?? '—'}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={equipo.activo ? 'secondary' : 'outline'}>
                                            {equipo.activo ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <Button asChild variant="ghost" size="icon" aria-label="Editar">
                                            <Link href={EquipoMedicoController.edit.url(equipo.id)} prefetch>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <ConfirmarEliminacion
                                            url={EquipoMedicoController.destroy.url(equipo.id)}
                                            descripcion={`Se eliminará el equipo «${equipo.nombre}». Esta acción no se puede deshacer.`}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion links={equipos.links} total={equipos.total} from={equipos.from} to={equipos.to} />
            </div>
        </>
    );
}

EquiposMedicosIndex.layout = {
    breadcrumbs: [{ title: 'Equipos médicos', href: '/parametros/equipos-medicos' }],
};
