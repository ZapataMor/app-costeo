import { Form, Link } from '@inertiajs/react';
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
import type { ProcedimientoParam } from '@/types/parametros';

type FormAction = { action: string; method: 'get' | 'post' | 'put' | 'patch' | 'delete' };

export function ProcedimientoForm({
    action,
    procedimiento,
    complejidades,
    nivelesConfiabilidad,
    hrefCancelar,
}: {
    action: FormAction;
    procedimiento?: ProcedimientoParam;
    complejidades: string[];
    nivelesConfiabilidad: string[];
    hrefCancelar: string;
}) {
    return (
        <Form {...action} options={{ preserveScroll: true }} className="max-w-3xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="codigo_cups">Código CUPS (6 dígitos)</Label>
                            <Input id="codigo_cups" name="codigo_cups" defaultValue={procedimiento?.codigo_cups ?? ''} required maxLength={6} placeholder="p. ej. 740001" />
                            <InputError message={errors.codigo_cups} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="nombre">Nombre</Label>
                            <Input id="nombre" name="nombre" defaultValue={procedimiento?.nombre ?? ''} required placeholder="p. ej. Cesárea segmentaria" />
                            <InputError message={errors.nombre} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="especialidad">Especialidad</Label>
                            <Input id="especialidad" name="especialidad" defaultValue={procedimiento?.especialidad ?? ''} required maxLength={120} placeholder="p. ej. Ginecobstetricia" />
                            <InputError message={errors.especialidad} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="complejidad">Complejidad</Label>
                            <Select name="complejidad" defaultValue={procedimiento?.complejidad ?? 'media'} required>
                                <SelectTrigger id="complejidad">
                                    <SelectValue placeholder="Seleccione" />
                                </SelectTrigger>
                                <SelectContent>
                                    {complejidades.map((c) => (
                                        <SelectItem key={c} value={c} className="capitalize">
                                            {c}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.complejidad} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="duracion_estimada_minutos">Duración estimada (minutos)</Label>
                            <Input id="duracion_estimada_minutos" name="duracion_estimada_minutos" type="number" min="1" max="1440" defaultValue={procedimiento?.duracion_estimada_minutos ?? ''} required />
                            <InputError message={errors.duracion_estimada_minutos} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tarifa_soat">Tarifa SOAT de referencia (COP, opcional)</Label>
                            <Input id="tarifa_soat" name="tarifa_soat" type="number" step="0.01" min="0" defaultValue={procedimiento?.tarifa_soat ?? ''} />
                            <InputError message={errors.tarifa_soat} />
                        </div>
                    </div>

                    <CamposTrazabilidad
                        niveles={nivelesConfiabilidad}
                        fuente={procedimiento?.fuente}
                        nivel={procedimiento?.nivel_confiabilidad}
                        errors={errors}
                    />

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
