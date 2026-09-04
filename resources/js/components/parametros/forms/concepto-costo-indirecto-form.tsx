import { Form, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { CamposTrazabilidad } from '@/components/parametros/campos-trazabilidad';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    BaseAsignacionOpcion,
    CategoriaCifOpcion,
    ConceptoCostoIndirectoParam,
} from '@/types/parametros';

type FormAction = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

const etiquetaCategoria = (valor: string) =>
    valor.charAt(0).toUpperCase() + valor.slice(1).replace(/_/g, ' ');

export function ConceptoCostoIndirectoForm({
    action,
    concepto,
    categorias,
    basesAsignacion,
    nivelesConfiabilidad,
    hrefCancelar,
    onSuccess,
    onCancelar,
}: {
    action: FormAction;
    concepto?: ConceptoCostoIndirectoParam;
    categorias: CategoriaCifOpcion[];
    basesAsignacion: BaseAsignacionOpcion[];
    nivelesConfiabilidad: string[];
    hrefCancelar?: string;
    onSuccess?: () => void;
    onCancelar?: () => void;
}) {
    const [categoria, setCategoria] = useState(
        concepto?.categoria ?? categorias[0]?.valor ?? '',
    );
    const [base, setBase] = useState(
        concepto?.base_asignacion ?? basesAsignacion[0]?.valor ?? '',
    );

    // El monto y el porcentaje son excluyentes: el backend prohíbe el campo
    // que no corresponde, así que aquí solo se muestra el que aplica.
    const usaPorcentaje =
        basesAsignacion.find((b) => b.valor === base)?.usaPorcentaje ?? false;

    const categoriaElegida = categorias.find((c) => c.valor === categoria);

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            onSuccess={onSuccess}
            className="max-w-3xl space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="nombre">Nombre del concepto</Label>
                            <Input
                                id="nombre"
                                name="nombre"
                                defaultValue={concepto?.nombre ?? ''}
                                required
                                placeholder="p. ej. Energía eléctrica"
                            />
                            <InputError message={errors.nombre} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="categoria">Categoría</Label>
                            <Select
                                name="categoria"
                                value={categoria}
                                onValueChange={setCategoria}
                            >
                                <SelectTrigger id="categoria">
                                    <SelectValue placeholder="Seleccione" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categorias.map((c) => (
                                        <SelectItem
                                            key={c.valor}
                                            value={c.valor}
                                        >
                                            {etiquetaCategoria(c.valor)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.categoria} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="base_asignacion">
                                Base de asignación (inductor)
                            </Label>
                            <Select
                                name="base_asignacion"
                                value={base}
                                onValueChange={setBase}
                            >
                                <SelectTrigger id="base_asignacion">
                                    <SelectValue placeholder="Seleccione" />
                                </SelectTrigger>
                                <SelectContent>
                                    {basesAsignacion.map((b) => (
                                        <SelectItem
                                            key={b.valor}
                                            value={b.valor}
                                        >
                                            {b.etiqueta}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.base_asignacion} />
                        </div>

                        {usaPorcentaje ? (
                            <div className="grid gap-2">
                                <Label htmlFor="porcentaje">
                                    Porcentaje del costo directo (0–1)
                                </Label>
                                <Input
                                    id="porcentaje"
                                    name="porcentaje"
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    max="1"
                                    defaultValue={concepto?.porcentaje ?? ''}
                                    required
                                    placeholder="0.05 = 5 %"
                                />
                                <InputError message={errors.porcentaje} />
                            </div>
                        ) : (
                            <div className="grid gap-2">
                                <Label htmlFor="monto_mensual">
                                    Monto mensual de la bolsa (COP)
                                </Label>
                                <Input
                                    id="monto_mensual"
                                    name="monto_mensual"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    defaultValue={concepto?.monto_mensual ?? ''}
                                    required
                                />
                                <InputError message={errors.monto_mensual} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="vigente_desde">Vigente desde</Label>
                            <Input
                                id="vigente_desde"
                                name="vigente_desde"
                                type="date"
                                defaultValue={
                                    concepto?.vigente_desde?.slice(0, 10) ?? ''
                                }
                                required
                            />
                            <InputError message={errors.vigente_desde} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="vigente_hasta">
                                Vigente hasta (vacío = indefinido)
                            </Label>
                            <Input
                                id="vigente_hasta"
                                name="vigente_hasta"
                                type="date"
                                defaultValue={
                                    concepto?.vigente_hasta?.slice(0, 10) ?? ''
                                }
                            />
                            <InputError message={errors.vigente_hasta} />
                        </div>
                    </div>

                    {categoriaElegida?.solapa && (
                        <div className="flex gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                            <p className="text-muted-foreground">
                                Esta categoría se solapa con{' '}
                                <strong>{categoriaElegida.solape}</strong>, que
                                hoy ya entra al costo directo. El concepto queda{' '}
                                <strong>inactivo</strong> al guardarlo: al
                                activar la categoría deberás marcar ese
                                componente como derivado de CIF, o el costo se
                                contaría dos veces.
                            </p>
                        </div>
                    )}

                    <CamposTrazabilidad
                        niveles={nivelesConfiabilidad}
                        fuente={concepto?.fuente}
                        nivel={concepto?.nivel_confiabilidad}
                        errors={errors}
                    />

                    <div className="flex items-center gap-3">
                        <Button disabled={processing}>Guardar</Button>
                        {onCancelar ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onCancelar}
                            >
                                Cancelar
                            </Button>
                        ) : (
                            hrefCancelar && (
                                <Button asChild variant="outline">
                                    <Link href={hrefCancelar}>Cancelar</Link>
                                </Button>
                            )
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}
