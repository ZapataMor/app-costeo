import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { etiqueta } from '@/lib/formato';

type Profesional = {
    id: number;
    name: string;
    email: string;
    activo: boolean;
    recurso: {
        id: number;
        nombre: string;
        rol: string;
        especialidad: string | null;
    } | null;
};

export default function Profesionales({
    profesionales,
}: {
    profesionales: Profesional[];
}) {
    return (
        <>
            <Head title="Profesionales" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Profesionales"
                        description="Cuentas del personal quirúrgico para participar en la gestión del conocimiento."
                    />
                    <Button asChild>
                        <Link href="/conocimiento/profesionales/create">
                            <Plus className="size-4" />
                            Nueva cuenta
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3 font-medium">Nombre</th>
                                    <th className="p-3 font-medium">Correo</th>
                                    <th className="p-3 font-medium">
                                        Rol quirúrgico
                                    </th>
                                    <th className="p-3 font-medium">Estado</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {profesionales.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="p-6 text-center text-muted-foreground"
                                        >
                                            Aún no hay cuentas de profesionales.
                                        </td>
                                    </tr>
                                )}
                                {profesionales.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3">{p.name}</td>
                                        <td className="p-3 text-muted-foreground">
                                            {p.email}
                                        </td>
                                        <td className="p-3">
                                            {p.recurso
                                                ? `${etiqueta(p.recurso.rol)}${p.recurso.especialidad ? ` · ${p.recurso.especialidad}` : ''}`
                                                : '—'}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    p.activo
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {p.activo
                                                    ? 'Activa'
                                                    : 'Inactiva'}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    router.patch(
                                                        `/conocimiento/profesionales/${p.id}/activo`,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                {p.activo
                                                    ? 'Desactivar'
                                                    : 'Activar'}
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Profesionales.layout = {
    breadcrumbs: [
        { title: 'Profesionales', href: '/conocimiento/profesionales' },
    ],
};
