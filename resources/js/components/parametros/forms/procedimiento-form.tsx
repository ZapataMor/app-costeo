import { Form, Link } from '@inertiajs/react';
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
import type { ProcedimientoParam } from '@/types/parametros';

type FormAction = { action: string; method: 'get' | 'post' | 'put' | 'patch' | 'delete' };

type Fase = {
    campo:
        | 'minutos_prequirurgico'
        | 'duracion_estimada_minutos'
        | 'minutos_recuperacion'
        | 'minutos_recambio';
    etiqueta: string;
    ayuda: string;
    /** Solo el tiempo de sala lo es: es el único que se costea hoy. */
    requerido?: boolean;
};

/** Las cuatro fases del ciclo, en el orden en que las vive el paciente. */
const FASES: readonly Fase[] = [
    {
        campo: 'minutos_prequirurgico',
        etiqueta: 'Preparación pre-quirúrgica',
        ayuda: 'Desde que ingresa hasta que entra a sala: admisión, ayuno, vía, profilaxis.',
    },
    {
        campo: 'duracion_estimada_minutos',
        etiqueta: 'Tiempo de sala',
        ayuda: 'De entrada a salida de quirófano. Es el recurso más caro y el que se costea.',
        requerido: true,
    },
    {
        campo: 'minutos_recuperacion',
        etiqueta: 'Recuperación / URPA',
        ayuda: 'Desde que sale de sala hasta el egreso o el paso a hospitalización.',
    },
    {
        campo: 'minutos_recambio',
        etiqueta: 'Recambio de sala',
        ayuda: 'Limpieza y alistamiento para el siguiente paciente: la sala sigue ocupada.',
    },
];

type CampoFase = Fase['campo'];

/** Formatea minutos como «2 h 15 min», que es como el hospital los piensa. */
function enHoras(minutos: number): string {
    const horas = Math.floor(minutos / 60);
    const resto = minutos % 60;

    if (horas === 0) {
        return `${resto} min`;
    }

    return resto === 0 ? `${horas} h` : `${horas} h ${resto} min`;
}

export function ProcedimientoForm({
    action,
    procedimiento,
    complejidades,
    nivelesConfiabilidad,
    hrefCancelar,
    onSuccess,
    onCancelar,
}: {
    action: FormAction;
    procedimiento?: ProcedimientoParam;
    complejidades: string[];
    nivelesConfiabilidad: string[];
    hrefCancelar?: string;
    onSuccess?: () => void;
    onCancelar?: () => void;
}) {
    // Los tiempos se llevan en estado local —y no como campos no controlados
    // como el resto— solo para poder mostrar el ciclo total mientras se
    // escribe: sin ese total, las cuatro casillas no dicen nada por sí solas.
    const [tiempos, setTiempos] = useState<Record<CampoFase, string>>(() => ({
        minutos_prequirurgico: procedimiento?.minutos_prequirurgico?.toString() ?? '',
        duracion_estimada_minutos: procedimiento?.duracion_estimada_minutos?.toString() ?? '',
        minutos_recuperacion: procedimiento?.minutos_recuperacion?.toString() ?? '',
        minutos_recambio: procedimiento?.minutos_recambio?.toString() ?? '',
    }));

    const total = FASES.reduce(
        (suma, { campo }) => suma + (Number(tiempos[campo]) || 0),
        0,
    );

    const faltanFases = FASES.some(
        ({ campo, requerido }) => ! requerido && tiempos[campo] === '',
    );

    return (
        <Form {...action} options={{ preserveScroll: true }} onSuccess={onSuccess} className="max-w-3xl space-y-6">
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
                            <Label htmlFor="tarifa_soat">Tarifa SOAT de referencia (COP, opcional)</Label>
                            <Input id="tarifa_soat" name="tarifa_soat" type="number" step="0.01" min="0" defaultValue={procedimiento?.tarifa_soat ?? ''} />
                            <InputError message={errors.tarifa_soat} />
                        </div>
                    </div>

                    <fieldset className="grid gap-4 rounded-lg border p-4">
                        <legend className="px-1 text-sm font-medium">Tiempos estándar del ciclo</legend>
                        <p className="-mt-2 text-sm text-muted-foreground">
                            Cuánto dura cada fase en condiciones normales. Se usan para prellenar el registro
                            de cada cirugía y para comparar el estándar contra lo que realmente pasó.
                        </p>

                        {FASES.map(({ campo, etiqueta, ayuda, requerido }) => (
                            <div key={campo} className="grid gap-1.5 sm:grid-cols-[1fr_8rem] sm:items-baseline sm:gap-4">
                                <div>
                                    <Label htmlFor={campo}>
                                        {etiqueta}
                                        {requerido && <span className="text-destructive"> *</span>}
                                    </Label>
                                    <p className="text-xs text-muted-foreground">{ayuda}</p>
                                </div>
                                <div className="grid gap-1">
                                    <Input
                                        id={campo}
                                        name={campo}
                                        type="number"
                                        min={requerido ? 1 : 0}
                                        max={campo === 'minutos_recuperacion' ? 10080 : 1440}
                                        required={requerido}
                                        placeholder="min"
                                        value={tiempos[campo]}
                                        onChange={(e) => setTiempos((t) => ({ ...t, [campo]: e.target.value }))}
                                    />
                                    <InputError message={errors[campo]} />
                                </div>
                            </div>
                        ))}

                        <div className="flex flex-wrap items-baseline justify-between gap-2 border-t pt-3 text-sm">
                            <span className="font-medium">Ciclo total estimado</span>
                            <span className="tabular-nums">
                                {total > 0 ? `${total} min · ${enHoras(total)}` : '—'}
                            </span>
                        </div>
                        {faltanFases && (
                            <p className="text-xs text-muted-foreground">
                                Puedes dejar en blanco las fases que aún no tengas medidas: el procedimiento se
                                guarda igual y el ciclo total solo suma lo que esté diligenciado.
                            </p>
                        )}
                    </fieldset>

                    <CamposTrazabilidad
                        niveles={nivelesConfiabilidad}
                        fuente={procedimiento?.fuente}
                        nivel={procedimiento?.nivel_confiabilidad}
                        errors={errors}
                    />

                    <div className="flex items-center gap-3">
                        <Button disabled={processing}>Guardar</Button>
                        {onCancelar ? (
                            <Button type="button" variant="outline" onClick={onCancelar}>
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
