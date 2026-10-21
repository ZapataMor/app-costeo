import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpen, CheckCircle2, Lock, Pin, Plus } from 'lucide-react';
import { useState } from 'react';
import { AreaTexto } from '@/components/conocimiento/area-texto';
import InputError from '@/components/input-error';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fechaHora } from '@/lib/formato';
import type { HiloResumen, Paginado } from '@/types/conocimiento';

type Comunidad = {
    id: number;
    nombre: string;
    especialidad: string | null;
    descripcion: string | null;
    miembros: {
        id: number;
        name: string;
        rol: string;
        perfil: string | null;
    }[];
};

type Enlace = {
    enlazable_tipo?: string;
    enlazable_id?: string;
    titulo?: string;
};

export default function DetalleComunidad({
    comunidad,
    hilos,
    permisos,
    enlace,
}: {
    comunidad: Comunidad;
    hilos: Paginado<HiloResumen>;
    permisos: { participar: boolean; moderar: boolean };
    enlace: Enlace;
}) {
    const [abriendo, setAbriendo] = useState(Boolean(enlace.enlazable_tipo));

    return (
        <>
            <Head title={comunidad.nombre} />
            <div className="grid gap-4 p-4 lg:grid-cols-[1fr_280px]">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold">
                                {comunidad.nombre}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {comunidad.descripcion}
                            </p>
                        </div>
                        {permisos.participar && (
                            <Button onClick={() => setAbriendo(!abriendo)}>
                                <Plus className="size-4" />
                                Nuevo hilo
                            </Button>
                        )}
                    </div>

                    {abriendo && permisos.participar && (
                        <NuevoHilo comunidadId={comunidad.id} enlace={enlace} />
                    )}

                    {hilos.data.length === 0 ? (
                        <Card>
                            <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                Todavía no hay discusiones. Un buen comienzo: el
                                caso más caro del mes pasado.
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardContent className="divide-y p-0">
                                {hilos.data.map((h) => (
                                    <Link
                                        key={h.id}
                                        href={`/conocimiento/hilos/${h.id}`}
                                        className="flex items-start justify-between gap-3 p-4 hover:bg-muted/40"
                                    >
                                        <div className="space-y-1">
                                            <p className="flex flex-wrap items-center gap-1.5 font-medium">
                                                {h.fijado && (
                                                    <Pin className="size-3.5 text-muted-foreground" />
                                                )}
                                                {h.cerrado && (
                                                    <Lock className="size-3.5 text-muted-foreground" />
                                                )}
                                                {h.titulo}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {h.autor ?? '—'} · actividad{' '}
                                                {fechaHora(
                                                    h.ultima_actividad_en,
                                                )}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                                            {h.resuelto && (
                                                <Badge variant="secondary">
                                                    <CheckCircle2 className="size-3" />
                                                    Resuelto
                                                </Badge>
                                            )}
                                            {h.leccion_aprendida_id && (
                                                <Badge variant="outline">
                                                    <BookOpen className="size-3" />
                                                    Lección
                                                </Badge>
                                            )}
                                            <Badge variant="outline">
                                                {h.respuestas} resp.
                                            </Badge>
                                        </div>
                                    </Link>
                                ))}
                            </CardContent>
                        </Card>
                    )}

                    <Paginacion
                        links={hilos.links}
                        total={hilos.total}
                        from={hilos.from}
                        to={hilos.to}
                    />
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Miembros ({comunidad.miembros.length})
                        </CardTitle>
                        <CardDescription>
                            Los moderadores fijan y cierran hilos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-1.5 text-sm">
                        {comunidad.miembros.map((m) => (
                            <div
                                key={m.id}
                                className="flex items-center justify-between gap-2"
                            >
                                <span className="truncate">
                                    {m.name}
                                    {m.perfil && (
                                        <span className="block text-xs text-muted-foreground">
                                            {m.perfil}
                                        </span>
                                    )}
                                </span>
                                {m.rol === 'moderador' && (
                                    <Badge variant="outline">Moderador</Badge>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function NuevoHilo({
    comunidadId,
    enlace,
}: {
    comunidadId: number;
    enlace: Enlace;
}) {
    const { data, setData, post, processing, errors } = useForm({
        titulo: enlace.titulo ?? '',
        cuerpo: '',
        enlazable_tipo: enlace.enlazable_tipo ?? null,
        enlazable_id: enlace.enlazable_id ? Number(enlace.enlazable_id) : null,
    });

    return (
        <Card>
            <CardContent className="py-4">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(`/conocimiento/comunidades/${comunidadId}/hilos`);
                    }}
                    className="grid gap-3"
                >
                    {data.enlazable_tipo && (
                        <p className="text-xs text-muted-foreground">
                            Enlazado a {data.enlazable_tipo} #
                            {data.enlazable_id}.
                        </p>
                    )}
                    <div className="grid gap-1.5">
                        <Label>Título</Label>
                        <Input
                            value={data.titulo}
                            onChange={(e) => setData('titulo', e.target.value)}
                            required
                        />
                        <InputError message={errors.titulo} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Planteamiento</Label>
                        <AreaTexto
                            value={data.cuerpo}
                            onChange={(e) => setData('cuerpo', e.target.value)}
                            rows={5}
                            placeholder="El caso, la duda o la propuesta. Sin nombres de pacientes."
                            required
                        />
                        <InputError message={errors.cuerpo} />
                    </div>
                    <div>
                        <Button disabled={processing}>Publicar hilo</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

DetalleComunidad.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento/lecciones' },
        { title: 'Comunidades', href: '/conocimiento/comunidades' },
    ],
};
