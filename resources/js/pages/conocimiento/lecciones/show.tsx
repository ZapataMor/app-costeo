import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    ArrowLeft,
    CheckCircle2,
    Globe,
    MessageSquarePlus,
    Pencil,
    Send,
    ThumbsUp,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import { AreaTexto } from '@/components/conocimiento/area-texto';
import { EstadoLeccionBadge } from '@/components/conocimiento/estado-leccion-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, fecha, fechaHora } from '@/lib/formato';
import type { LeccionDetalle } from '@/types/conocimiento';

export default function DetalleLeccion({
    leccion,
    permisos,
}: {
    leccion: LeccionDetalle;
    permisos: { editar: boolean; enviar: boolean; gestionar: boolean };
}) {
    const [devolviendo, setDevolviendo] = useState(false);
    const [motivo, setMotivo] = useState('');
    const base = `/conocimiento/lecciones/${leccion.id}`;
    const accion = (
        ruta: string,
        datos: Record<string, string | boolean> = {},
    ) => router.post(`${base}/${ruta}`, datos, { preserveScroll: true });

    return (
        <>
            <Head title={leccion.titulo} />
            <div className="flex max-w-4xl flex-col gap-4 p-4">
                <div className="flex items-start gap-2">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        aria-label="Volver"
                    >
                        <Link
                            href={
                                leccion.de_la_red
                                    ? '/conocimiento/lecciones?vista=red'
                                    : '/conocimiento/lecciones'
                            }
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {leccion.tipo_etiqueta}
                            </Badge>
                            <Badge variant="secondary">
                                {leccion.categoria_etiqueta}
                            </Badge>
                            {!leccion.de_la_red && (
                                <EstadoLeccionBadge
                                    estado={leccion.estado}
                                    etiqueta={leccion.estado_etiqueta}
                                />
                            )}
                            {leccion.compartida_red && (
                                <Badge variant="outline">
                                    <Globe className="size-3" />
                                    {leccion.de_la_red
                                        ? leccion.hospital
                                        : 'Compartida con la red'}
                                </Badge>
                            )}
                        </div>
                        <h1 className="text-xl font-semibold">
                            {leccion.titulo}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {leccion.de_la_red
                                ? `Publicada por ${leccion.hospital}`
                                : `Por ${leccion.autor ?? 'autor eliminado'}`}
                            {leccion.publicada_en &&
                                ` · publicada el ${fecha(leccion.publicada_en)}`}
                            {leccion.procedimiento &&
                                ` · ${leccion.procedimiento.nombre}`}
                        </p>
                    </div>
                </div>

                {leccion.motivo_devolucion && leccion.estado === 'borrador' && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                        <CardContent className="py-3 text-sm">
                            <strong>Devuelta con observaciones:</strong>{' '}
                            {leccion.motivo_devolucion}
                        </CardContent>
                    </Card>
                )}

                <Seccion titulo="Contexto: qué pasó" texto={leccion.contexto} />
                <Seccion
                    titulo="Lección: qué aprendimos"
                    texto={leccion.leccion}
                />
                {leccion.recomendacion && (
                    <Seccion
                        titulo="Recomendación: qué hacer distinto"
                        texto={leccion.recomendacion}
                    />
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Datos</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm sm:grid-cols-2">
                        <Dato etiqueta="Causa" valor={leccion.causa_etiqueta} />
                        <Dato
                            etiqueta="Impacto estimado"
                            valor={
                                leccion.impacto_estimado_cop !== null
                                    ? cop(leccion.impacto_estimado_cop)
                                    : null
                            }
                        />
                        <Dato
                            etiqueta="Validada por"
                            valor={
                                leccion.revisor
                                    ? `${leccion.revisor} · ${leccion.revisado_en ? fechaHora(leccion.revisado_en) : ''}`
                                    : null
                            }
                        />
                        <Dato
                            etiqueta="Etiquetas"
                            valor={
                                leccion.etiquetas.length
                                    ? leccion.etiquetas
                                          .map((e) => `#${e}`)
                                          .join(' ')
                                    : null
                            }
                        />
                        {leccion.cirugias.length > 0 && (
                            <div className="sm:col-span-2">
                                <span className="text-muted-foreground">
                                    Evidencia:{' '}
                                </span>
                                {leccion.cirugias.map((c) => (
                                    <Link
                                        key={c.id}
                                        href={`/cirugias/${c.id}`}
                                        className="mr-2 underline"
                                    >
                                        #{c.id} ({fecha(c.fecha)})
                                    </Link>
                                ))}
                            </div>
                        )}
                        {leccion.alerta_conocimiento && (
                            <div className="text-muted-foreground sm:col-span-2">
                                Nació de la alerta «
                                {leccion.alerta_conocimiento.titulo}»
                            </div>
                        )}
                        {leccion.hilos.length > 0 && (
                            <div className="sm:col-span-2">
                                <span className="text-muted-foreground">
                                    Discusión:{' '}
                                </span>
                                {leccion.hilos.map((h) => (
                                    <Link
                                        key={h.id}
                                        href={`/conocimiento/hilos/${h.id}`}
                                        className="mr-2 underline"
                                    >
                                        {h.titulo}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-2">
                    {leccion.estado === 'publicada' && !leccion.de_la_red && (
                        <Button
                            variant={leccion.valorada ? 'default' : 'outline'}
                            onClick={() => accion('valorar')}
                        >
                            <ThumbsUp className="size-4" />
                            Útil ({leccion.utiles})
                        </Button>
                    )}
                    {permisos.editar && (
                        <Button asChild variant="outline">
                            <Link href={`${base}/edit`}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    )}
                    {permisos.enviar && (
                        <Button onClick={() => accion('enviar')}>
                            <Send className="size-4" />
                            Enviar a revisión
                        </Button>
                    )}
                    {permisos.gestionar &&
                        ['en_revision', 'borrador'].includes(
                            leccion.estado,
                        ) && (
                            <Button onClick={() => accion('publicar')}>
                                <CheckCircle2 className="size-4" />
                                Validar y publicar
                            </Button>
                        )}
                    {permisos.gestionar && leccion.estado === 'en_revision' && (
                        <Button
                            variant="outline"
                            onClick={() => setDevolviendo(!devolviendo)}
                        >
                            <Undo2 className="size-4" />
                            Devolver
                        </Button>
                    )}
                    {permisos.gestionar && leccion.estado === 'publicada' && (
                        <>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    accion('compartir', {
                                        compartir: !leccion.compartida_red,
                                    })
                                }
                            >
                                <Globe className="size-4" />
                                {leccion.compartida_red
                                    ? 'Retirar de la red'
                                    : 'Compartir con la red'}
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={() => accion('archivar')}
                            >
                                <Archive className="size-4" />
                                Archivar
                            </Button>
                        </>
                    )}
                    {!leccion.de_la_red && leccion.estado === 'publicada' && (
                        <Button asChild variant="ghost">
                            <Link
                                href={`/conocimiento/comunidades?enlazable_tipo=leccion&enlazable_id=${leccion.id}`}
                            >
                                <MessageSquarePlus className="size-4" />
                                Discutir en una comunidad
                            </Link>
                        </Button>
                    )}
                </div>

                {devolviendo && (
                    <Card>
                        <CardContent className="grid gap-2 py-4">
                            <AreaTexto
                                value={motivo}
                                onChange={(e) => setMotivo(e.target.value)}
                                placeholder="Qué falta o qué hay que corregir"
                            />
                            <div>
                                <Button
                                    size="sm"
                                    disabled={motivo.trim() === ''}
                                    onClick={() =>
                                        accion('devolver', { motivo })
                                    }
                                >
                                    Devolver al autor
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function Seccion({ titulo, texto }: { titulo: string; texto: string }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription className="text-xs font-semibold tracking-[1px] uppercase">
                    {titulo}
                </CardDescription>
            </CardHeader>
            <CardContent className="text-sm whitespace-pre-line">
                {texto}
            </CardContent>
        </Card>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null }) {
    return (
        <div>
            <span className="text-muted-foreground">{etiqueta}: </span>
            {valor ?? '—'}
        </div>
    );
}

DetalleLeccion.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento' },
        { title: 'Lecciones aprendidas', href: '/conocimiento/lecciones' },
    ],
};
