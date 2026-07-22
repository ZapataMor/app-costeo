import { useForm } from '@inertiajs/react';
import { HeartPulse, Pencil } from 'lucide-react';
import { useState } from 'react';
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
import type { ResultadoClinico } from '@/types/cirugias';

/**
 * Captura del resultado clínico (dimensión «resultado» de Donabedian).
 * Cierra el indicador de completitud y es el insumo de la Capa 4: sin
 * complicaciones registradas no hay lecciones que aprender.
 */
export function ResultadoClinicoCard({
    cirugiaId,
    resultado,
}: {
    cirugiaId: number;
    resultado: ResultadoClinico | null;
}) {
    const [editando, setEditando] = useState(resultado === null);

    const { data, setData, post, processing, errors } = useForm({
        complicacion_intraoperatoria:
            resultado?.complicacion_intraoperatoria ?? false,
        descripcion_complicacion_intra:
            resultado?.descripcion_complicacion_intra ?? '',
        complicacion_postoperatoria:
            resultado?.complicacion_postoperatoria ?? false,
        descripcion_complicacion_post:
            resultado?.descripcion_complicacion_post ?? '',
        dias_estancia: String(resultado?.dias_estancia ?? 0),
        reingreso_30_dias: resultado?.reingreso_30_dias ?? false,
        mortalidad: resultado?.mortalidad ?? false,
    });

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/cirugias/${cirugiaId}/resultado-clinico`, {
            preserveScroll: true,
            onSuccess: () => setEditando(false),
        });
    };

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-2">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <HeartPulse className="size-4 text-muted-foreground" />
                        Resultado clínico
                    </CardTitle>
                    <CardDescription>
                        Complicaciones, estancia, reingreso y mortalidad.
                    </CardDescription>
                </div>
                {resultado !== null && !editando && (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Editar resultado clínico"
                        onClick={() => setEditando(true)}
                    >
                        <Pencil className="size-4" />
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {editando ? (
                    <form onSubmit={guardar} className="space-y-4">
                        <div className="space-y-3">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.complicacion_intraoperatoria}
                                    onCheckedChange={(v) =>
                                        setData(
                                            'complicacion_intraoperatoria',
                                            v === true,
                                        )
                                    }
                                />
                                Hubo complicación intraoperatoria
                            </label>
                            {data.complicacion_intraoperatoria && (
                                <div className="grid gap-2">
                                    <Input
                                        aria-label="Descripción de la complicación intraoperatoria"
                                        placeholder="¿Qué ocurrió?"
                                        value={
                                            data.descripcion_complicacion_intra
                                        }
                                        onChange={(e) =>
                                            setData(
                                                'descripcion_complicacion_intra',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors.descripcion_complicacion_intra
                                        }
                                    />
                                </div>
                            )}

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.complicacion_postoperatoria}
                                    onCheckedChange={(v) =>
                                        setData(
                                            'complicacion_postoperatoria',
                                            v === true,
                                        )
                                    }
                                />
                                Hubo complicación postoperatoria
                            </label>
                            {data.complicacion_postoperatoria && (
                                <div className="grid gap-2">
                                    <Input
                                        aria-label="Descripción de la complicación postoperatoria"
                                        placeholder="¿Qué ocurrió?"
                                        value={
                                            data.descripcion_complicacion_post
                                        }
                                        onChange={(e) =>
                                            setData(
                                                'descripcion_complicacion_post',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors.descripcion_complicacion_post
                                        }
                                    />
                                </div>
                            )}

                            <div className="grid max-w-48 gap-2">
                                <Label htmlFor="dias_estancia">
                                    Días de estancia
                                </Label>
                                <Input
                                    id="dias_estancia"
                                    type="number"
                                    min={0}
                                    max={365}
                                    value={data.dias_estancia}
                                    onChange={(e) =>
                                        setData('dias_estancia', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.dias_estancia} />
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.reingreso_30_dias}
                                    onCheckedChange={(v) =>
                                        setData('reingreso_30_dias', v === true)
                                    }
                                />
                                Reingreso dentro de 30 días
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.mortalidad}
                                    onCheckedChange={(v) =>
                                        setData('mortalidad', v === true)
                                    }
                                />
                                Mortalidad asociada
                            </label>
                        </div>

                        <div className="flex items-center gap-3">
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                Guardar resultado
                            </Button>
                            {resultado !== null && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setEditando(false)}
                                >
                                    Cancelar
                                </Button>
                            )}
                        </div>
                    </form>
                ) : (
                    <dl className="space-y-2 text-sm">
                        <Fila
                            termino="Complicación intraoperatoria"
                            valor={resultado?.complicacion_intraoperatoria}
                            detalle={resultado?.descripcion_complicacion_intra}
                        />
                        <Fila
                            termino="Complicación postoperatoria"
                            valor={resultado?.complicacion_postoperatoria}
                            detalle={resultado?.descripcion_complicacion_post}
                        />
                        <div className="flex justify-between border-b pb-2">
                            <dt className="text-muted-foreground">
                                Días de estancia
                            </dt>
                            <dd className="font-medium tabular-nums">
                                {resultado?.dias_estancia ?? 0}
                            </dd>
                        </div>
                        <Fila
                            termino="Reingreso a 30 días"
                            valor={resultado?.reingreso_30_dias}
                        />
                        <Fila
                            termino="Mortalidad"
                            valor={resultado?.mortalidad}
                        />
                    </dl>
                )}
            </CardContent>
        </Card>
    );
}

function Fila({
    termino,
    valor,
    detalle,
}: {
    termino: string;
    valor?: boolean;
    detalle?: string | null;
}) {
    return (
        <div className="flex items-start justify-between gap-3 border-b pb-2 last:border-0">
            <div className="min-w-0">
                <dt className="text-muted-foreground">{termino}</dt>
                {valor && detalle && (
                    <p className="text-xs text-muted-foreground">{detalle}</p>
                )}
            </div>
            <dd>
                <Badge variant={valor ? 'destructive' : 'secondary'}>
                    {valor ? 'Sí' : 'No'}
                </Badge>
            </dd>
        </div>
    );
}
