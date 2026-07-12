import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { CamposTrazabilidad } from '@/components/parametros/campos-trazabilidad';
import { Button } from '@/components/ui/button';
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
import type { InsumoParam } from '@/types/parametros';

type FormAction = { action: string; method: 'get' | 'post' | 'put' | 'patch' | 'delete' };

export function InsumoForm({
    action,
    insumo,
    categorias,
    nivelesConfiabilidad,
    hrefCancelar,
}: {
    action: FormAction;
    insumo?: InsumoParam;
    categorias: string[];
    nivelesConfiabilidad: string[];
    hrefCancelar: string;
}) {
    const [activo, setActivo] = useState(insumo?.activo ?? true);
    const [categoria, setCategoria] = useState(insumo?.categoria ?? '');

    return (
        <Form {...action} options={{ preserveScroll: true }} className="max-w-3xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="codigo">Código interno</Label>
                            <Input id="codigo" name="codigo" defaultValue={insumo?.codigo ?? ''} required maxLength={30} placeholder="p. ej. MED-001" />
                            <InputError message={errors.codigo} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="nombre">Nombre</Label>
                            <Input id="nombre" name="nombre" defaultValue={insumo?.nombre ?? ''} required placeholder="p. ej. Oxitocina 10 UI" />
                            <InputError message={errors.nombre} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="categoria">Categoría</Label>
                            <Select name="categoria" defaultValue={insumo?.categoria} onValueChange={setCategoria} required>
                                <SelectTrigger id="categoria">
                                    <SelectValue placeholder="Seleccione" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categorias.map((c) => (
                                        <SelectItem key={c} value={c} className="capitalize">
                                            {c}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.categoria} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="codigo_atc">
                                Código ATC {categoria === 'medicamento' ? '(obligatorio para medicamentos)' : '(solo medicamentos)'}
                            </Label>
                            <Input id="codigo_atc" name="codigo_atc" defaultValue={insumo?.codigo_atc ?? ''} placeholder="p. ej. H01BB02" maxLength={7} />
                            <InputError message={errors.codigo_atc} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="unidad">Unidad de medida</Label>
                            <Input id="unidad" name="unidad" defaultValue={insumo?.unidad ?? ''} required maxLength={20} placeholder="p. ej. ampolla, unidad, par" />
                            <InputError message={errors.unidad} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="costo_unitario">Costo unitario (COP)</Label>
                            <Input id="costo_unitario" name="costo_unitario" type="number" step="0.01" min="0.01" defaultValue={insumo?.costo_unitario ?? ''} required />
                            <InputError message={errors.costo_unitario} />
                        </div>
                    </div>

                    <CamposTrazabilidad
                        niveles={nivelesConfiabilidad}
                        fuente={insumo?.fuente}
                        nivel={insumo?.nivel_confiabilidad}
                        errors={errors}
                    />

                    <div className="flex items-center gap-2">
                        <input type="hidden" name="activo" value={activo ? '1' : '0'} />
                        <Checkbox id="activo" checked={activo} onCheckedChange={(v) => setActivo(v === true)} />
                        <Label htmlFor="activo">Activo (disponible para registrar consumos)</Label>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button disabled={processing}>Guardar</Button>
                        <Button asChild variant="outline">
                            <Link href={hrefCancelar}>Cancelar</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
