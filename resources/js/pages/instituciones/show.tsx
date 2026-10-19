import { Form, Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import InstitucionController from '@/actions/App/Http/Controllers/Instituciones/InstitucionController';
import UsuarioInstitucionController from '@/actions/App/Http/Controllers/Instituciones/UsuarioInstitucionController';
import { UsuarioInstitucionForm } from '@/components/instituciones/usuario-institucion-form';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { ModalFormulario } from '@/components/parametros/modal-formulario';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { etiquetasNivel, etiquetasRol } from '@/types/instituciones';
import type { Institucion, UsuarioInstitucion } from '@/types/instituciones';

export default function InstitucionesShow({
    institucion,
    usuarios,
    rolesAsignables,
}: {
    institucion: Institucion;
    usuarios: UsuarioInstitucion[];
    rolesAsignables: string[];
}) {
    const [editando, setEditando] = useState<UsuarioInstitucion | null>(null);

    const ubicacion = [institucion.municipio, institucion.departamento]
        .filter(Boolean)
        .join(', ');

    return (
        <>
            <Head title={institucion.nombre} />
            <div className="flex flex-col gap-6 p-4">
                <EncabezadoListado
                    hrefAtras={InstitucionController.index.url()}
                    titulo={institucion.nombre}
                    descripcion={`NIT ${institucion.nit} · ${ubicacion}`}
                    accion={
                        <Button asChild variant="outline">
                            <Link
                                href={InstitucionController.edit.url(
                                    institucion.id,
                                )}
                                prefetch
                            >
                                <Pencil className="size-4" />
                                Editar institución
                            </Link>
                        </Button>
                    }
                />

                <dl className="grid gap-4 rounded-lg border p-4 text-sm sm:grid-cols-3 lg:grid-cols-6">
                    <Dato
                        etiqueta="Nivel de complejidad"
                        valor={
                            etiquetasNivel[institucion.nivel_complejidad] ??
                            institucion.nivel_complejidad
                        }
                    />
                    <Dato
                        etiqueta="Horas por día"
                        valor={String(institucion.horas_dia)}
                    />
                    <Dato
                        etiqueta="Días por mes"
                        valor={String(institucion.dias_mes)}
                    />
                    <Dato
                        etiqueta="Minutos efectivos / hora"
                        valor={String(institucion.minutos_efectivos_hora)}
                    />
                    <Dato
                        etiqueta="Minutos disponibles / mes"
                        valor={institucion.minutos_disponibles_mes.toLocaleString(
                            'es-CO',
                        )}
                    />
                    <Dato
                        etiqueta="Factor indirecto"
                        valor={institucion.factor_indirecto.toLocaleString(
                            'es-CO',
                            { maximumFractionDigits: 4 },
                        )}
                    />
                </dl>

                <section className="flex flex-col gap-4">
                    <EncabezadoListado
                        titulo="Usuarios"
                        descripcion="Cuentas que acceden a esta institución: el administrador configura y analiza; el digitador solo registra procedimientos."
                        accion={
                            <ModalFormulario
                                titulo="Nuevo usuario"
                                descripcion={`Se creará en «${institucion.nombre}».`}
                                textoBoton="Nuevo usuario"
                            >
                                {(cerrar) => (
                                    <UsuarioInstitucionForm
                                        action={UsuarioInstitucionController.store.form(
                                            institucion.id,
                                        )}
                                        rolesAsignables={rolesAsignables}
                                        onSuccess={cerrar}
                                    />
                                )}
                            </ModalFormulario>
                        }
                    />

                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <th className="p-3 font-medium">Nombre</th>
                                    <th className="p-3 font-medium">Correo</th>
                                    <th className="p-3 font-medium">Rol</th>
                                    <th className="p-3 font-medium">Creado</th>
                                    <th className="p-3 font-medium">Estado</th>
                                    <th className="p-3 text-right font-medium">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {usuarios.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="p-6 text-center text-muted-foreground"
                                        >
                                            Esta institución aún no tiene
                                            usuarios. Crea al menos un
                                            administrador con «Nuevo usuario».
                                        </td>
                                    </tr>
                                )}
                                {usuarios.map((usuario) => (
                                    <tr
                                        key={usuario.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3">{usuario.name}</td>
                                        <td className="p-3 text-muted-foreground">
                                            {usuario.email}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    usuario.role ===
                                                    'admin_hospital'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {etiquetasRol[usuario.role]}
                                            </Badge>
                                        </td>
                                        <td className="p-3 tabular-nums">
                                            {usuario.created_at ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    usuario.activo
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {usuario.activo
                                                    ? 'Activo'
                                                    : 'Inactivo'}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-right whitespace-nowrap">
                                            <Form
                                                {...UsuarioInstitucionController.toggleActivo.form(
                                                    {
                                                        hospital:
                                                            institucion.id,
                                                        usuario: usuario.id,
                                                    },
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                                className="inline"
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        {usuario.activo
                                                            ? 'Desactivar'
                                                            : 'Activar'}
                                                    </Button>
                                                )}
                                            </Form>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Editar usuario"
                                                onClick={() =>
                                                    setEditando(usuario)
                                                }
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <ConfirmarEliminacion
                                                url={UsuarioInstitucionController.destroy.url(
                                                    {
                                                        hospital:
                                                            institucion.id,
                                                        usuario: usuario.id,
                                                    },
                                                )}
                                                descripcion={`Se eliminará la cuenta de «${usuario.name}». Solo es posible si aún no tiene actividad registrada; de lo contrario, desactívala.`}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Dialog
                open={editando !== null}
                onOpenChange={(abierto) => !abierto && setEditando(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Editar usuario</DialogTitle>
                        <DialogDescription>
                            Deja la contraseña en blanco para conservar la
                            actual.
                        </DialogDescription>
                    </DialogHeader>
                    {editando && (
                        <UsuarioInstitucionForm
                            key={editando.id}
                            action={UsuarioInstitucionController.update.form({
                                hospital: institucion.id,
                                usuario: editando.id,
                            })}
                            usuario={editando}
                            rolesAsignables={rolesAsignables}
                            onSuccess={() => setEditando(null)}
                            onCancelar={() => setEditando(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
            <dd className="font-medium tabular-nums">{valor}</dd>
        </div>
    );
}

InstitucionesShow.layout = {
    breadcrumbs: [
        { title: 'Instituciones', href: '/instituciones' },
        { title: 'Detalle', href: '#' },
    ],
};
