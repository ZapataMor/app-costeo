import { Form, Head } from '@inertiajs/react';
import DigitadorController from '@/actions/App/Http/Controllers/DigitadorController';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Digitador = {
    id: number;
    name: string;
    email: string;
    activo: boolean;
    created_at: string | null;
};

export default function DigitadoresIndex({ digitadores }: { digitadores: Digitador[] }) {
    return (
        <>
            <Head title="Digitadores" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    hrefAtras="/dashboard"
                    titulo="Digitadores"
                    descripcion="Personal encargado de registrar los procedimientos de tu hospital. Cada digitador solo accede al módulo de registro."
                    hrefNuevo={DigitadorController.create.url()}
                    textoNuevo="Nuevo digitador"
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Nombre</th>
                                <th className="p-3 font-medium">Correo</th>
                                <th className="p-3 font-medium">Creado</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {digitadores.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="p-6 text-center text-muted-foreground">
                                        Aún no hay digitadores. Crea el primero con «Nuevo digitador».
                                    </td>
                                </tr>
                            )}
                            {digitadores.map((digitador) => (
                                <tr key={digitador.id} className="border-b last:border-0">
                                    <td className="p-3">{digitador.name}</td>
                                    <td className="p-3 text-muted-foreground">{digitador.email}</td>
                                    <td className="p-3 tabular-nums">{digitador.created_at ?? '—'}</td>
                                    <td className="p-3">
                                        <Badge variant={digitador.activo ? 'secondary' : 'outline'}>
                                            {digitador.activo ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <Form
                                            {...DigitadorController.toggleActivo.form(digitador.id)}
                                            options={{ preserveScroll: true }}
                                            className="inline"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    {digitador.activo ? 'Desactivar' : 'Activar'}
                                                </Button>
                                            )}
                                        </Form>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

DigitadoresIndex.layout = {
    breadcrumbs: [{ title: 'Digitadores', href: '/digitadores' }],
};
