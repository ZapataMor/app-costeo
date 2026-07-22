import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2, TriangleAlert, Wand2 } from 'lucide-react';
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

/** Desplaza un `datetime-local` en minutos, conservando el formato del input. */
function desplazar(momento: string, minutos: number): string {
    if (momento === '') {
        return '';
    }

    const fecha = new Date(momento);
    fecha.setMinutes(fecha.getMinutes() + minutos);

    // `toISOString` pasa a UTC; se compensa el offset para no correr la hora.
    const local = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
}

/** Minutos entre dos `datetime-local`; null si falta alguno o el rango es inválido. */
function minutosEntre(inicio: string, fin: string): number | null {
    if (inicio === '' || fin === '') {
        return null;
    }

    const minutos = Math.round(
        (new Date(fin).getTime() - new Date(inicio).getTime()) / 60000,
    );

    return minutos > 0 ? minutos : null;
}

const vacio: DatosCirugia = {
    paciente_id: '',
    sala_operatoria_id: '',
    fecha: '',
    hora_ingreso_paciente: '',
    hora_inicio: '',
    hora_incision: '',
    hora_cierre: '',
    hora_fin: '',
    hora_salida_recuperacion: '',
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
                detalle: `${p.tipo_documento} ${p.documento}`,
                // Buscar por número de identificación es como llega el
                // paciente identificado desde el quirófano.
                busqueda: p.documento,
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
    // Marcar «realizada» afirma que el paciente ya egresó; el backend lo
    // rechaza sin la salida de recuperación, así que se advierte antes.
    const sinEgreso = data.hora_salida_recuperacion === '';
    const noContabilizable = sinHoraFin || sinEstadoRealizada || sinEgreso;

    /** Duración real capturada, base para sugerir los tiempos de cada recurso. */
    const duracion = minutosEntre(data.hora_inicio, data.hora_fin);

    /**
     * Tiempo de sala (`duracion`) y tiempo quirúrgico neto son distintos: la
     * diferencia es sala ocupada sin operar —inducción, posición, asepsia—,
     * que es donde se ve la ineficiencia de quirófano.
     */
    const minutosPre = minutosEntre(data.hora_ingreso_paciente, data.hora_inicio);
    const minutosNeto = minutosEntre(data.hora_incision, data.hora_cierre);
    const minutosPost = minutosEntre(data.hora_fin, data.hora_salida_recuperacion);
    const cicloTotal = minutosEntre(
        data.hora_ingreso_paciente,
        data.hora_salida_recuperacion,
    );

    /** Protocolo del procedimiento principal: fuente de los tiempos estándar. */
    const protocolo = useMemo(() => {
        const principal =
            data.procedimientos.find((p) => p.es_principal) ??
            data.procedimientos[0];

        return procedimientos.find((p) => String(p.id) === principal?.id) ?? null;
    }, [data.procedimientos, procedimientos]);

    /**
     * Rellena solo las marcas vacías a partir de los tiempos estándar del
     * protocolo. No pisa lo ya capturado: el dato real siempre gana sobre el
     * estándar, que aquí es apenas un punto de partida.
     */
    const sugerirDesdeProtocolo = () => {
        if (protocolo === null) {
            return;
        }

        const cambios: Partial<DatosCirugia> = {};

        if (
            data.hora_ingreso_paciente === '' &&
            data.hora_inicio !== '' &&
            protocolo.minutos_prequirurgico !== null
        ) {
            cambios.hora_ingreso_paciente = desplazar(
                data.hora_inicio,
                -protocolo.minutos_prequirurgico,
            );
        }

        if (
            data.hora_salida_recuperacion === '' &&
            data.hora_fin !== '' &&
            protocolo.minutos_recuperacion !== null
        ) {
            cambios.hora_salida_recuperacion = desplazar(
                data.hora_fin,
                protocolo.minutos_recuperacion,
            );
        }

        setData((actual) => ({ ...actual, ...cambios }));
    };

    const puedeSugerir =
        protocolo !== null &&
        ((data.hora_ingreso_paciente === '' &&
            data.hora_inicio !== '' &&
            protocolo.minutos_prequirurgico !== null) ||
            (data.hora_salida_recuperacion === '' &&
                data.hora_fin !== '' &&
                protocolo.minutos_recuperacion !== null));

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

    /**
     * Cambia la entrada o la salida de un miembro y recalcula sus minutos,
     * que son lo que realmente cuesta. El backend hace el mismo cálculo al
     * validar, así que el número que se ve es el que se guarda.
     */
    const cambiarHorasMiembro = (
        indice: number,
        cambios: { hora_inicio?: string; hora_fin?: string },
    ) => {
        const fila = { ...data.equipo[indice], ...cambios };
        const minutos = minutosEntre(fila.hora_inicio, fila.hora_fin);

        actualizarFila('equipo', indice, {
            ...cambios,
            ...(minutos !== null
                ? { minutos_participacion: String(minutos) }
                : {}),
        });
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
                            placeholderBusqueda="Buscar por documento, nombre o apellido…"
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
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <CardTitle className="text-base">
                                Tiempos del ciclo
                            </CardTitle>
                            <CardDescription>
                                Solo la entrada a sala es obligatoria. Cada marca
                                que agregues permite costear una fase más.
                            </CardDescription>
                        </div>
                        {puedeSugerir && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={sugerirDesdeProtocolo}
                            >
                                <Wand2 className="size-4" /> Sugerir del protocolo
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <section className="space-y-2">
                        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Pre-quirúrgico
                        </h3>
                        <div className="grid gap-2 sm:max-w-sm">
                            <Label htmlFor="hora_ingreso_paciente">
                                Ingreso del paciente
                            </Label>
                            <Input
                                id="hora_ingreso_paciente"
                                type="datetime-local"
                                value={data.hora_ingreso_paciente}
                                onChange={(e) =>
                                    setData(
                                        'hora_ingreso_paciente',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={error('hora_ingreso_paciente')}
                            />
                            {minutosPre !== null && (
                                <p className="text-xs text-muted-foreground">
                                    {minutosPre} min de preparación
                                </p>
                            )}
                        </div>
                    </section>

                    <section className="space-y-2 border-t pt-4">
                        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Quirúrgico
                        </h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="hora_inicio">
                                    Entrada a sala
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
                                <Label htmlFor="hora_fin">Salida de sala</Label>
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
                                <Label htmlFor="hora_incision">
                                    Incisión (opcional)
                                </Label>
                                <Input
                                    id="hora_incision"
                                    type="datetime-local"
                                    value={data.hora_incision}
                                    onChange={(e) =>
                                        setData('hora_incision', e.target.value)
                                    }
                                />
                                <InputError message={error('hora_incision')} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="hora_cierre">
                                    Cierre (opcional)
                                </Label>
                                <Input
                                    id="hora_cierre"
                                    type="datetime-local"
                                    value={data.hora_cierre}
                                    onChange={(e) =>
                                        setData('hora_cierre', e.target.value)
                                    }
                                />
                                <InputError message={error('hora_cierre')} />
                            </div>
                        </div>
                        {duracion !== null && (
                            <p className="text-xs text-muted-foreground">
                                {duracion} min de sala
                                {minutosNeto !== null && (
                                    <>
                                        {' · '}
                                        {minutosNeto} min de tiempo quirúrgico
                                        neto{' · '}
                                        <span className="text-amber-700 dark:text-amber-500">
                                            {duracion - minutosNeto} min de sala
                                            sin operar
                                        </span>
                                    </>
                                )}
                            </p>
                        )}
                    </section>

                    <section className="space-y-2 border-t pt-4">
                        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Post-quirúrgico
                        </h3>
                        <div className="grid gap-2 sm:max-w-sm">
                            <Label htmlFor="hora_salida_recuperacion">
                                Salida de recuperación
                            </Label>
                            <Input
                                id="hora_salida_recuperacion"
                                type="datetime-local"
                                value={data.hora_salida_recuperacion}
                                onChange={(e) =>
                                    setData(
                                        'hora_salida_recuperacion',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={error('hora_salida_recuperacion')}
                            />
                            <p className="text-xs text-muted-foreground">
                                {minutosPost !== null
                                    ? `${minutosPost} min de recuperación`
                                    : 'Si el paciente sigue hospitalizado, déjalo vacío y complétalo al egreso desde el listado.'}
                            </p>
                        </div>
                    </section>

                    {cicloTotal !== null && (
                        <div className="flex items-baseline justify-between gap-2 border-t pt-3 text-sm">
                            <span className="font-medium">Ciclo completo</span>
                            <span className="tabular-nums">
                                {cicloTotal} min
                            </span>
                        </div>
                    )}
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
                        Personal que participó. Registre su entrada y salida —o
                        los minutos directamente—: son la base del costo TDABC
                        de talento humano.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {data.equipo.map((fila, i) => (
                        <div
                            key={i}
                            className="flex flex-wrap items-end gap-2 rounded-lg border p-3"
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
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`equipo_entrada_${i}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Entrada
                                </Label>
                                <Input
                                    id={`equipo_entrada_${i}`}
                                    type="datetime-local"
                                    className="w-52"
                                    value={fila.hora_inicio}
                                    onChange={(e) =>
                                        cambiarHorasMiembro(i, {
                                            hora_inicio: e.target.value,
                                        })
                                    }
                                />
                                <InputError
                                    message={error(`equipo.${i}.hora_inicio`)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`equipo_salida_${i}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Salida
                                </Label>
                                <Input
                                    id={`equipo_salida_${i}`}
                                    type="datetime-local"
                                    className="w-52"
                                    value={fila.hora_fin}
                                    onChange={(e) =>
                                        cambiarHorasMiembro(i, {
                                            hora_fin: e.target.value,
                                        })
                                    }
                                />
                                <InputError
                                    message={error(`equipo.${i}.hora_fin`)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`equipo_minutos_${i}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Minutos
                                </Label>
                                <Input
                                    id={`equipo_minutos_${i}`}
                                    type="number"
                                    min={1}
                                    max={1440}
                                    className="w-28"
                                    placeholder="Minutos"
                                    value={fila.minutos_participacion}
                                    onChange={(e) =>
                                        // Editar los minutos a mano descarta
                                        // las horas: dejarlas daría un rango
                                        // que no cuadra con lo que se cobra.
                                        actualizarFila('equipo', i, {
                                            minutos_participacion:
                                                e.target.value,
                                            hora_inicio: '',
                                            hora_fin: '',
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
                                    // Por defecto entra y sale con la cirugía;
                                    // ajustar la excepción es más rápido que
                                    // capturar los tiempos de cada persona.
                                    hora_inicio: data.hora_inicio,
                                    hora_fin: data.hora_fin,
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
                        {sinEgreso && ! sinHoraFin && (
                            <p>
                                No tiene salida de recuperación: mientras el
                                paciente siga en el hospital el ciclo no ha
                                terminado, y darlo por realizado sería un dato
                                que no ocurrió.
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
