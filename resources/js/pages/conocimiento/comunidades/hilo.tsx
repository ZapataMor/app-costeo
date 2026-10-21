import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, BookPlus, CheckCircle2, Lock, Pin } from 'lucide-react';
import { AreaTexto } from '@/components/conocimiento/area-texto';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { fechaHora } from '@/lib/formato';
import type { HiloDetalle } from '@/types/conocimiento';

export default function Hilo({
    hilo,
    permisos,
}: {
    hilo: HiloDetalle;
    permisos: {
        responder: boolean;
        moderar: boolean;
        marcar_solucion: boolean;
    };
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        cuerpo: '',
    });
    const moderar = (cambios: { fijado?: boolean; cerrado?: boolean }) =>
        router.patch(`/conocimiento/hilos/${hilo.id}`, cambios, {
            preserveScroll: true,
        });

    return (
        <>
            <Head title={hilo.titulo} />
            <div className="flex max-w-4xl flex-col gap-4 p-4">
                <div className="flex items-start gap-2">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        aria-label="Volver"
                    >
                        <Link
                            href={`/conocimiento/comunidades/${hilo.comunidad.id}`}
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <p className="text-xs text-muted-foreground">
                            {hilo.comunidad.nombre}
                        </p>
                        <h1 className="flex flex-wrap items-center gap-2 text-xl font-semibold">
                            {hilo.fijado && <Pin className="size-4" />}
                            {hilo.cerrado && <Lock className="size-4" />}
                            {hilo.titulo}
                        </h1>
                    </div>
                </div>

                <Card>
                    <CardContent className="space-y-3 py-4">
                        <p className="text-xs text-muted-foreground">
                            {hilo.autor ?? '—'}
                            {hilo.creado_en &&
                                ` · ${fechaHora(hilo.creado_en)}`}
                        </p>
                        <p className="text-sm whitespace-pre-line">
                            {hilo.cuerpo}
                        </p>
                        {hilo.enlace && (
                            <Link
                                href={hilo.enlace.url}
                                className="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                            >
                                <Badge variant="outline">
                                    {hilo.enlace.tipo}
                                </Badge>
                                {hilo.enlace.titulo}
                            </Link>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-2">
                    {hilo.leccion ? (
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={`/conocimiento/lecciones/${hilo.leccion.id}`}
                            >
                                <BookPlus className="size-4" />
                                Lección: {hilo.leccion.titulo}
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={`/conocimiento/lecciones/create?hilo=${hilo.id}`}
                            >
                                <BookPlus className="size-4" />
                                Convertir en lección
                            </Link>
                        </Button>
                    )}
                    {permisos.moderar && (
                        <>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    moderar({ fijado: !hilo.fijado })
                                }
                            >
                                <Pin className="size-4" />
                                {hilo.fijado ? 'Desfijar' : 'Fijar'}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    moderar({ cerrado: !hilo.cerrado })
                                }
                            >
                                <Lock className="size-4" />
                                {hilo.cerrado ? 'Reabrir' : 'Cerrar'}
                            </Button>
                        </>
                    )}
                </div>

                <h2 className="text-sm font-semibold">
                    {hilo.respuestas.length} respuesta(s)
                </h2>

                {hilo.respuestas.map((r) => (
                    <Card
                        key={r.id}
                        className={
                            r.es_solucion
                                ? 'border-emerald-300 dark:border-emerald-800'
                                : undefined
                        }
                    >
                        <CardContent className="space-y-2 py-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-xs text-muted-foreground">
                                    {r.autor ?? '—'}
                                    {r.creado_en &&
                                        ` · ${fechaHora(r.creado_en)}`}
                                </p>
                                <div className="flex items-center gap-2">
                                    {r.es_solucion && (
                                        <Badge variant="secondary">
                                            <CheckCircle2 className="size-3" />
                                            Solución
                                        </Badge>
                                    )}
                                    {permisos.marcar_solucion && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.patch(
                                                    `/conocimiento/hilos/${hilo.id}/respuestas/${r.id}/solucion`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {r.es_solucion
                                                ? 'Quitar solución'
                                                : 'Marcar como solución'}
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <p className="text-sm whitespace-pre-line">
                                {r.cuerpo}
                            </p>
                        </CardContent>
                    </Card>
                ))}

                {permisos.responder ? (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(`/conocimiento/hilos/${hilo.id}/respuestas`, {
                                preserveScroll: true,
                                onSuccess: () => reset(),
                            });
                        }}
                        className="grid gap-2"
                    >
                        <AreaTexto
                            value={data.cuerpo}
                            onChange={(e) => setData('cuerpo', e.target.value)}
                            placeholder="Su respuesta"
                            required
                        />
                        <InputError message={errors.cuerpo} />
                        <div>
                            <Button size="sm" disabled={processing}>
                                Responder
                            </Button>
                        </div>
                    </form>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        {hilo.cerrado
                            ? 'El hilo está cerrado.'
                            : 'Solo los miembros de la comunidad pueden responder.'}
                    </p>
                )}
            </div>
        </>
    );
}

Hilo.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento/lecciones' },
        { title: 'Comunidades', href: '/conocimiento/comunidades' },
    ],
};
