import { Head, Link, router, usePage } from '@inertiajs/react';
import { Globe, Plus, Search, ThumbsUp } from 'lucide-react';
import { useState } from 'react';
import { EstadoLeccionBadge } from '@/components/conocimiento/estado-leccion-badge';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cop, fecha } from '@/lib/formato';
import { etiquetaEje } from '@/lib/graficas';
import type {
    CatalogosLeccion,
    LeccionResumen,
    Paginado,
} from '@/types/conocimiento';

type Filtros = {
    q: string;
    categoria: string;
    tipo: string;
    procedimiento: number | null;
};

const NOMBRE_VISTA: Record<string, string> = {
    publicadas: 'Repositorio del hospital',
    mias: 'Mis lecciones',
    revision: 'Por validar',
    red: 'Red de hospitales',
};

export default function Lecciones({
    lecciones,
    vista,
    vistas,
    filtros,
    conteos,
    categorias,
    tipos,
    procedimientos,
}: {
    lecciones: Paginado<LeccionResumen>;
    vista: string;
    vistas: string[];
    filtros: Filtros;
    conteos: { revision: number; mias_borrador: number };
} & CatalogosLeccion) {
    const [q, setQ] = useState(filtros.q);
    const { auth } = usePage().props;

    const filtrar = (cambios: Partial<Filtros> & { vista?: string }) =>
        router.get(
            '/conocimiento/lecciones',
            { vista, ...filtros, ...cambios },
            { preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Lecciones aprendidas" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Lecciones aprendidas y mejores prácticas
                        </h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Lo que el hospital aprendió de sus casos, escrito
                            para que no se pierda cuando alguien rota. Toda
                            lección pasa por validación antes de publicarse; la
                            red reúne lo que otros hospitales decidieron
                            compartir, sin personas ni pacientes.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/conocimiento/lecciones/create">
                            <Plus className="size-4" />
                            Nueva lección
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-1">
                    {vistas.map((v) => (
                        <Button
                            key={v}
                            size="sm"
                            variant={vista === v ? 'default' : 'outline'}
                            onClick={() => filtrar({ vista: v })}
                        >
                            {v === 'red' && <Globe className="size-4" />}
                            {NOMBRE_VISTA[v]}
                            {v === 'revision' && conteos.revision > 0 && (
                                <Badge variant="secondary" className="ml-1">
                                    {conteos.revision}
                                </Badge>
                            )}
                            {v === 'mias' && conteos.mias_borrador > 0 && (
                                <Badge variant="secondary" className="ml-1">
                                    {conteos.mias_borrador} borrador
                                </Badge>
                            )}
                        </Button>
                    ))}
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        filtrar({ q });
                    }}
                    className="flex flex-wrap items-center gap-2"
                >
                    <div className="relative">
                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar en título y contenido"
                            className="w-72 pl-8"
                        />
                    </div>
                    <Filtro
                        valor={filtros.categoria}
                        placeholder="Categoría"
                        opciones={categorias.map((c) => ({
                            valor: c.valor,
                            etiqueta: c.etiqueta,
                        }))}
                        alCambiar={(categoria) => filtrar({ categoria })}
                    />
                    <Filtro
                        valor={filtros.tipo}
                        placeholder="Tipo"
                        opciones={tipos}
                        alCambiar={(tipo) => filtrar({ tipo })}
                    />
                    {vista !== 'red' && (
                        <Filtro
                            valor={
                                filtros.procedimiento
                                    ? String(filtros.procedimiento)
                                    : ''
                            }
                            placeholder="Procedimiento"
                            opciones={procedimientos.map((p) => ({
                                valor: String(p.id),
                                etiqueta: etiquetaEje(p.nombre, 40),
                            }))}
                            alCambiar={(v) =>
                                filtrar({ procedimiento: v ? Number(v) : null })
                            }
                        />
                    )}
                </form>

                {lecciones.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            {vista === 'red'
                                ? 'Ningún otro hospital ha compartido lecciones todavía.'
                                : 'No hay lecciones aquí. La primera puede salir de una alerta revisada o de un hilo de comunidad.'}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {lecciones.data.map((l) => (
                            <Card key={l.id} className="flex flex-col">
                                <CardHeader>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {l.tipo_etiqueta}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {l.categoria_etiqueta}
                                        </Badge>
                                        {vista !== 'publicadas' &&
                                            vista !== 'red' && (
                                                <EstadoLeccionBadge
                                                    estado={l.estado}
                                                    etiqueta={l.estado_etiqueta}
                                                />
                                            )}
                                        {l.compartida_red && !l.de_la_red && (
                                            <Badge variant="outline">
                                                <Globe className="size-3" />
                                                En la red
                                            </Badge>
                                        )}
                                    </div>
                                    <CardTitle className="text-base">
                                        <Link
                                            href={
                                                l.de_la_red
                                                    ? `/conocimiento/lecciones/red/${l.id}`
                                                    : `/conocimiento/lecciones/${l.id}`
                                            }
                                            className="hover:underline"
                                        >
                                            {l.titulo}
                                        </Link>
                                    </CardTitle>
                                    <CardDescription>
                                        {l.de_la_red
                                            ? l.hospital
                                            : (l.autor ?? 'Autor eliminado')}
                                        {l.procedimiento &&
                                            ` · ${etiquetaEje(l.procedimiento.nombre, 40)}`}
                                        {l.publicada_en &&
                                            ` · ${fecha(l.publicada_en)}`}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="mt-auto space-y-2 text-sm">
                                    <p className="text-muted-foreground">
                                        {l.extracto}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                        {l.impacto_estimado_cop !== null && (
                                            <span>
                                                Impacto{' '}
                                                {cop(l.impacto_estimado_cop)}
                                            </span>
                                        )}
                                        {l.causa_etiqueta && (
                                            <span>{l.causa_etiqueta}</span>
                                        )}
                                        <span className="flex items-center gap-1">
                                            <ThumbsUp className="size-3" />
                                            {l.utiles}
                                        </span>
                                        {l.etiquetas.map((e) => (
                                            <Badge
                                                key={e}
                                                variant="outline"
                                                className="text-[10px]"
                                            >
                                                #{e}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <Paginacion
                    links={lecciones.links}
                    total={lecciones.total}
                    from={lecciones.from}
                    to={lecciones.to}
                />
                {auth.user?.role === 'profesional' && (
                    <p className="text-xs text-muted-foreground">
                        Sus lecciones las valida el administrador del hospital
                        antes de publicarse.
                    </p>
                )}
            </div>
        </>
    );
}

function Filtro({
    valor,
    placeholder,
    opciones,
    alCambiar,
}: {
    valor: string;
    placeholder: string;
    opciones: { valor: string; etiqueta: string }[];
    alCambiar: (v: string) => void;
}) {
    return (
        <Select
            value={valor || 'todas'}
            onValueChange={(v) => alCambiar(v === 'todas' ? '' : v)}
        >
            <SelectTrigger className="w-52">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="todas">{placeholder}: todas</SelectItem>
                {opciones.map((o) => (
                    <SelectItem key={o.valor} value={o.valor}>
                        {o.etiqueta}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

Lecciones.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento' },
        { title: 'Lecciones aprendidas', href: '/conocimiento/lecciones' },
    ],
};
