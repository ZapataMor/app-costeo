import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2, TriangleAlert } from 'lucide-react';
import { useMemo } from 'react';
import { BuscadorSelect } from '@/components/buscador-select';
import {
    calcularEstimacion,
    EstimacionCosto,
} from '@/components/cirugias/estimacion-costo';
import { NuevoPacienteModal } from '@/components/cirugias/nuevo-paciente-modal';
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
    DatosCirugia,
    ParametrosTdabc,
} from '@/types/cirugias';

export type CatalogosCirugia = {
    pacientes: CatalogoPaciente[];
    salas: CatalogoSala[];
    procedimientos: CatalogoProcedimiento[];
    recursos: CatalogoRecurso[];
    insumos: CatalogoInsumo[];
    equiposMedicos: CatalogoEquipoMedico[];
    tipos: string[];
    estados: string[];
    rolesQuirurgicos: string[];
    regimenes: string[];
    parametrosTdabc: ParametrosTdabc;
};

const vacio: DatosCirugia = {
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
};

/**
 * Formulario de captura del procedimiento, compartido por el registro y la
 * corrección: una sola definición de los campos evita que editar acepte
 * algo distinto de lo que acepta registrar.
 */
export function FormularioCirugia({
    catalogos,
    valoresIniciales,
    urlEnvio,
    metodo,
    textoEnviar,
    hrefCancelar,
}: {
    catalogos: CatalogosCirugia;
    valoresIniciales?: DatosCirugia;
    urlEnvio: string;
    metodo: 'post' | 'put';
    textoEnviar: string;
    hrefCancelar: string;
}) {
    const {
        pacientes,
        salas,
        procedimientos,
        recursos,
        insumos,
        equiposMedicos,
        tipos,
        estados,
        rolesQuirurgicos,
        regimenes,
        parametrosTdabc,
    } = catalogos;

    const opcionesPacientes = useMemo(
        () =>
            pacientes.map((p) => ({
                valor: String(p.id),
                etiqueta: `${p.apellidos}, ${p.nombres}`,
                detalle: p.tipo_documento,
            })),
        [pacientes],
    );

    const opcionesSalas = useMemo(
        () => salas.map((s) => ({ valor: String(s.id), etiqueta: s.nombre })),
        [salas],
    );

    const opcionesProcedimientos = useMemo(
        () =>
            procedimientos.map((p) => ({
                valor: String(p.id),
                etiqueta: p.nombre,
                detalle: `${p.codigo_cups} · ${p.duracion_estimada_minutos} min est.`,
                busqueda: p.codigo_cups,
            })),
        [procedimientos],
    );

    const opcionesRecursos = useMemo(
        () =>
            recursos.map((r) => ({
                valor: String(r.id),
                etiqueta: r.nombre,
                detalle: r.rol,
                busqueda: r.especialidad ?? '',
            })),
        [recursos],
    );

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

    const opcionesEquipos = useMemo(
        () =>
            equiposMedicos.map((e) => ({
                valor: String(e.id),
                etiqueta: e.nombre,
            })),
        [equiposMedicos],
    );

    const { data, setData, submit, processing, errors } = useForm<DatosCirugia>(
        valoresIniciales ?? vacio,
    );

    const error = (clave: string): string | undefined =>
        (errors as Record<string, string>)[clave];

    const sinHoraFin = data.hora_fin === '';
    const sinEstadoRealizada = data.estado !== 'realizada';
    const noContabilizable = sinHoraFin || sinEstadoRealizada;

    /** Duración real capturada, base para sugerir los minutos de cada recurso. */
    const duracionMinutos = (): number | null => {
        if (data.hora_inicio === '' || data.hora_fin === '') {
            return null;
        }

        const minutos = Math.round(
            (new Date(data.hora_fin).getTime() -
                new Date(data.hora_inicio).getTime()) /
                60000,
        );

        return minutos > 0 ? minutos : null;
    };

    const duracion = duracionMinutos();

    const estimacion = useMemo(
        () =>
            calcularEstimacion({
                datos: data,
                salas,
                recursos,
                insumos,
                equiposMedicos,
                parametros: parametrosTdabc,
                duracionMinutos: duracion,
            }),
        [
            data,
            salas,
            recursos,
            insumos,
            equiposMedicos,
            parametrosTdabc,
            duracion,
        ],
    );

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        submit(metodo, urlEnvio, { preserveScroll: true });
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
        <form onSubmit={enviar} className="max-w-4xl space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Datos generales</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <div className="flex items-center justify-between gap-2">
                            <Label>Paciente</Label>
                            <NuevoPacienteModal
                                regimenes={regimenes}
                                onCreado={(id) => setData('paciente_id', id)}
                            />
                        </div>
                        <BuscadorSelect
                            opciones={opcionesPacientes}
                            valor={data.paciente_id}
                            onCambio={(v) => setData('paciente_id', v)}
                            placeholder="Seleccione paciente"
                            placeholderBusqueda="Buscar por nombre o apellido…"
                            sinResultados="Ningún paciente coincide. Use «Nuevo paciente»."
                        />
                        <InputError message={error('paciente_id')} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Sala operatoria (opcional)</Label>
                        <BuscadorSelect
                            opciones={opcionesSalas}
                            valor={data.sala_operatoria_id}
                            onCambio={(v) => setData('sala_operatoria_id', v)}
                            placeholder="Sin sala"
                            placeholderBusqueda="Buscar sala…"
                        />
                        <InputError message={error('sala_operatoria_id')} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="fecha">Fecha</Label>
                        <Input
                            id="fecha"
                            type="date"
                            value={data.fecha}
                            onChange={(e) => setData('fecha', e.target.value)}
                            required
                        />
                        <InputError message={error('fecha')} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Tipo / Estado</Label>
                        <div className="flex gap-2">
                            <Select
                                value={data.tipo}
                                onValueChange={(v) => setData('tipo', v)}
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
                                onValueChange={(v) => setData('estado', v)}
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
                        <Label htmlFor="hora_inicio">Hora de inicio</Label>
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
                        <Label htmlFor="hora_fin">Hora de finalización</Label>
                        <Input
                            id="hora_fin"
                            type="datetime-local"
                            value={data.hora_fin}
                            onChange={(e) => setData('hora_fin', e.target.value)}
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
                                setData('diagnostico_cie10', e.target.value)
                            }
                            placeholder="p. ej. O82 o K35.8"
                            maxLength={8}
                        />
                        <InputError message={error('diagnostico_cie10')} />
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
                    <CardTitle className="text-base">Procedimientos</CardTitle>
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
                                <BuscadorSelect
                                    opciones={opcionesProcedimientos}
                                    valor={fila.id}
                                    onCambio={(v) =>
                                        actualizarFila('procedimientos', i, {
                                            id: v,
                                        })
                                    }
                                    placeholder="Seleccione procedimiento"
                                    placeholderBusqueda="Buscar por nombre o código CUPS…"
                                />
                                <InputError
                                    message={error(`procedimientos.${i}.id`)}
                                />
                            </div>
                            <label className="flex items-center gap-1.5 text-sm">
                                <Checkbox
                                    checked={fila.es_principal}
                                    onCheckedChange={(v) =>
                                        setData(
                                            'procedimientos',
                                            data.procedimientos.map((p, j) => ({
                                                ...p,
                                                es_principal:
                                                    j === i && v === true,
                                            })),
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
                                onClick={() => quitarFila('procedimientos', i)}
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
                        <Plus className="size-4" /> Agregar procedimiento
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Equipo quirúrgico
                    </CardTitle>
                    <CardDescription>
                        Personal que participó y sus minutos (base del costo
                        TDABC de talento humano).
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {data.equipo.map((fila, i) => (
                        <div
                            key={i}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <div className="min-w-56 flex-1">
                                <BuscadorSelect
                                    opciones={opcionesRecursos}
                                    valor={fila.recurso_humano_id}
                                    onCambio={(v) => {
                                        const recurso = recursos.find(
                                            (r) => String(r.id) === v,
                                        );
                                        actualizarFila('equipo', i, {
                                            recurso_humano_id: v,
                                            rol: recurso?.rol ?? fila.rol,
                                        });
                                    }}
                                    placeholder="Seleccione persona"
                                    placeholderBusqueda="Buscar por nombre o especialidad…"
                                />
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
                                        actualizarFila('equipo', i, { rol: v })
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
                                <InputError message={error(`equipo.${i}.rol`)} />
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
                                    // Por defecto participan toda la cirugía;
                                    // ajustar la excepción es más rápido que
                                    // teclear los minutos de cada persona.
                                    minutos_participacion: String(
                                        duracion ?? '',
                                    ),
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
                        El precio se toma del insumo al momento de registrar
                        (snapshot).
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {data.consumos.map((fila, i) => (
                        <div
                            key={i}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <div className="min-w-64 flex-1">
                                <BuscadorSelect
                                    opciones={opcionesInsumos}
                                    valor={fila.insumo_id}
                                    onCambio={(v) =>
                                        actualizarFila('consumos', i, {
                                            insumo_id: v,
                                        })
                                    }
                                    placeholder="Seleccione insumo"
                                    placeholderBusqueda="Buscar por nombre o código…"
                                />
                                <InputError
                                    message={error(`consumos.${i}.insumo_id`)}
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
                                    message={error(`consumos.${i}.cantidad`)}
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar"
                                onClick={() => quitarFila('consumos', i)}
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
                    <CardTitle className="text-base">Equipos médicos</CardTitle>
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
                                <BuscadorSelect
                                    opciones={opcionesEquipos}
                                    valor={fila.id}
                                    onCambio={(v) =>
                                        actualizarFila('equipos_medicos', i, {
                                            id: v,
                                        })
                                    }
                                    placeholder="Seleccione equipo"
                                    placeholderBusqueda="Buscar equipo…"
                                />
                                <InputError
                                    message={error(`equipos_medicos.${i}.id`)}
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
                                        actualizarFila('equipos_medicos', i, {
                                            minutos_uso: e.target.value,
                                        })
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
                                {
                                    id: '',
                                    minutos_uso: String(duracion ?? ''),
                                },
                            ])
                        }
                    >
                        <Plus className="size-4" /> Agregar equipo
                    </Button>
                </CardContent>
            </Card>

            <EstimacionCosto estimacion={estimacion} duracionMinutos={duracion} />

            {noContabilizable && (
                <Alert className="border-amber-300/70 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert className="size-4" />
                    <AlertTitle>
                        Este procedimiento no se contabilizará en los indicadores
                    </AlertTitle>
                    <AlertDescription className="text-amber-800 dark:text-amber-300/90">
                        {sinHoraFin && (
                            <p>
                                No tiene hora de finalización: sin ella no hay
                                duración real que costear.
                            </p>
                        )}
                        {sinEstadoRealizada && (
                            <p>
                                Su estado es «{data.estado.replace('_', ' ')}»:
                                solo los procedimientos realizados se costean y
                                entran a los indicadores.
                            </p>
                        )}
                        <p>
                            Puedes guardarlo así y completarlo después con
                            «Cerrar» desde el listado; entretanto queda marcado
                            como «No contabilizada».
                        </p>
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {textoEnviar}
                </Button>
                <Button asChild variant="outline">
                    <Link href={hrefCancelar}>Cancelar</Link>
                </Button>
            </div>
        </form>
    );
}
