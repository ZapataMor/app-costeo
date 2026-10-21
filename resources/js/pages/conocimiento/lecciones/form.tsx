import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { AreaTexto } from '@/components/conocimiento/area-texto';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { etiquetaEje } from '@/lib/graficas';
import type { CatalogosLeccion } from '@/types/conocimiento';

type Inicial = {
    tipo?: string;
    titulo?: string;
    categoria?: string;
    contexto?: string;
    leccion?: string | null;
    recomendacion?: string | null;
    causa?: string | null;
    procedimiento_quirurgico_id?: number | null;
    especialidad?: string | null;
    alerta_sobrecosto_id?: number | null;
    alerta_conocimiento_id?: number | null;
    impacto_estimado_cop?: number | null;
    etiquetas?: string[] | null;
    cirugias?: number[];
    hilo_id?: number;
};

const NINGUNO = 'ninguno';

export default function FormularioLeccion({
    leccion,
    inicial,
    categorias,
    causas,
    tipos,
    procedimientos,
}: {
    leccion: { id: number } | null;
    inicial: Inicial;
} & CatalogosLeccion) {
    const [etiquetasTexto, setEtiquetasTexto] = useState(
        (inicial.etiquetas ?? []).join(', '),
    );

    const { data, setData, post, put, transform, processing, errors } = useForm(
        {
            tipo: inicial.tipo ?? 'leccion',
            titulo: inicial.titulo ?? '',
            categoria: inicial.categoria ?? 'costos',
            contexto: inicial.contexto ?? '',
            leccion: inicial.leccion ?? '',
            recomendacion: inicial.recomendacion ?? '',
            causa: inicial.causa ?? '',
            procedimiento_quirurgico_id:
                inicial.procedimiento_quirurgico_id ?? null,
            especialidad: inicial.especialidad ?? '',
            alerta_sobrecosto_id: inicial.alerta_sobrecosto_id ?? null,
            alerta_conocimiento_id: inicial.alerta_conocimiento_id ?? null,
            impacto_estimado_cop: inicial.impacto_estimado_cop ?? '',
            cirugias: inicial.cirugias ?? [],
            hilo_id: inicial.hilo_id ?? null,
        },
    );

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        transform((d) => ({
            ...d,
            causa: d.causa || null,
            especialidad:
                d.especialidad ||
                procedimientos.find(
                    (p) => p.id === d.procedimiento_quirurgico_id,
                )?.especialidad ||
                null,
            impacto_estimado_cop:
                d.impacto_estimado_cop === '' ? null : d.impacto_estimado_cop,
            etiquetas: etiquetasTexto
                .split(',')
                .map((t) => t.trim())
                .filter(Boolean),
        }));

        if (leccion) {
            put(`/conocimiento/lecciones/${leccion.id}`);
        } else {
            post('/conocimiento/lecciones');
        }
    };

    const titulo = leccion ? 'Editar borrador' : 'Nueva lección';

    return (
        <>
            <Head title={titulo} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        aria-label="Volver"
                    >
                        <Link
                            href={
                                leccion
                                    ? `/conocimiento/lecciones/${leccion.id}`
                                    : '/conocimiento/lecciones'
                            }
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-xl font-semibold">{titulo}</h1>
                </div>

                <form onSubmit={enviar} className="grid max-w-4xl gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Qué pasó y qué aprendimos
                            </CardTitle>
                            <CardDescription>
                                Formato clásico de lección aprendida: el
                                contexto, la lección y lo que se recomienda
                                hacer distinto. Escriba para alguien que llega
                                nuevo al servicio.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-4 sm:grid-cols-[1fr_200px_200px]">
                                <Campo etiqueta="Título" error={errors.titulo}>
                                    <Input
                                        value={data.titulo}
                                        onChange={(e) =>
                                            setData('titulo', e.target.value)
                                        }
                                        required
                                    />
                                </Campo>
                                <Campo etiqueta="Tipo" error={errors.tipo}>
                                    <Select
                                        value={data.tipo}
                                        onValueChange={(v) =>
                                            setData('tipo', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {tipos.map((t) => (
                                                <SelectItem
                                                    key={t.valor}
                                                    value={t.valor}
                                                >
                                                    {t.etiqueta}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Campo>
                                <Campo
                                    etiqueta="Categoría"
                                    error={errors.categoria}
                                >
                                    <Select
                                        value={data.categoria}
                                        onValueChange={(v) =>
                                            setData('categoria', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categorias.map((c) => (
                                                <SelectItem
                                                    key={c.valor}
                                                    value={c.valor}
                                                >
                                                    {c.etiqueta}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Campo>
                            </div>
                            <Campo
                                etiqueta="Contexto: ¿qué pasó?"
                                error={errors.contexto}
                            >
                                <AreaTexto
                                    value={data.contexto}
                                    onChange={(e) =>
                                        setData('contexto', e.target.value)
                                    }
                                    rows={4}
                                    required
                                />
                            </Campo>
                            <Campo
                                etiqueta="Lección: ¿qué aprendimos?"
                                error={errors.leccion}
                            >
                                <AreaTexto
                                    value={data.leccion}
                                    onChange={(e) =>
                                        setData('leccion', e.target.value)
                                    }
                                    rows={4}
                                    required
                                />
                            </Campo>
                            <Campo
                                etiqueta="Recomendación: ¿qué hacer distinto? (opcional)"
                                error={errors.recomendacion}
                            >
                                <AreaTexto
                                    value={data.recomendacion}
                                    onChange={(e) =>
                                        setData('recomendacion', e.target.value)
                                    }
                                    rows={3}
                                />
                            </Campo>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Para encontrarla después
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta="Procedimiento"
                                error={errors.procedimiento_quirurgico_id}
                            >
                                <Select
                                    value={
                                        data.procedimiento_quirurgico_id
                                            ? String(
                                                  data.procedimiento_quirurgico_id,
                                              )
                                            : NINGUNO
                                    }
                                    onValueChange={(v) =>
                                        setData(
                                            'procedimiento_quirurgico_id',
                                            v === NINGUNO ? null : Number(v),
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NINGUNO}>
                                            Varios / ninguno en particular
                                        </SelectItem>
                                        {procedimientos.map((p) => (
                                            <SelectItem
                                                key={p.id}
                                                value={String(p.id)}
                                            >
                                                {etiquetaEje(p.nombre, 50)} (
                                                {p.codigo_cups})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Campo>
                            <Campo etiqueta="Causa" error={errors.causa}>
                                <Select
                                    value={data.causa || NINGUNO}
                                    onValueChange={(v) =>
                                        setData('causa', v === NINGUNO ? '' : v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NINGUNO}>
                                            Sin causa específica
                                        </SelectItem>
                                        {causas.map((c) => (
                                            <SelectItem
                                                key={c.valor}
                                                value={c.valor}
                                            >
                                                {c.etiqueta}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Campo>
                            <Campo
                                etiqueta="Impacto estimado (COP)"
                                error={errors.impacto_estimado_cop}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    value={data.impacto_estimado_cop ?? ''}
                                    onChange={(e) =>
                                        setData(
                                            'impacto_estimado_cop',
                                            e.target.value === ''
                                                ? ''
                                                : Number(e.target.value),
                                        )
                                    }
                                />
                            </Campo>
                            <Campo etiqueta="Etiquetas (separadas por coma)">
                                <Input
                                    value={etiquetasTexto}
                                    onChange={(e) =>
                                        setEtiquetasTexto(e.target.value)
                                    }
                                    placeholder="anestesia, suturas, recambio"
                                />
                            </Campo>
                            {data.cirugias.length > 0 && (
                                <p className="text-xs text-muted-foreground sm:col-span-2">
                                    Evidencia: cirugía(s){' '}
                                    {data.cirugias
                                        .map((id) => `#${id}`)
                                        .join(', ')}
                                    . En la red no se comparten.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex gap-2">
                        <Button disabled={processing}>
                            {leccion ? 'Guardar cambios' : 'Guardar borrador'}
                        </Button>
                        <Button asChild variant="outline" type="button">
                            <Link href="/conocimiento/lecciones">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function Campo({
    etiqueta,
    error,
    children,
}: {
    etiqueta: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{etiqueta}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

FormularioLeccion.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento' },
        { title: 'Lecciones aprendidas', href: '/conocimiento/lecciones' },
    ],
};
