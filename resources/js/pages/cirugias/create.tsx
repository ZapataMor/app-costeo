import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2, TriangleAlert } from 'lucide-react';
import CirugiaController from '@/actions/App/Http/Controllers/Cirugias/CirugiaController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import type {
    CatalogoEquipoMedico,
    CatalogoInsumo,
    CatalogoPaciente,
    CatalogoProcedimiento,
    CatalogoRecurso,
    CatalogoSala,
} from '@/types/cirugias';

type ProcedimientoFila = { id: string; es_principal: boolean };
type MiembroFila = {
    recurso_humano_id: string;
    rol: string;
    minutos_participacion: string;
};
type ConsumoFila = { insumo_id: string; cantidad: string };
type EquipoMedicoFila = { id: string; minutos_uso: string };

type DatosCirugia = {
    paciente_id: string;
    sala_operatoria_id: string;
    fecha: string;
    hora_inicio: string;
    hora_fin: string;
    tipo: string;
    estado: string;
    diagnostico_cie10: string;
    observaciones: string;
    procedimientos: ProcedimientoFila[];
    equipo: MiembroFila[];
    consumos: ConsumoFila[];
    equipos_medicos: EquipoMedicoFila[];
};

export default function CirugiasCreate({
    pacientes,
    salas,
    procedimientos,
    recursos,
    insumos,
    equiposMedicos,
    tipos,
    estados,
    rolesQuirurgicos,
}: {
    pacientes: CatalogoPaciente[];
    salas: CatalogoSala[];
    procedimientos: CatalogoProcedimiento[];
    recursos: CatalogoRecurso[];
    insumos: CatalogoInsumo[];
    equiposMedicos: CatalogoEquipoMedico[];
    tipos: string[];
    estados: string[];
    rolesQuirurgicos: string[];
}) {
    const { data, setData, post, processing, errors } = useForm<DatosCirugia>({
        paciente_id: '',
        sala_operatoria_id: '',
        fecha: '',
        hora_inicio: '',
        hora_fin: '',
        tipo: 'programada',
        estado: 'en_proceso',
        diagnostico_cie10: '',
        observaciones: '',
        procedimientos: [{ id: '', es_principal: true }],
        equipo: [],
        consumos: [],
        equipos_medicos: [],
    });

    const error = (clave: string): string | undefined =>
        (errors as Record<string, string>)[clave];

    const sinHoraFin = data.hora_fin === '';
    const sinEstadoRealizada = data.estado !== 'realizada';
    const noContabilizable = sinHoraFin || sinEstadoRealizada;

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        post(CirugiaController.store.url(), { preserveScroll: true });
    };

    const actualizarFila = <
        K extends 'procedimientos' | 'equipo' | 'consumos' | 'equipos_medicos',
    >(
        campo: K,
        indice: number,
        cambios: Partial<DatosCirugia[K][number]>,
    ) => {
        const filas = data[campo].map((fila, i) =>
            i === indice ? { ...fila, ...cambios } : fila,
        );
        setData(campo, filas as never);
    };

    const quitarFila = (
        campo: 'procedimientos' | 'equipo' | 'consumos' | 'equipos_medicos',
        indice: number,
    ) => {
        setData(campo, data[campo].filter((_, i) => i !== indice) as never);
    };

    return (
        <>
            <Head title="Registrar procedimiento" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Registrar procedimiento"
                    description="El procedimiento consume los parámetros de Capa 1: procedimientos, personal, insumos, equipos y sala."
                />

                <form onSubmit={enviar} className="max-w-4xl space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Datos generales
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Paciente</Label>
                                <Select
                                    value={data.paciente_id}
                                    onValueChange={(v) =>
                                        setData('paciente_id', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccione paciente" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {pacientes.map((p) => (
                                            <SelectItem
                                                key={p.id}
                                                value={String(p.id)}
                                            >
                                                {p.apellidos}, {p.nombres}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={error('paciente_id')} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Sala operatoria (opcional)</Label>
                                <Select
                                    value={data.sala_operatoria_id}
                                    onValueChange={(v) =>
                                        setData('sala_operatoria_id', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sin sala" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {salas.map((s) => (
                                            <SelectItem
                                                key={s.id}
                                                value={String(s.id)}
                                            >
                                                {s.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={error('sala_operatoria_id')}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fecha">Fecha</Label>
                                <Input
                                    id="fecha"
                                    type="date"
                                    value={data.fecha}
                                    onChange={(e) =>
                                        setData('fecha', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={error('fecha')} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Tipo / Estado</Label>
                                <div className="flex gap-2">
                                    <Select
                                        value={data.tipo}
                                        onValueChange={(v) =>
                                            setData('tipo', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {tipos.map((t) => (
                                                <SelectItem
                                                    key={t}
                                                    value={t}
                                                    className="capitalize"
                                                >
                                                    {t}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        value={data.estado}
                                        onValueChange={(v) =>
                                            setData('estado', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {estados.map((est) => (
                                                <SelectItem
                                                    key={est}
                                                    value={est}
                                                    className="capitalize"
                                                >
                                                    {est.replace('_', ' ')}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <InputError
                                    message={error('tipo') ?? error('estado')}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="hora_inicio">
                                    Hora de inicio
                                </Label>
                                <Input
                                    id="hora_inicio"
                                    type="datetime-local"
                                    value={data.hora_inicio}
                                    onChange={(e) =>
                                        setData('hora_inicio', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={error('hora_inicio')} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="hora_fin">
                                    Hora de finalización
                                </Label>
                                <Input
                                    id="hora_fin"
                                    type="datetime-local"
                                    value={data.hora_fin}
                                    onChange={(e) =>
                                        setData('hora_fin', e.target.value)
                                    }
                                />
                                <InputError message={error('hora_fin')} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="diagnostico_cie10">
                                    Diagnóstico CIE-10 (opcional)
                                </Label>
                                <Input
                                    id="diagnostico_cie10"
                                    value={data.diagnostico_cie10}
                                    onChange={(e) =>
                                        setData(
                                            'diagnostico_cie10',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="p. ej. O82 o K35.8"
                                    maxLength={8}
                                />
                                <InputError
                                    message={error('diagnostico_cie10')}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="observaciones">
                                    Observaciones (opcional)
                                </Label>
                                <Input
                                    id="observaciones"
                                    value={data.observaciones}
                                    onChange={(e) =>
                                        setData('observaciones', e.target.value)
                                    }
                                />
                                <InputError message={error('observaciones')} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Procedimientos
                            </CardTitle>
                            <CardDescription>
                                Al menos uno; marca cuál es el principal.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <InputError message={error('procedimientos')} />
                            {data.procedimientos.map((fila, i) => (
                                <div
                                    key={i}
                                    className="flex flex-wrap items-center gap-2"
                                >
                                    <div className="min-w-64 flex-1">
                                        <Select
                                            value={fila.id}
                                            onValueChange={(v) =>
                                                actualizarFila(
                                                    'procedimientos',
                                                    i,
                                                    { id: v },
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Seleccione procedimiento" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {procedimientos.map((p) => (
                                                    <SelectItem
                                                        key={p.id}
                                                        value={String(p.id)}
                                                    >
                                                        {p.codigo_cups} ·{' '}
                                                        {p.nombre} (
                                                        {
                                                            p.duracion_estimada_minutos
                                                        }{' '}
                                                        min est.)
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={error(
                                                `procedimientos.${i}.id`,
                                            )}
                                        />
                                    </div>
                                    <label className="flex items-center gap-1.5 text-sm">
                                        <Checkbox
                                            checked={fila.es_principal}
                                            onCheckedChange={(v) =>
                                                setData(
                                                    'procedimientos',
                                                    data.procedimientos.map(
                                                        (p, j) => ({
                                                            ...p,
                                                            es_principal:
                                                                j === i &&
                                                                v === true,
                                                        }),
                                                    ),
                                                )
                                            }
                                        />
                                        Principal
                                    </label>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Quitar"
                                        onClick={() =>
                                            quitarFila('procedimientos', i)
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
                                    setData('procedimientos', [
                                        ...data.procedimientos,
                                        { id: '', es_principal: false },
                                    ])
                                }
                            >
                                <Plus className="size-4" /> Agregar
                                procedimiento
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Equipo quirúrgico
                            </CardTitle>
                            <CardDescription>
                                Personal que participó y sus minutos (base del
                                costo TDABC de talento humano).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {data.equipo.map((fila, i) => (
                                <div
                                    key={i}
                                    className="flex flex-wrap items-center gap-2"
                                >
                                    <div className="min-w-56 flex-1">
                                        <Select
                                            value={fila.recurso_humano_id}
                                            onValueChange={(v) => {
                                                const recurso = recursos.find(
                                                    (r) => String(r.id) === v,
                                                );
                                                actualizarFila('equipo', i, {
                                                    recurso_humano_id: v,
                                                    rol:
                                                        recurso?.rol ??
                                                        fila.rol,
                                                });
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Seleccione persona" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {recursos.map((r) => (
                                                    <SelectItem
                                                        key={r.id}
                                                        value={String(r.id)}
                                                    >
                                                        {r.nombre} ({r.rol})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={error(
                                                `equipo.${i}.recurso_humano_id`,
                                            )}
                                        />
                                    </div>
                                    <div className="w-44">
                                        <Select
                                            value={fila.rol}
                                            onValueChange={(v) =>
                                                actualizarFila('equipo', i, {
                                                    rol: v,
                                                })
                                            }
                                        >
                                            <SelectTrigger>
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
                                            message={error(`equipo.${i}.rol`)}
                                        />
                                    </div>
                                    <div className="w-32">
                                        <Input
                                            type="number"
                                            min={1}
                                            max={1440}
                                            placeholder="Minutos"
                                            value={fila.minutos_participacion}
                                            onChange={(e) =>
                                                actualizarFila('equipo', i, {
                                                    minutos_participacion:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={error(
                                                `equipo.${i}.minutos_participacion`,
                                            )}
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Quitar"
                                        onClick={() => quitarFila('equipo', i)}
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
                                    setData('equipo', [
                                        ...data.equipo,
                                        {
                                            recurso_humano_id: '',
                                            rol: '',
                                            minutos_participacion: '',
                                        },
                                    ])
                                }
                            >
                                <Plus className="size-4" /> Agregar persona
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Consumo de insumos
                            </CardTitle>
                            <CardDescription>
                                El precio se toma del insumo al momento de
                                registrar (snapshot).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {data.consumos.map((fila, i) => (
                                <div
                                    key={i}
                                    className="flex flex-wrap items-center gap-2"
                                >
                                    <div className="min-w-64 flex-1">
                                        <Select
                                            value={fila.insumo_id}
                                            onValueChange={(v) =>
                                                actualizarFila('consumos', i, {
                                                    insumo_id: v,
                                                })
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Seleccione insumo" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {insumos.map((ins) => (
                                                    <SelectItem
                                                        key={ins.id}
                                                        value={String(ins.id)}
                                                    >
                                                        {ins.codigo} ·{' '}
                                                        {ins.nombre} (
                                                        {ins.unidad})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={error(
                                                `consumos.${i}.insumo_id`,
                                            )}
                                        />
                                    </div>
                                    <div className="w-32">
                                        <Input
                                            type="number"
                                            min={0.01}
                                            step="0.01"
                                            placeholder="Cantidad"
                                            value={fila.cantidad}
                                            onChange={(e) =>
                                                actualizarFila('consumos', i, {
                                                    cantidad: e.target.value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={error(
                                                `consumos.${i}.cantidad`,
                                            )}
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Quitar"
                                        onClick={() =>
                                            quitarFila('consumos', i)
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
                                    setData('consumos', [
                                        ...data.consumos,
                                        { insumo_id: '', cantidad: '' },
                                    ])
                                }
                            >
                                <Plus className="size-4" /> Agregar insumo
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Equipos médicos
                            </CardTitle>
                            <CardDescription>
                                Equipos usados y sus minutos de uso.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {data.equipos_medicos.map((fila, i) => (
                                <div
                                    key={i}
                                    className="flex flex-wrap items-center gap-2"
                                >
                                    <div className="min-w-64 flex-1">
                                        <Select
                                            value={fila.id}
                                            onValueChange={(v) =>
                                                actualizarFila(
                                                    'equipos_medicos',
                                                    i,
                                                    { id: v },
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Seleccione equipo" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {equiposMedicos.map((eq) => (
                                                    <SelectItem
                                                        key={eq.id}
                                                        value={String(eq.id)}
                                                    >
                                                        {eq.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={error(
                                                `equipos_medicos.${i}.id`,
                                            )}
                                        />
                                    </div>
                                    <div className="w-32">
                                        <Input
                                            type="number"
                                            min={1}
                                            max={1440}
                                            placeholder="Minutos"
                                            value={fila.minutos_uso}
                                            onChange={(e) =>
                                                actualizarFila(
                                                    'equipos_medicos',
                                                    i,
                                                    {
                                                        minutos_uso:
                                                            e.target.value,
                                                    },
                                                )
                                            }
                                        />
                                        <InputError
                                            message={error(
                                                `equipos_medicos.${i}.minutos_uso`,
                                            )}
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Quitar"
                                        onClick={() =>
                                            quitarFila('equipos_medicos', i)
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
                                    setData('equipos_medicos', [
                                        ...data.equipos_medicos,
                                        { id: '', minutos_uso: '' },
                                    ])
                                }
                            >
                                <Plus className="size-4" /> Agregar equipo
                            </Button>
                        </CardContent>
                    </Card>

                    {noContabilizable && (
                        <Alert className="border-amber-300/70 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            <TriangleAlert className="size-4" />
                            <AlertTitle>
                                Este procedimiento no se contabilizará en los
                                indicadores
                            </AlertTitle>
                            <AlertDescription className="text-amber-800 dark:text-amber-300/90">
                                {sinHoraFin && (
                                    <p>
                                        No tiene hora de finalización: sin ella
                                        no hay duración real que costear.
                                    </p>
                                )}
                                {sinEstadoRealizada && (
                                    <p>
                                        Su estado es «
                                        {data.estado.replace('_', ' ')}»: solo
                                        los procedimientos realizados se costean
                                        y entran a los indicadores.
                                    </p>
                                )}
                                <p>
                                    Puedes guardarla así y completarla después;
                                    quedará marcada como «No contabilizada».
                                </p>
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            Registrar procedimiento
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={CirugiaController.index.url()}>
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CirugiasCreate.layout = {
    breadcrumbs: [
        { title: 'Procedimientos', href: '/cirugias' },
        { title: 'Registrar', href: '/cirugias/create' },
    ],
};
