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
import type { RecursoHumanoParam } from '@/types/parametros';

type FormAction = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

export function RecursoHumanoForm({
    action,
    recurso,
    roles,
    nivelesConfiabilidad,
    hrefCancelar,
    onSuccess,
    onCancelar,
}: {
    action: FormAction;
    recurso?: RecursoHumanoParam;
    roles: string[];
    nivelesConfiabilidad: string[];
    hrefCancelar?: string;
    onSuccess?: () => void;
    onCancelar?: () => void;
}) {
    const [activo, setActivo] = useState(recurso?.activo ?? true);

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
                            <Label htmlFor="nombre">Nombre completo</Label>
                            <Input
                                id="nombre"
                                name="nombre"
                                defaultValue={recurso?.nombre ?? ''}
                                required
                            />
                            <InputError message={errors.nombre} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="rol">Rol quirúrgico</Label>
                            <Select
                                name="rol"
                                defaultValue={recurso?.rol}
                                required
                            >
                                <SelectTrigger id="rol">
                                    <SelectValue placeholder="Seleccione" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((r) => (
                                        <SelectItem
                                            key={r}
                                            value={r}
                                            className="capitalize"
                                        >
                                            {r}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.rol} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="especialidad">
                                Especialidad (opcional)
                            </Label>
                            <Input
                                id="especialidad"
                                name="especialidad"
                                defaultValue={recurso?.especialidad ?? ''}
                                maxLength={120}
                                placeholder="p. ej. Ginecobstetricia"
                            />
                            <InputError message={errors.especialidad} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="salario_mensual">
                                Salario mensual (COP)
                            </Label>
                            <Input
                                id="salario_mensual"
                                name="salario_mensual"
                                type="number"
                                step="0.01"
                                min="0.01"
                                defaultValue={recurso?.salario_mensual ?? ''}
                                required
                            />
                            <InputError message={errors.salario_mensual} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="prestaciones_mensuales">
                                Prestaciones mensuales (COP)
                            </Label>
                            <Input
                                id="prestaciones_mensuales"
                                name="prestaciones_mensuales"
                                type="number"
                                step="0.01"
                                min="0"
                                defaultValue={
                                    recurso?.prestaciones_mensuales ?? '0'
                                }
                                required
                            />
                            <InputError
                                message={errors.prestaciones_mensuales}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="costos_indirectos_mensuales">
                                Costos indirectos asignados (COP)
                            </Label>
                            <Input
                                id="costos_indirectos_mensuales"
                                name="costos_indirectos_mensuales"
                                type="number"
                                step="0.01"
                                min="0"
                                defaultValue={
                                    recurso?.costos_indirectos_mensuales ?? '0'
                                }
                                required
                            />
                            <InputError
                                message={errors.costos_indirectos_mensuales}
                            />
                        </div>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        El costo por minuto TDABC se deriva de (salario +
                        prestaciones + indirectos) ÷ minutos disponibles del
                        hospital.
                    </p>

                    <CamposTrazabilidad
                        niveles={nivelesConfiabilidad}
                        fuente={recurso?.fuente}
                        nivel={recurso?.nivel_confiabilidad}
                        errors={errors}
                    />

                    <div className="flex items-center gap-2">
                        <input
                            type="hidden"
                            name="activo"
                            value={activo ? '1' : '0'}
                        />
                        <Checkbox
                            id="activo"
                            checked={activo}
                            onCheckedChange={(v) => setActivo(v === true)}
                        />
                        <Label htmlFor="activo">
                            Activo (disponible para asignar a cirugías)
                        </Label>
                    </div>

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
