import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { BuscadorSelect } from '@/components/buscador-select';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ETIQUETA_FASE } from '@/lib/fases';
import { cop } from '@/lib/formato';
import type {
    CatalogoEquipoMedico,
    CatalogoInsumo,
    CatalogoRecurso,
    FaseCiclo,
    PlantillaProcedimiento,
} from '@/types/cirugias';

export type CatalogosPlantilla = {
    insumos: CatalogoInsumo[];
    recursos: CatalogoRecurso[];
    equiposMedicos: CatalogoEquipoMedico[];
    rolesQuirurgicos: string[];
    fases: FaseCiclo[];
};

/**
 * Editor de la plantilla del procedimiento: lo que se usa siempre en él.
 *
 * Cada línea lleva su fase porque la gasa de la preparación y la de la
 * cirugía no cuestan lo mismo ni se imputan al mismo lado; y lleva la marca
 * «opcional» para el material que solo aparece en algunos casos, que se
 * sugiere sin ensuciar el registro típico.
 */
export function PlantillaProcedimientoForm({
    procedimientoId,
    plantilla,
    catalogos,
    hrefCancelar,
}: {
    procedimientoId: number;
    plantilla: PlantillaProcedimiento;
    catalogos: CatalogosPlantilla;
    hrefCancelar: string;
}) {
    const { insumos, recursos, equiposMedicos, rolesQuirurgicos, fases } =
        catalogos;

    const { data, setData, put, processing, errors } =
        useForm<PlantillaProcedimiento>(plantilla);

    const error = (clave: string): string | undefined =>
        (errors as Record<string, string>)[clave];

    const opcionesInsumos = useMemo(
        () =>
            insumos.map((i) => ({
                valor: String(i.id),
                etiqueta: i.nombre,
                detalle: `${i.codigo} · ${i.unidad}`,
                busqueda: i.codigo,
            })),
        [insumos],
    );

    const opcionesRecursos = useMemo(
        () => [
            // La primera opción es la ausencia de persona fija: es el caso
            // normal, y decirlo evita que se fije a alguien por inercia.
            {
                valor: '',
                etiqueta: 'Sin persona fija (la define el turno)',
            },
            ...recursos.map((r) => ({
                valor: String(r.id),
                etiqueta: r.nombre,
                detalle: r.rol,
                busqueda: r.especialidad ?? '',
            })),
        ],
        [recursos],
    );

    const opcionesEquipos = useMemo(
        () =>
            equiposMedicos.map((e) => ({
                valor: String(e.id),
                etiqueta: e.nombre,
            })),
        [equiposMedicos],
    );

    /**
     * Costo de los insumos de la plantilla a precios de hoy. No es el costo
     * del procedimiento —falta personal, sala y equipos— pero es el número
     * que permite discutir la plantilla: si sube al agregar una línea, el
     * protocolo se encareció.
     */
    const costoInsumos = useMemo(
        () =>
            data.insumos.reduce((suma, fila) => {
                if (fila.opcional) {
                    return suma;
                }

                const insumo = insumos.find(
                    (i) => String(i.id) === fila.insumo_id,
                );

                return (
                    suma +
                    (Number(fila.cantidad) || 0) *
                        Number(insumo?.costo_unitario ?? 0)
                );
            }, 0),
        [data.insumos, insumos],
    );

    const selectorFase = (
        valor: FaseCiclo,
        onCambio: (fase: FaseCiclo) => void,
    ) => (
        <Select value={valor} onValueChange={(v) => onCambio(v as FaseCiclo)}>
            <SelectTrigger className="w-44">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {fases.map((fase) => (
                    <SelectItem key={fase} value={fase}>
                        {ETIQUETA_FASE[fase]}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    const casillaOpcional = (
        marcada: boolean,
        onCambio: (valor: boolean) => void,
    ) => (
        <label className="flex items-center gap-1.5 text-sm text-muted-foreground">
            <Checkbox
                checked={marcada}
                onCheckedChange={(v) => onCambio(v === true)}
            />
            Opcional
        </label>
    );

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        put(`/parametros/procedimientos/${procedimientoId}/plantilla`, {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={enviar} className="max-w-5xl space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Insumos estándar
                    </CardTitle>
                    <CardDescription>
                        Lo que este procedimiento consume siempre, con la
                        cantidad habitual y la fase en la que se consume.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {data.insumos.map((fila, i) => (
                        <div
                            key={i}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <div className="min-w-64 flex-1">
                                <BuscadorSelect
                                    opciones={opcionesInsumos}
                                    valor={fila.insumo_id}
                                    onCambio={(v) =>
                                        setData(
                                            'insumos',
                                            data.insumos.map((f, j) =>
                                                j === i
                                                    ? { ...f, insumo_id: v }
                                                    : f,
                                            ),
                                        )
                                    }
                                    placeholder="Seleccione insumo"
                                    placeholderBusqueda="Buscar por nombre o código…"
                                />
                                <InputError
                                    message={error(`insumos.${i}.insumo_id`)}
                                />
                            </div>
                            {selectorFase(fila.fase, (fase) =>
                                setData(
                                    'insumos',
                                    data.insumos.map((f, j) =>
                                        j === i ? { ...f, fase } : f,
                                    ),
                                ),
                            )}
                            <div className="w-28">
                                <Input
                                    type="number"
                                    min={0.01}
                                    step="0.01"
                                    placeholder="Cantidad"
                                    value={fila.cantidad}
                                    onChange={(e) =>
                                        setData(
                                            'insumos',
                                            data.insumos.map((f, j) =>
                                                j === i
                                                    ? {
                                                          ...f,
                                                          cantidad:
                                                              e.target.value,
                                                      }
                                                    : f,
                                            ),
                                        )
                                    }
                                />
                                <InputError
                                    message={error(`insumos.${i}.cantidad`)}
                                />
                            </div>
                            {casillaOpcional(fila.opcional, (opcional) =>
                                setData(
                                    'insumos',
                                    data.insumos.map((f, j) =>
                                        j === i ? { ...f, opcional } : f,
                                    ),
                                ),
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar insumo"
                                onClick={() =>
                                    setData(
                                        'insumos',
                                        data.insumos.filter((_, j) => j !== i),
                                    )
                                }
                            >
                                <Trash2 className="size-4 text-destructive" />
                            </Button>
                            <InputError message={error(`insumos.${i}.fase`)} />
                        </div>
                    ))}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setData('insumos', [
                                    ...data.insumos,
                                    {
                                        insumo_id: '',
                                        fase: 'quirurgica',
                                        cantidad: '1',
                                        opcional: false,
                                    },
                                ])
                            }
                        >
                            <Plus className="size-4" /> Agregar insumo
                        </Button>
                        {data.insumos.length > 0 && (
                            <p className="text-sm text-muted-foreground">
                                Insumos fijos a precios de hoy:{' '}
                                <span className="font-medium tabular-nums text-foreground">
                                    {cop(costoInsumos)}
                                </span>
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Personal estándar
                    </CardTitle>
                    <CardDescription>
                        Qué roles hacen falta y cuántas personas de cada uno. La
                        persona concreta suele definirla el turno: fíjela solo
                        donde de verdad siempre es la misma.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {data.personal.map((fila, i) => (
                        <div
                            key={i}
                            className="flex flex-wrap items-end gap-2 rounded-lg border p-3"
                        >
                            <div className="grid gap-1">
                                <Label className="text-xs text-muted-foreground">
                                    Rol
                                </Label>
                                <Select
                                    value={fila.rol}
                                    onValueChange={(rol) =>
                                        setData(
                                            'personal',
                                            data.personal.map((f, j) =>
                                                j === i ? { ...f, rol } : f,
                                            ),
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-44">
                                        <SelectValue placeholder="Rol" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {rolesQuirurgicos.map((rol) => (
                                            <SelectItem
                                                key={rol}
                                                value={rol}
                                                className="capitalize"
                                            >
                                                {rol}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={error(`personal.${i}.rol`)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs text-muted-foreground">
                                    Fase
                                </Label>
                                {selectorFase(fila.fase, (fase) =>
                                    setData(
                                        'personal',
                                        data.personal.map((f, j) =>
                                            j === i ? { ...f, fase } : f,
                                        ),
                                    ),
                                )}
                                <InputError
                                    message={error(`personal.${i}.fase`)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`personal_cantidad_${i}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Personas
                                </Label>
                                <Input
                                    id={`personal_cantidad_${i}`}
                                    type="number"
                                    min={1}
                                    max={20}
                                    className="w-24"
                                    value={fila.cantidad}
                                    onChange={(e) =>
                                        setData(
                                            'personal',
                                            data.personal.map((f, j) =>
                                                j === i
                                                    ? {
                                                          ...f,
                                                          cantidad: Number(
                                                              e.target.value,
                                                          ),
                                                      }
                                                    : f,
                                            ),
                                        )
                                    }
                                />
                                <InputError
                                    message={error(`personal.${i}.cantidad`)}
                                />
                            </div>
                            <div className="grid min-w-56 flex-1 gap-1">
                                <Label className="text-xs text-muted-foreground">
                                    Persona fija (opcional)
                                </Label>
                                <BuscadorSelect
                                    opciones={opcionesRecursos}
                                    valor={fila.recurso_humano_id}
                                    onCambio={(v) =>
                                        setData(
                                            'personal',
                                            data.personal.map((f, j) =>
                                                j === i
                                                    ? {
                                                          ...f,
                                                          recurso_humano_id: v,
                                                      }
                                                    : f,
                                            ),
                                        )
                                    }
                                    placeholder="Sin persona fija"
                                    placeholderBusqueda="Buscar por nombre o especialidad…"
                                />
                                <InputError
                                    message={error(
                                        `personal.${i}.recurso_humano_id`,
                                    )}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`personal_minutos_${i}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Minutos
                                </Label>
                                <Input
                                    id={`personal_minutos_${i}`}
                                    type="number"
                                    min={1}
                                    max={1440}
                                    className="w-28"
                                    placeholder="Toda la fase"
                                    value={fila.minutos}
                                    onChange={(e) =>
                                        setData(
                                            'personal',
                                            data.personal.map((f, j) =>
                                                j === i
                                                    ? {
                                                          ...f,
                                                          minutos:
                                                              e.target.value,
                                                      }
                                                    : f,
                                            ),
                                        )
                                    }
                                />
                                <InputError
                                    message={error(`personal.${i}.minutos`)}
                                />
                            </div>
                            {casillaOpcional(fila.opcional, (opcional) =>
                                setData(
                                    'personal',
                                    data.personal.map((f, j) =>
                                        j === i ? { ...f, opcional } : f,
                                    ),
                                ),
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar rol"
                                onClick={() =>
                                    setData(
                                        'personal',
                                        data.personal.filter((_, j) => j !== i),
                                    )
                                }
                            >
                                <Trash2 className="size-4 text-destructive" />
                            </Button>
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setData('personal', [
                                ...data.personal,
                                {
                                    rol: rolesQuirurgicos[0] ?? '',
                                    fase: 'quirurgica',
                                    cantidad: 1,
                                    recurso_humano_id: '',
                                    minutos: '',
                                    opcional: false,
                                },
                            ])
                        }
                    >
                        <Plus className="size-4" /> Agregar rol
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Equipos médicos estándar
                    </CardTitle>
                    <CardDescription>
                        Deje los minutos en blanco si el equipo se usa todo el
                        tiempo de sala, que es lo habitual.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {data.equipos.map((fila, i) => (
                        <div
                            key={i}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <div className="min-w-64 flex-1">
                                <BuscadorSelect
                                    opciones={opcionesEquipos}
                                    valor={fila.equipo_medico_id}
                                    onCambio={(v) =>
                                        setData(
                                            'equipos',
                                            data.equipos.map((f, j) =>
                                                j === i
                                                    ? {
                                                          ...f,
                                                          equipo_medico_id: v,
                                                      }
                                                    : f,
                                            ),
                                        )
                                    }
                                    placeholder="Seleccione equipo"
                                    placeholderBusqueda="Buscar equipo…"
                                />
                                <InputError
                                    message={error(
                                        `equipos.${i}.equipo_medico_id`,
                                    )}
                                />
                            </div>
                            <div className="w-36">
                                <Input
                                    type="number"
                                    min={1}
                                    max={1440}
                                    placeholder="Todo el tiempo"
                                    value={fila.minutos_uso}
                                    onChange={(e) =>
                                        setData(
                                            'equipos',
                                            data.equipos.map((f, j) =>
                                                j === i
                                                    ? {
                                                          ...f,
                                                          minutos_uso:
                                                              e.target.value,
                                                      }
                                                    : f,
                                            ),
                                        )
                                    }
                                />
                                <InputError
                                    message={error(`equipos.${i}.minutos_uso`)}
                                />
                            </div>
                            {casillaOpcional(fila.opcional, (opcional) =>
                                setData(
                                    'equipos',
                                    data.equipos.map((f, j) =>
                                        j === i ? { ...f, opcional } : f,
                                    ),
                                ),
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar equipo"
                                onClick={() =>
                                    setData(
                                        'equipos',
                                        data.equipos.filter((_, j) => j !== i),
                                    )
                                }
                            >
                                <Trash2 className="size-4 text-destructive" />
                            </Button>
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setData('equipos', [
                                ...data.equipos,
                                {
                                    equipo_medico_id: '',
                                    minutos_uso: '',
                                    opcional: false,
                                },
                            ])
                        }
                    >
                        <Plus className="size-4" /> Agregar equipo
                    </Button>
                </CardContent>
            </Card>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    Guardar plantilla
                </Button>
                <Button asChild variant="outline">
                    <Link href={hrefCancelar}>Volver</Link>
                </Button>
            </div>
        </form>
    );
}
