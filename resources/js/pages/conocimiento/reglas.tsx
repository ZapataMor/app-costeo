import { Head, Link, router, useForm } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { SeveridadBadge } from '@/components/conocimiento/severidad-badge';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { fechaHora } from '@/lib/formato';
import type { ReglaConocimiento, Severidad } from '@/types/conocimiento';

const ETIQUETA_SEVERIDAD: Record<Severidad, string> = {
    critica: 'Crítica',
    advertencia: 'Advertencia',
    informativa: 'Informativa',
};

export default function Reglas({
    reglas,
    severidades,
}: {
    reglas: ReglaConocimiento[];
    severidades: Severidad[];
}) {
    const evaluadaEn = reglas
        .map((r) => r.evaluada_en)
        .filter(Boolean)
        .sort()
        .pop();

    return (
        <>
            <Head title="Reglas de conocimiento" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Reglas de conocimiento
                        </h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Las reglas de alcance <strong>cirugía</strong> se
                            evalúan cada vez que se costea un caso; las de{' '}
                            <strong>periodo</strong> miran meses cerrados. Al
                            cambiar un umbral, reevalúe para aplicarlo al
                            histórico: las alertas ya revisadas no se tocan.
                            {evaluadaEn &&
                                ` Última evaluación: ${fechaHora(evaluadaEn)}`}
                        </p>
                    </div>
                    <Button
                        onClick={() =>
                            router.post(
                                '/conocimiento/reglas/evaluar',
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Play className="size-4" />
                        Evaluar el histórico
                    </Button>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {reglas.map((regla) => (
                        <TarjetaRegla
                            key={regla.id}
                            regla={regla}
                            severidades={severidades}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

function TarjetaRegla({
    regla,
    severidades,
}: {
    regla: ReglaConocimiento;
    severidades: Severidad[];
}) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        activa: regla.activa,
        severidad: regla.severidad,
        parametros: Object.fromEntries(
            regla.parametros.map((p) => [p.clave, p.valor]),
        ) as Record<string, number>,
    });

    return (
        <Card className={regla.activa ? undefined : 'opacity-70'}>
            <CardHeader>
                <div className="flex flex-wrap items-center gap-2">
                    <CardTitle className="text-base">{regla.nombre}</CardTitle>
                    <Badge variant="outline">
                        {regla.alcance === 'cirugia'
                            ? 'Por cirugía'
                            : 'Por mes'}
                    </Badge>
                    <SeveridadBadge severidad={regla.severidad} />
                </div>
                <CardDescription>{regla.descripcion}</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(`/conocimiento/reglas/${regla.id}`, {
                            preserveScroll: true,
                        });
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        {regla.parametros.map((p) => (
                            <div key={p.clave} className="grid gap-1.5">
                                <Label htmlFor={`${regla.id}-${p.clave}`}>
                                    {p.etiqueta} ({p.unidad})
                                </Label>
                                <Input
                                    id={`${regla.id}-${p.clave}`}
                                    type="number"
                                    min="0"
                                    step="any"
                                    value={data.parametros[p.clave]}
                                    onChange={(e) =>
                                        setData('parametros', {
                                            ...data.parametros,
                                            [p.clave]: Number(e.target.value),
                                        })
                                    }
                                />
                                <InputError
                                    message={
                                        (errors as Record<string, string>)[
                                            `parametros.${p.clave}`
                                        ]
                                    }
                                />
                            </div>
                        ))}
                        <div className="grid gap-1.5">
                            <Label>Severidad</Label>
                            <Select
                                value={data.severidad}
                                onValueChange={(v) =>
                                    setData('severidad', v as Severidad)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {severidades.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {ETIQUETA_SEVERIDAD[s]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.activa}
                                onCheckedChange={(v) =>
                                    setData('activa', v === true)
                                }
                            />
                            Regla activa
                        </label>
                        <div className="flex items-center gap-3">
                            <Link
                                href={`/conocimiento/alertas?tipo=${regla.tipo}&estado=pendiente`}
                                className="text-xs text-muted-foreground hover:underline"
                            >
                                {regla.pendientes} pendientes de {regla.total}
                            </Link>
                            <Button size="sm" disabled={processing || !isDirty}>
                                Guardar
                            </Button>
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

Reglas.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento' },
        { title: 'Reglas', href: '/conocimiento/reglas' },
    ],
};
