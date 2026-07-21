import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import SalaOperatoriaController from '@/actions/App/Http/Controllers/Parametros/SalaOperatoriaController';
import { FiltrosListado } from '@/components/filtros-listado';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { SalaOperatoriaForm } from '@/components/parametros/forms/sala-operatoria-form';
import { ModalFormulario } from '@/components/parametros/modal-formulario';
import { NivelConfiabilidadBadge } from '@/components/parametros/nivel-confiabilidad-badge';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { opcionesDesdeValores } from '@/lib/filtros';
import { cop } from '@/lib/formato';
import type { Paginado, SalaOperatoriaParam } from '@/types/parametros';

export default function SalasOperatoriasIndex({ salas, nivelesConfiabilidad, filtros }: { salas: Paginado<SalaOperatoriaParam>; nivelesConfiabilidad: string[]; filtros: Record<string, string> }) {
    return (
        <>
            <Head title="Salas operatorias" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    hrefAtras="/parametros"
                    titulo="Salas operatorias"
                    descripcion="Salas con su costo por hora de funcionamiento (parámetro de Capa 1)."
                    accion={
                        <ModalFormulario titulo="Nueva sala operatoria" textoBoton="Nueva sala">
                            {(cerrar) => (
                                <SalaOperatoriaForm action={SalaOperatoriaController.store.form()} nivelesConfiabilidad={nivelesConfiabilidad} onSuccess={cerrar} />
                            )}
                        </ModalFormulario>
                    }
                />

                <FiltrosListado
                    url="/parametros/salas-operatorias"
                    valores={filtros}
                    placeholderBusqueda="Nombre de la sala…"
                    filtros={[
                        { clave: 'confiabilidad', etiqueta: 'Confiabilidad', opciones: opcionesDesdeValores(nivelesConfiabilidad) },
                        {
                            clave: 'activa',
                            etiqueta: 'Estado',
                            opciones: [
                                { valor: '1', etiqueta: 'Activas' },
                                { valor: '0', etiqueta: 'Inactivas' },
                            ],
                        },
                    ]}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Nombre</th>
                                <th className="p-3 font-medium">Ubicación</th>
                                <th className="p-3 text-right font-medium">Costo/hora</th>
                                <th className="p-3 font-medium">Equipamiento</th>
                                <th className="p-3 font-medium">Confiabilidad</th>
                                <th className="p-3 font-medium">Fuente</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {salas.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="p-6 text-center text-muted-foreground">
                                        No hay salas operatorias registradas.
                                    </td>
                                </tr>
                            )}
                            {salas.data.map((sala) => (
                                <tr key={sala.id} className="border-b last:border-0">
                                    <td className="p-3">{sala.nombre}</td>
                                    <td className="p-3">{sala.ubicacion ?? '—'}</td>
                                    <td className="p-3 text-right tabular-nums">{cop(Number(sala.costo_hora))}</td>
                                    <td className="max-w-56 truncate p-3 text-muted-foreground" title={(sala.equipamiento ?? []).join(', ')}>
                                        {(sala.equipamiento ?? []).join(', ') || '—'}
                                    </td>
                                    <td className="p-3">
                                        <NivelConfiabilidadBadge nivel={sala.nivel_confiabilidad} />
                                    </td>
                                    <td className="max-w-48 truncate p-3 text-muted-foreground" title={sala.fuente ?? ''}>
                                        {sala.fuente ?? '—'}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={sala.activa ? 'secondary' : 'outline'}>
                                            {sala.activa ? 'Activa' : 'Inactiva'}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <Button asChild variant="ghost" size="icon" aria-label="Editar">
                                            <Link href={SalaOperatoriaController.edit.url(sala.id)} prefetch>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <ConfirmarEliminacion
                                            url={SalaOperatoriaController.destroy.url(sala.id)}
                                            descripcion={`Se eliminará la sala «${sala.nombre}». Esta acción no se puede deshacer.`}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion links={salas.links} total={salas.total} from={salas.from} to={salas.to} />
            </div>
        </>
    );
}

SalasOperatoriasIndex.layout = {
    breadcrumbs: [{ title: 'Salas operatorias', href: '/parametros/salas-operatorias' }],
};
