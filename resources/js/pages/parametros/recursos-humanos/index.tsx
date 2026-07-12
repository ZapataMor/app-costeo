import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import RecursoHumanoController from '@/actions/App/Http/Controllers/Parametros/RecursoHumanoController';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { ModalFormulario } from '@/components/parametros/modal-formulario';
import { RecursoHumanoForm } from '@/components/parametros/forms/recurso-humano-form';
import { NivelConfiabilidadBadge } from '@/components/parametros/nivel-confiabilidad-badge';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cop } from '@/lib/formato';
import type { Paginado, RecursoHumanoParam } from '@/types/parametros';

export default function RecursosHumanosIndex({ recursos, roles, nivelesConfiabilidad }: { recursos: Paginado<RecursoHumanoParam>; roles: string[]; nivelesConfiabilidad: string[] }) {
    return (
        <>
            <Head title="Recursos humanos" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    hrefAtras="/parametros"
                    titulo="Recursos humanos"
                    descripcion="Personal quirúrgico con su estructura salarial, base del costo por minuto TDABC."
                    accion={
                        <ModalFormulario titulo="Nuevo recurso humano" textoBoton="Nuevo recurso">
                            {(cerrar) => (
                                <RecursoHumanoForm action={RecursoHumanoController.store.form()} roles={roles} nivelesConfiabilidad={nivelesConfiabilidad} onSuccess={cerrar} />
                            )}
                        </ModalFormulario>
                    }
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Nombre</th>
                                <th className="p-3 font-medium">Rol</th>
                                <th className="p-3 font-medium">Especialidad</th>
                                <th className="p-3 text-right font-medium">Salario</th>
                                <th className="p-3 text-right font-medium">Prestaciones</th>
                                <th className="p-3 text-right font-medium">Indirectos</th>
                                <th className="p-3 font-medium">Confiabilidad</th>
                                <th className="p-3 font-medium">Fuente</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recursos.data.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="p-6 text-center text-muted-foreground">
                                        No hay recursos humanos registrados.
                                    </td>
                                </tr>
                            )}
                            {recursos.data.map((recurso) => (
                                <tr key={recurso.id} className="border-b last:border-0">
                                    <td className="p-3">{recurso.nombre}</td>
                                    <td className="p-3 capitalize">{recurso.rol}</td>
                                    <td className="p-3">{recurso.especialidad ?? '—'}</td>
                                    <td className="p-3 text-right tabular-nums">{cop(Number(recurso.salario_mensual))}</td>
                                    <td className="p-3 text-right tabular-nums">{cop(Number(recurso.prestaciones_mensuales))}</td>
                                    <td className="p-3 text-right tabular-nums">{cop(Number(recurso.costos_indirectos_mensuales))}</td>
                                    <td className="p-3">
                                        <NivelConfiabilidadBadge nivel={recurso.nivel_confiabilidad} />
                                    </td>
                                    <td className="max-w-48 truncate p-3 text-muted-foreground" title={recurso.fuente ?? ''}>
                                        {recurso.fuente ?? '—'}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={recurso.activo ? 'secondary' : 'outline'}>
                                            {recurso.activo ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <Button asChild variant="ghost" size="icon" aria-label="Editar">
                                            <Link href={RecursoHumanoController.edit.url(recurso.id)} prefetch>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <ConfirmarEliminacion
                                            url={RecursoHumanoController.destroy.url(recurso.id)}
                                            descripcion={`Se eliminará «${recurso.nombre}». Esta acción no se puede deshacer.`}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion links={recursos.links} total={recursos.total} from={recursos.from} to={recursos.to} />
            </div>
        </>
    );
}

RecursosHumanosIndex.layout = {
    breadcrumbs: [{ title: 'Recursos humanos', href: '/parametros/recursos-humanos' }],
};
