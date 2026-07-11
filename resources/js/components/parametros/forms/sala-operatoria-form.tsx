import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { CamposTrazabilidad } from '@/components/parametros/campos-trazabilidad';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SalaOperatoriaParam } from '@/types/parametros';

type FormAction = { action: string; method: 'get' | 'post' | 'put' | 'patch' | 'delete' };

export function SalaOperatoriaForm({
    action,
    sala,
    nivelesConfiabilidad,
    hrefCancelar,
}: {
    action: FormAction;
    sala?: SalaOperatoriaParam;
    nivelesConfiabilidad: string[];
    hrefCancelar: string;
}) {
    const [activa, setActiva] = useState(sala?.activa ?? true);
    // El equipamiento se captura como texto separado por comas y se envía como arreglo.
    const [equipamiento, setEquipamiento] = useState((sala?.equipamiento ?? []).join(', '));

    const items = equipamiento
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item.length > 0);

    return (
        <Form {...action} options={{ preserveScroll: true }} className="max-w-3xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="nombre">Nombre</Label>
                            <Input id="nombre" name="nombre" defaultValue={sala?.nombre ?? ''} required placeholder="p. ej. Sala 1" />
                            <InputError message={errors.nombre} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ubicacion">Ubicación (opcional)</Label>
                            <Input id="ubicacion" name="ubicacion" defaultValue={sala?.ubicacion ?? ''} placeholder="p. ej. Piso 2 - Central quirúrgica" />
                            <InputError message={errors.ubicacion} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="costo_hora">Costo por hora (COP)</Label>
                            <Input id="costo_hora" name="costo_hora" type="number" step="0.01" min="0.01" defaultValue={sala?.costo_hora ?? ''} required />
                            <InputError message={errors.costo_hora} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="equipamiento_texto">Equipamiento (separado por comas)</Label>
                            <Input
                                id="equipamiento_texto"
                                value={equipamiento}
                                onChange={(e) => setEquipamiento(e.target.value)}
                                placeholder="p. ej. lámpara cielítica, mesa quirúrgica"
                            />
                            {items.map((item, i) => (
                                <input key={i} type="hidden" name={`equipamiento[${i}]`} value={item} />
                            ))}
                            <InputError message={errors.equipamiento} />
                        </div>
                    </div>

                    <CamposTrazabilidad
                        niveles={nivelesConfiabilidad}
                        fuente={sala?.fuente}
                        nivel={sala?.nivel_confiabilidad}
                        errors={errors}
                    />

                    <div className="flex items-center gap-2">
                        <input type="hidden" name="activa" value={activa ? '1' : '0'} />
                        <Checkbox id="activa" checked={activa} onCheckedChange={(v) => setActiva(v === true)} />
                        <Label htmlFor="activa">Activa (disponible para programar cirugías)</Label>
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
