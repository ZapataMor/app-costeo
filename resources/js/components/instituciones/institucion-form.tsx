import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { etiquetasNivel } from '@/types/instituciones';
import type { Institucion } from '@/types/instituciones';

type FormAction = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

/**
 * Formulario de alta/edición de una institución. Identificación y capacidad
 * TDABC van juntas: la capacidad es lo que vuelve costeable al hospital.
 */
export function InstitucionForm({
    action,
    institucion,
    nivelesComplejidad,
    hrefCancelar,
}: {
    action: FormAction;
    institucion?: Institucion;
    nivelesComplejidad: string[];
    hrefCancelar: string;
}) {
    const [horasDia, setHorasDia] = useState(institucion?.horas_dia ?? 12);
    const [diasMes, setDiasMes] = useState(institucion?.dias_mes ?? 26);
    const [minutosHora, setMinutosHora] = useState(
        institucion?.minutos_efectivos_hora ?? 60,
    );

    const minutosDisponibles = horasDia * diasMes * minutosHora;

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="max-w-3xl space-y-8"
        >
            {({ processing, errors }) => (
                <>
                    <section className="space-y-4">
                        <div>
                            <h3 className="text-base font-semibold">
                                Identificación
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Datos con los que la institución aparece en el
                                aplicativo y en los reportes.
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="nombre">Nombre</Label>
                                <Input
                                    id="nombre"
                                    name="nombre"
                                    required
                                    defaultValue={institucion?.nombre ?? ''}
                                    placeholder="p. ej. Hospital San Rafael de Maicao"
                                />
                                <InputError message={errors.nombre} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="nit">NIT</Label>
                                <Input
                                    id="nit"
                                    name="nit"
                                    required
                                    maxLength={20}
                                    defaultValue={institucion?.nit ?? ''}
                                    placeholder="p. ej. 800100200-1"
                                />
                                <InputError message={errors.nit} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="nivel_complejidad">
                                    Nivel de complejidad
                                </Label>
                                <Select
                                    name="nivel_complejidad"
                                    defaultValue={
                                        institucion?.nivel_complejidad ??
                                        'media'
                                    }
                                    required
                                >
                                    <SelectTrigger id="nivel_complejidad">
                                        <SelectValue placeholder="Seleccione" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {nivelesComplejidad.map((nivel) => (
                                            <SelectItem
                                                key={nivel}
                                                value={nivel}
                                            >
                                                {etiquetasNivel[nivel] ?? nivel}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.nivel_complejidad}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="municipio">
                                    Municipio (opcional)
                                </Label>
                                <Input
                                    id="municipio"
                                    name="municipio"
                                    defaultValue={institucion?.municipio ?? ''}
                                    placeholder="p. ej. Maicao"
                                />
                                <InputError message={errors.municipio} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="departamento">
                                    Departamento
                                </Label>
                                <Input
                                    id="departamento"
                                    name="departamento"
                                    required
                                    defaultValue={
                                        institucion?.departamento ??
                                        'La Guajira'
                                    }
                                />
                                <InputError message={errors.departamento} />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4">
                        <div>
                            <h3 className="text-base font-semibold">
                                Capacidad TDABC
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Minutos disponibles al mes por recurso:
                                horas/día × días/mes × minutos efectivos por
                                hora. El administrador del hospital podrá
                                ajustarla después desde Parámetros.
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="horas_dia">Horas por día</Label>
                                <Input
                                    id="horas_dia"
                                    name="horas_dia"
                                    type="number"
                                    min={1}
                                    max={24}
                                    required
                                    value={horasDia}
                                    onChange={(e) =>
                                        setHorasDia(Number(e.target.value))
                                    }
                                />
                                <InputError message={errors.horas_dia} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="dias_mes">Días por mes</Label>
                                <Input
                                    id="dias_mes"
                                    name="dias_mes"
                                    type="number"
                                    min={1}
                                    max={31}
                                    required
                                    value={diasMes}
                                    onChange={(e) =>
                                        setDiasMes(Number(e.target.value))
                                    }
                                />
                                <InputError message={errors.dias_mes} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="minutos_efectivos_hora">
                                    Minutos efectivos por hora
                                </Label>
                                <Input
                                    id="minutos_efectivos_hora"
                                    name="minutos_efectivos_hora"
                                    type="number"
                                    min={1}
                                    max={60}
                                    required
                                    value={minutosHora}
                                    onChange={(e) =>
                                        setMinutosHora(Number(e.target.value))
                                    }
                                />
                                <InputError
                                    message={errors.minutos_efectivos_hora}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="factor_indirecto">
                                    Factor de costos indirectos
                                </Label>
                                <Input
                                    id="factor_indirecto"
                                    name="factor_indirecto"
                                    type="number"
                                    step="0.0001"
                                    min={0}
                                    max={1}
                                    required
                                    defaultValue={
                                        institucion?.factor_indirecto ?? 0
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Proporción entre 0 y 1 (p. ej. 0.12). En 0
                                    si los indirectos ya van dentro de cada
                                    recurso.
                                </p>
                                <InputError message={errors.factor_indirecto} />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label>Minutos disponibles al mes</Label>
                                <div className="flex h-9 items-center rounded-md border bg-muted/40 px-3 text-sm tabular-nums">
                                    {minutosDisponibles.toLocaleString('es-CO')}{' '}
                                    min
                                </div>
                            </div>
                        </div>
                    </section>

                    <div className="flex items-center gap-3">
                        <Button disabled={processing}>
                            {institucion
                                ? 'Guardar cambios'
                                : 'Crear institución'}
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={hrefCancelar}>Cancelar</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
