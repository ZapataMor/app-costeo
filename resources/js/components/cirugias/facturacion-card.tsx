import { useForm } from '@inertiajs/react';
import { Pencil, Receipt } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cop } from '@/lib/formato';
import type { Facturacion } from '@/types/cirugias';

/**
 * Captura de la facturación del procedimiento. Es el origen de los KPIs de
 * margen, glosas y recaudo: sin ella, el panel de Rentabilidad no tiene con
 * qué comparar el costo TDABC.
 */
export function FacturacionCard({
    cirugiaId,
    facturacion,
    costoTotal,
}: {
    cirugiaId: number;
    facturacion: Facturacion | null;
    costoTotal: number | null;
}) {
    const [editando, setEditando] = useState(facturacion === null);

    const { data, setData, post, processing, errors } = useForm({
        valor_facturado: facturacion?.valor_facturado ?? '',
        valor_glosado: facturacion?.valor_glosado ?? '0',
        valor_recaudado: facturacion?.valor_recaudado ?? '0',
        tarifa_referencia_soat: facturacion?.tarifa_referencia_soat ?? '',
        fecha_facturacion: facturacion?.fecha_facturacion ?? '',
    });

    const guardar = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/cirugias/${cirugiaId}/facturacion`, {
            preserveScroll: true,
            onSuccess: () => setEditando(false),
        });
    };

    const facturado = Number(facturacion?.valor_facturado ?? 0);
    const margen =
        facturacion !== null && costoTotal !== null
            ? facturado - costoTotal
            : null;

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-2">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Receipt className="size-4 text-muted-foreground" />
                        Facturación
                    </CardTitle>
                    <CardDescription>
                        Alimenta los indicadores de margen, glosas y recaudo.
                    </CardDescription>
                </div>
                {facturacion !== null && !editando && (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Editar facturación"
                        onClick={() => setEditando(true)}
                    >
                        <Pencil className="size-4" />
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {editando ? (
                    <form onSubmit={guardar} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="valor_facturado">
                                    Valor facturado (COP)
                                </Label>
                                <Input
                                    id="valor_facturado"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.valor_facturado}
                                    onChange={(e) =>
                                        setData(
                                            'valor_facturado',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={errors.valor_facturado} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fecha_facturacion">
                                    Fecha de facturación (opcional)
                                </Label>
                                <Input
                                    id="fecha_facturacion"
                                    type="date"
                                    value={data.fecha_facturacion}
                                    onChange={(e) =>
                                        setData(
                                            'fecha_facturacion',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.fecha_facturacion}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="valor_glosado">
                                    Valor glosado (COP)
                                </Label>
                                <Input
                                    id="valor_glosado"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.valor_glosado}
                                    onChange={(e) =>
                                        setData('valor_glosado', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.valor_glosado} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="valor_recaudado">
                                    Valor recaudado (COP)
                                </Label>
                                <Input
                                    id="valor_recaudado"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.valor_recaudado}
                                    onChange={(e) =>
                                        setData(
                                            'valor_recaudado',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={errors.valor_recaudado} />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="tarifa_referencia_soat">
                                    Tarifa de referencia SOAT (opcional)
                                </Label>
                                <Input
                                    id="tarifa_referencia_soat"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.tarifa_referencia_soat}
                                    onChange={(e) =>
                                        setData(
                                            'tarifa_referencia_soat',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.tarifa_referencia_soat}
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <Button type="submit" size="sm" disabled={processing}>
                                Guardar facturación
                            </Button>
                            {facturacion !== null && (
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
                            termino="Facturado"
                            valor={cop(facturado)}
                        />
                        <Fila
                            termino="Glosado"
                            valor={cop(Number(facturacion?.valor_glosado ?? 0))}
                        />
                        <Fila
                            termino="Recaudado"
                            valor={cop(
                                Number(facturacion?.valor_recaudado ?? 0),
                            )}
                        />
                        {facturacion?.tarifa_referencia_soat && (
                            <Fila
                                termino="Referencia SOAT"
                                valor={cop(
                                    Number(facturacion.tarifa_referencia_soat),
                                )}
                            />
                        )}
                        {margen !== null && (
                            <div className="flex items-center justify-between border-t pt-2">
                                <dt className="font-medium">
                                    Margen sobre el costo TDABC
                                </dt>
                                <dd>
                                    <Badge
                                        variant={
                                            margen >= 0
                                                ? 'secondary'
                                                : 'destructive'
                                        }
                                        className="tabular-nums"
                                    >
                                        {cop(margen)}
                                    </Badge>
                                </dd>
                            </div>
                        )}
                    </dl>
                )}
            </CardContent>
        </Card>
    );
}

function Fila({ termino, valor }: { termino: string; valor: string }) {
    return (
        <div className="flex justify-between border-b pb-2 last:border-0">
            <dt className="text-muted-foreground">{termino}</dt>
            <dd className="font-medium tabular-nums">{valor}</dd>
        </div>
    );
}
