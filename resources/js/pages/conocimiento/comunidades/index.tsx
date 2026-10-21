import { Head, Link } from '@inertiajs/react';
import { MessagesSquare, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { fechaHora } from '@/lib/formato';
import type { ComunidadResumen } from '@/types/conocimiento';

export default function Comunidades({
    comunidades,
}: {
    comunidades: ComunidadResumen[];
}) {
    // Si se llega desde «Discutir» en una lección o recomendación, el enlace
    // viaja hasta la comunidad elegida para prellenar el hilo.
    const enlace = typeof window === 'undefined' ? '' : window.location.search;

    return (
        <>
            <Head title="Comunidades de práctica" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        Comunidades de práctica
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Un espacio por especialidad para revisar casos, discutir
                        protocolos y convertir lo que se aprende en lecciones.
                        Cada profesional entra a la general y a la de su
                        especialidad; anestesia y enfermería quirúrgica, a
                        todas.
                        {enlace &&
                            ' Elija la comunidad donde quiere abrir la discusión.'}
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {comunidades.map((c) => (
                        <Link
                            key={c.id}
                            href={`/conocimiento/comunidades/${c.id}${enlace}`}
                        >
                            <Card className="h-full transition-colors hover:bg-muted/40">
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <CardTitle className="text-base">
                                            {c.nombre}
                                        </CardTitle>
                                        {c.soy_miembro && (
                                            <Badge variant="secondary">
                                                Miembro
                                            </Badge>
                                        )}
                                    </div>
                                    <CardDescription>
                                        {c.descripcion}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                                    <span className="flex items-center gap-1">
                                        <Users className="size-3.5" />
                                        {c.miembros}
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <MessagesSquare className="size-3.5" />
                                        {c.hilos} hilos
                                    </span>
                                    {c.ultima_actividad && (
                                        <span>
                                            Última actividad{' '}
                                            {fechaHora(c.ultima_actividad)}
                                        </span>
                                    )}
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

Comunidades.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento/lecciones' },
        { title: 'Comunidades', href: '/conocimiento/comunidades' },
    ],
};
