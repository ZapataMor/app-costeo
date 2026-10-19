import { Head, Link } from '@inertiajs/react';
import { Eye, Pencil } from 'lucide-react';
import InstitucionController from '@/actions/App/Http/Controllers/Instituciones/InstitucionController';
import { FiltrosListado } from '@/components/filtros-listado';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { etiquetasNivel } from '@/types/instituciones';
import type { InstitucionResumen } from '@/types/instituciones';
import type { Paginado } from '@/types/parametros';

export default function InstitucionesIndex({
    instituciones,
    nivelesComplejidad,
    filtros,
}: {
    instituciones: Paginado<InstitucionResumen>;
    nivelesComplejidad: string[];
    filtros: Record<string, string>;
}) {
    return (
        <>
            <Head title="Instituciones" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    titulo="Instituciones"
                    descripcion="Hospitales y centros médicos que operan en el aplicativo. Cada institución tiene sus propios usuarios, parámetros y datos."
                    hrefNuevo={InstitucionController.create.url()}
                    textoNuevo="Nueva institución"
                />

                <FiltrosListado
                    url={InstitucionController.index.url()}
                    valores={filtros}
                    placeholderBusqueda="Buscar por nombre, NIT o municipio…"
                    filtros={[
                        {
                            clave: 'nivel',
                            etiqueta: 'Nivel de complejidad',
                            opciones: nivelesComplejidad.map((nivel) => ({
                                valor: nivel,
                                etiqueta: etiquetasNivel[nivel] ?? nivel,
                            })),
                        },
                    ]}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Nombre</th>
                                <th className="p-3 font-medium">NIT</th>
                                <th className="p-3 font-medium">Ubicación</th>
                                <th className="p-3 font-medium">Nivel</th>
                                <th className="p-3 text-right font-medium">
                                    Usuarios
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Cirugías
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {instituciones.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No hay instituciones que coincidan. Crea
                                        la primera con «Nueva institución».
                                    </td>
                                </tr>
                            )}
                            {instituciones.data.map((institucion) => (
                                <tr
                                    key={institucion.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3 font-medium">
                                        <Link
                                            href={InstitucionController.show.url(
                                                institucion.id,
                                            )}
                                            className="hover:underline"
                                            prefetch
                                        >
                                            {institucion.nombre}
                                        </Link>
                                    </td>
                                    <td className="p-3 text-muted-foreground tabular-nums">
                                        {institucion.nit}
                                    </td>
                                    <td className="p-3 text-muted-foreground">
                                        {[
                                            institucion.municipio,
                                            institucion.departamento,
                                        ]
                                            .filter(Boolean)
                                            .join(', ')}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant="secondary">
                                            {etiquetasNivel[
                                                institucion.nivel_complejidad
                                            ] ?? institucion.nivel_complejidad}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {institucion.usuarios}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {institucion.cirugias}
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Ver"
                                        >
                                            <Link
                                                href={InstitucionController.show.url(
                                                    institucion.id,
                                                )}
                                                prefetch
                                            >
                                                <Eye className="size-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Editar"
                                        >
                                            <Link
                                                href={InstitucionController.edit.url(
                                                    institucion.id,
                                                )}
                                                prefetch
                                            >
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <ConfirmarEliminacion
                                            url={InstitucionController.destroy.url(
                                                institucion.id,
                                            )}
                                            descripcion={`Se eliminará «${institucion.nombre}» junto con sus usuarios. Solo es posible si la institución aún no tiene datos registrados.`}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion
                    links={instituciones.links}
                    total={instituciones.total}
                    from={instituciones.from}
                    to={instituciones.to}
                />
            </div>
        </>
    );
}

InstitucionesIndex.layout = {
    breadcrumbs: [{ title: 'Instituciones', href: '/instituciones' }],
};
