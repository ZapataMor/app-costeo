import { Head, Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import CirugiaController from '@/actions/App/Http/Controllers/Cirugias/CirugiaController';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cop } from '@/lib/formato';
import type { PaginadoCirugias } from '@/types/cirugias';

export default function CirugiasIndex({ cirugias }: { cirugias: PaginadoCirugias }) {
    return (
        <>
            <Head title="Cirugías" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    titulo="Cirugías"
                    descripcion="Registro de cirugías reales que consumen los parámetros de Capa 1 y alimentan el costeo TDABC."
                    hrefNuevo={CirugiaController.create.url()}
                    textoNuevo="Registrar cirugía"
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Fecha</th>
                                <th className="p-3 font-medium">Paciente</th>
                                <th className="p-3 font-medium">Procedimiento principal</th>
                                <th className="p-3 font-medium">Tipo</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">Duración</th>
                                <th className="p-3 text-right font-medium">Costo TDABC</th>
                                <th className="p-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {cirugias.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="p-6 text-center text-muted-foreground">
                                        No hay cirugías registradas. Usa «Registrar cirugía» para capturar la primera.
                                    </td>
                                </tr>
                            )}
                            {cirugias.data.map((cirugia) => (
                                <tr key={cirugia.id} className="border-b last:border-0">
                                    <td className="p-3 tabular-nums">{cirugia.fecha ?? '—'}</td>
                                    <td className="p-3">
                                        {cirugia.paciente ? `${cirugia.paciente.nombres} ${cirugia.paciente.apellidos}` : '—'}
                                    </td>
                                    <td className="p-3">
                                        {cirugia.procedimiento_principal ? (
                                            <>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {cirugia.procedimiento_principal.codigo_cups}
                                                </span>{' '}
                                                {cirugia.procedimiento_principal.nombre}
                                            </>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="p-3 capitalize">{cirugia.tipo}</td>
                                    <td className="p-3 capitalize">{cirugia.estado}</td>
                                    <td className="p-3 text-right tabular-nums">
                                        {cirugia.duracion_minutos !== null ? `${cirugia.duracion_minutos} min` : '—'}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {cirugia.costo_total !== null ? (
                                            cop(Number(cirugia.costo_total))
                                        ) : (
                                            <Badge variant="outline">Sin costear</Badge>
                                        )}
                                    </td>
                                    <td className="p-3 text-right">
                                        <Button asChild variant="ghost" size="icon" aria-label="Ver detalle">
                                            <Link href={CirugiaController.show.url(cirugia.id)} prefetch>
                                                <Eye className="size-4" />
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion links={cirugias.links} total={cirugias.total} from={cirugias.from} to={cirugias.to} />
            </div>
        </>
    );
}

CirugiasIndex.layout = {
    breadcrumbs: [{ title: 'Cirugías', href: '/cirugias' }],
};
