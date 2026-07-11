import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { CamposTrazabilidad } from '@/components/parametros/campos-trazabilidad';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EquipoMedicoParam } from '@/types/parametros';

type FormAction = { action: string; method: 'get' | 'post' | 'put' | 'patch' | 'delete' };

export function EquipoMedicoForm({
    action,
    equipo,
    nivelesConfiabilidad,
    hrefCancelar,
}: {
    action: FormAction;
    equipo?: EquipoMedicoParam;
    nivelesConfiabilidad: string[];
    hrefCancelar: string;
}) {
    const [activo, setActivo] = useState(equipo?.activo ?? true);

    return (
        <Form {...action} options={{ preserveScroll: true }} className="max-w-3xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="nombre">Nombre</Label>
                            <Input id="nombre" name="nombre" defaultValue={equipo?.nombre ?? ''} required placeholder="p. ej. Torre de laparoscopia" />
                            <InputError message={errors.nombre} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="codigo">Código (opcional)</Label>
                            <Input id="codigo" name="codigo" defaultValue={equipo?.codigo ?? ''} maxLength={30} placeholder="p. ej. EQ-001" />
                            <InputError message={errors.codigo} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="valor_adquisicion">Valor de adquisición (COP, opcional)</Label>
                            <Input id="valor_adquisicion" name="valor_adquisicion" type="number" step="0.01" min="0" defaultValue={equipo?.valor_adquisicion ?? ''} />
                            <InputError message={errors.valor_adquisicion} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="vida_util_anios">Vida útil (años, opcional)</Label>
                            <Input id="vida_util_anios" name="vida_util_anios" type="number" min="1" max="50" defaultValue={equipo?.vida_util_anios ?? ''} />
                            <InputError message={errors.vida_util_anios} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="costo_hora">Costo por hora de uso (COP)</Label>
                            <Input id="costo_hora" name="costo_hora" type="number" step="0.01" min="0.01" defaultValue={equipo?.costo_hora ?? ''} required />
                            <InputError message={errors.costo_hora} />
                        </div>
                    </div>

                    <CamposTrazabilidad
                        niveles={nivelesConfiabilidad}
                        fuente={equipo?.fuente}
                        nivel={equipo?.nivel_confiabilidad}
                        errors={errors}
                    />

                    <div className="flex items-center gap-2">
                        <input type="hidden" name="activo" value={activo ? '1' : '0'} />
                        <Checkbox id="activo" checked={activo} onCheckedChange={(v) => setActivo(v === true)} />
                        <Label htmlFor="activo">Activo (disponible para asignar a cirugías)</Label>
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
