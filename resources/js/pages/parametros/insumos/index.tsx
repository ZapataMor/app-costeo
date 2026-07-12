import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import InsumoController from '@/actions/App/Http/Controllers/Parametros/InsumoController';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { ModalFormulario } from '@/components/parametros/modal-formulario';
import { InsumoForm } from '@/components/parametros/forms/insumo-form';
import { NivelConfiabilidadBadge } from '@/components/parametros/nivel-confiabilidad-badge';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cop } from '@/lib/formato';
import type { InsumoParam, Paginado } from '@/types/parametros';

export default function InsumosIndex({ insumos, categorias, nivelesConfiabilidad }: { insumos: Paginado<InsumoParam>; categorias: string[]; nivelesConfiabilidad: string[] }) {
    return (
        <>
            <Head title="Insumos" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    hrefAtras="/parametros"
                    titulo="Insumos"
                    descripcion="Medicamentos, materiales y dispositivos con su costo unitario (parámetro de Capa 1)."
                    accion={
                        <ModalFormulario titulo="Nuevo insumo" textoBoton="Nuevo insumo">
                            {(cerrar) => (
                                <InsumoForm action={InsumoController.store.form()} categorias={categorias} nivelesConfiabilidad={nivelesConfiabilidad} onSuccess={cerrar} />
                            )}
                        </ModalFormulario>
                    }
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Código</th>
                                <th className="p-3 font-medium">Nombre</th>
                                <th className="p-3 font-medium">Categoría</th>
                                <th className="p-3 font-medium">Unidad</th>
                                <th className="p-3 text-right font-medium">Costo unitario</th>
                                <th className="p-3 font-medium">Confiabilidad</th>
                                <th className="p-3 font-medium">Fuente</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {insumos.data.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="p-6 text-center text-muted-foreground">
                                        No hay insumos registrados. Crea el primero con «Nuevo insumo».
                                    </td>
                                </tr>
                            )}
                            {insumos.data.map((insumo) => (
                                <tr key={insumo.id} className="border-b last:border-0">
                                    <td className="p-3 font-mono text-xs">{insumo.codigo}</td>
                                    <td className="p-3">{insumo.nombre}</td>
                                    <td className="p-3 capitalize">{insumo.categoria}</td>
                                    <td className="p-3">{insumo.unidad}</td>
                                    <td className="p-3 text-right tabular-nums">{cop(Number(insumo.costo_unitario))}</td>
                                    <td className="p-3">
                                        <NivelConfiabilidadBadge nivel={insumo.nivel_confiabilidad} />
                                    </td>
                                    <td className="max-w-48 truncate p-3 text-muted-foreground" title={insumo.fuente ?? ''}>
                                        {insumo.fuente ?? '—'}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={insumo.activo ? 'secondary' : 'outline'}>
                                            {insumo.activo ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <Button asChild variant="ghost" size="icon" aria-label="Editar">
                                            <Link href={InsumoController.edit.url(insumo.id)} prefetch>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <ConfirmarEliminacion
                                            url={InsumoController.destroy.url(insumo.id)}
                                            descripcion={`Se eliminará el insumo «${insumo.nombre}». Esta acción no se puede deshacer.`}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion links={insumos.links} total={insumos.total} from={insumos.from} to={insumos.to} />
            </div>
        </>
    );
}

InsumosIndex.layout = {
    breadcrumbs: [{ title: 'Parámetros', href: '/parametros/insumos' }, { title: 'Insumos', href: '/parametros/insumos' }],
};
