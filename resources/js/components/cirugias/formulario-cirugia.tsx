import { Link, useForm } from '@inertiajs/react';
import { Check, Plus, Trash2, TriangleAlert, Wand2 } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { cn } from '@/lib/utils';
import type {
    CatalogoEquipoMedico,
    CatalogoInsumo,
    CatalogoPaciente,
    CatalogoProcedimiento,
    CatalogoRecurso,
    CatalogoSala,
    DatosCirugia,
    FaseCiclo,
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

type ClavePaso = 'paciente' | 'pre' | 'quirurgica' | 'post';

/**
 * Campos que pertenecen a cada paso. Sirven para saltar al paso que trae
 * errores cuando el servidor rechaza el envío: sin esto, un error en un paso
 * oculto dejaría al usuario ante un botón que no hace nada visible.
 */
const CAMPOS_POR_PASO: Record<ClavePaso, string[]> = {
    paciente: [
        'paciente_id',
        'sala_operatoria_id',
        'fecha',
        'tipo',
        'estado',
        'diagnostico_cie10',
        'observaciones',
        'procedimientos',
    ],
    pre: ['hora_ingreso_paciente'],
    quirurgica: [
        'hora_inicio',
        'hora_incision',
        'hora_cierre',
        'hora_fin',
        'equipos_medicos',
    ],
    post: ['hora_salida_recuperacion'],
};

const PASOS: { clave: ClavePaso; titulo: string; fase: FaseCiclo | null }[] = [
    { clave: 'paciente', titulo: 'Paciente', fase: null },
    { clave: 'pre', titulo: 'Pre-quirúrgico', fase: 'pre' },
    { clave: 'quirurgica', titulo: 'Quirúrgico', fase: 'quirurgica' },
    { clave: 'post', titulo: 'Post-quirúrgico', fase: 'post' },
];

/**
 * Formulario de captura del procedimiento, compartido por el registro y la
 * corrección: una sola definición de los campos evita que editar acepte
 * algo distinto de lo que acepta registrar.
 *
 * Está organizado por fases del ciclo porque así ocurre en el hospital y así
 * se costea: los insumos y los minutos de personal se atribuyen a la fase en
 * cuyo paso se capturan, sin que el usuario tenga que elegirla en cada línea.
 *
 * El paso post-quirúrgico solo aparece al corregir. Al registrar, el paciente
 * todavía no ha egresado —esa parte se completa desde el cierre—, y mostrar
 * un paso que no se puede llenar solo estorbaría.
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

    const esCorreccion = metodo === 'put';
    const pasos = useMemo(
        () => (esCorreccion ? PASOS : PASOS.filter((p) => p.clave !== 'post')),
        [esCorreccion],
    );

    const [pasoActual, setPasoActual] = useState(0);

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

    /** Índice del primer paso al que pertenece alguno de los errores. */
    const primerPasoConErrores = (errores: Record<string, string>): number =>
        pasos.findIndex(({ clave, fase }) =>
            Object.keys(errores).some((error) => {
                const [raiz, indice] = error.split('.');

                if (CAMPOS_POR_PASO[clave].includes(raiz)) {
                    return true;
                }

                // Las filas de equipo/consumos pertenecen al paso de su fase.
                if (
                    fase === null ||
                    (raiz !== 'equipo' && raiz !== 'consumos')
                ) {
                    return false;
                }

                return data[raiz][Number(indice)]?.fase === fase;
            }),
        );

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
    const minutosPre = minutosEntre(
        data.hora_ingreso_paciente,
        data.hora_inicio,
    );
    const minutosNeto = minutosEntre(data.hora_incision, data.hora_cierre);
    const minutosPost = minutosEntre(
        data.hora_fin,
        data.hora_salida_recuperacion,
    );
    const cicloTotal = minutosEntre(
        data.hora_ingreso_paciente,
        data.hora_salida_recuperacion,
    );

    /** Protocolo del procedimiento principal: fuente de los tiempos estándar. */
    const protocolo = useMemo(() => {
        const principal =
            data.procedimientos.find((p) => p.es_principal) ??
            data.procedimientos[0];

        return (
            procedimientos.find((p) => String(p.id) === principal?.id) ?? null
        );
    }, [data.procedimientos, procedimientos]);

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

        submit(metodo, urlEnvio, {
            preserveScroll: true,
            // Un error en un paso que no se ve dejaría al usuario ante un
            // botón que aparentemente no hace nada: se salta al paso culpable.
            onError: (errores) => {
                const paso = primerPasoConErrores(errores);

                if (paso >= 0) {
                    setPasoActual(paso);
                }
            },
        });
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
     * Filas de una fase junto con su posición real en el arreglo: los
     * mensajes de error del servidor vienen indexados sobre el arreglo
     * completo, no sobre lo que se ve en pantalla.
     */
    const filasDeFase = <K extends 'equipo' | 'consumos'>(
        campo: K,
        fase: FaseCiclo,
    ): { fila: DatosCirugia[K][number]; indice: number }[] =>
        data[campo]
            .map((fila, indice) => ({ fila, indice }))
            .filter(({ fila }) => fila.fase === fase);

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

    const puedeSugerirPre =
        protocolo !== null &&
        data.hora_ingreso_paciente === '' &&
        data.hora_inicio !== '' &&
        protocolo.minutos_prequirurgico !== null;

    const puedeSugerirPost =
        protocolo !== null &&
        data.hora_salida_recuperacion === '' &&
        data.hora_fin !== '' &&
        protocolo.minutos_recuperacion !== null;

    /** Personal que participó en una fase, con sus minutos. */
    const tarjetaPersonal = (fase: FaseCiclo, descripcion: string) => (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Personal</CardTitle>
                <CardDescription>{descripcion}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {filasDeFase('equipo', fase).map(({ fila, indice }) => (
                    <div
                        key={indice}
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
                                    actualizarFila('equipo', indice, {
                                        recurso_humano_id: v,
                                        rol: recurso?.rol ?? fila.rol,
                                    });
                                }}
                                placeholder="Seleccione persona"
                                placeholderBusqueda="Buscar por nombre o especialidad…"
                            />
                            <InputError
                                message={error(
                                    `equipo.${indice}.recurso_humano_id`,
                                )}
                            />
                        </div>
                        <div className="w-44">
                            <Select
                                value={fila.rol}
                                onValueChange={(v) =>
                                    actualizarFila('equipo', indice, { rol: v })
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
                                message={error(`equipo.${indice}.rol`)}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label
                                htmlFor={`equipo_entrada_${indice}`}
                                className="text-xs text-muted-foreground"
                            >
                                Entrada
                            </Label>
                            <Input
                                id={`equipo_entrada_${indice}`}
                                type="datetime-local"
                                className="w-52"
                                value={fila.hora_inicio}
                                onChange={(e) =>
                                    cambiarHorasMiembro(indice, {
                                        hora_inicio: e.target.value,
                                    })
                                }
                            />
                            <InputError
                                message={error(`equipo.${indice}.hora_inicio`)}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label
                                htmlFor={`equipo_salida_${indice}`}
                                className="text-xs text-muted-foreground"
                            >
                                Salida
                            </Label>
                            <Input
                                id={`equipo_salida_${indice}`}
                                type="datetime-local"
                                className="w-52"
                                value={fila.hora_fin}
                                onChange={(e) =>
                                    cambiarHorasMiembro(indice, {
                                        hora_fin: e.target.value,
                                    })
                                }
                            />
                            <InputError
                                message={error(`equipo.${indice}.hora_fin`)}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label
                                htmlFor={`equipo_minutos_${indice}`}
                                className="text-xs text-muted-foreground"
                            >
                                Minutos
                            </Label>
                            <Input
                                id={`equipo_minutos_${indice}`}
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
                                    actualizarFila('equipo', indice, {
                                        minutos_participacion: e.target.value,
                                        hora_inicio: '',
                                        hora_fin: '',
                                    })
                                }
                            />
                            <InputError
                                message={error(
                                    `equipo.${indice}.minutos_participacion`,
                                )}
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Quitar"
                            onClick={() => quitarFila('equipo', indice)}
                        >
                            <Trash2 className="size-4 text-destructive" />
                        </Button>
                        <InputError message={error(`equipo.${indice}.fase`)} />
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
                                fase,
                                // Por defecto entra y sale con la cirugía;
                                // ajustar la excepción es más rápido que
                                // capturar los tiempos de cada persona.
                                hora_inicio:
                                    fase === 'quirurgica'
                                        ? data.hora_inicio
                                        : '',
                                hora_fin:
                                    fase === 'quirurgica' ? data.hora_fin : '',
                                minutos_participacion:
                                    fase === 'quirurgica'
                                        ? String(duracion ?? '')
                                        : '',
                            },
                        ])
                    }
                >
                    <Plus className="size-4" /> Agregar persona
                </Button>
            </CardContent>
        </Card>
    );

    /** Insumos consumidos en una fase. */
    const tarjetaInsumos = (fase: FaseCiclo, descripcion: string) => (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Insumos</CardTitle>
                <CardDescription>{descripcion}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {filasDeFase('consumos', fase).map(({ fila, indice }) => (
                    <div
                        key={indice}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <div className="min-w-64 flex-1">
                            <BuscadorSelect
                                opciones={opcionesInsumos}
                                valor={fila.insumo_id}
                                onCambio={(v) =>
                                    actualizarFila('consumos', indice, {
                                        insumo_id: v,
                                    })
                                }
                                placeholder="Seleccione insumo"
                                placeholderBusqueda="Buscar por nombre o código…"
                            />
                            <InputError
                                message={error(`consumos.${indice}.insumo_id`)}
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
                                    actualizarFila('consumos', indice, {
                                        cantidad: e.target.value,
                                    })
                                }
                            />
                            <InputError
                                message={error(`consumos.${indice}.cantidad`)}
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Quitar"
                            onClick={() => quitarFila('consumos', indice)}
                        >
                            <Trash2 className="size-4 text-destructive" />
                        </Button>
                        <InputError
                            message={error(`consumos.${indice}.fase`)}
                        />
                    </div>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        setData('consumos', [
                            ...data.consumos,
                            { insumo_id: '', fase, cantidad: '' },
                        ])
                    }
                >
                    <Plus className="size-4" /> Agregar insumo
                </Button>
            </CardContent>
        </Card>
    );

    const clavePaso = pasos[pasoActual].clave;
    const esUltimo = pasoActual === pasos.length - 1;

    return (
        <form onSubmit={enviar} className="max-w-4xl space-y-4">
            {/* Navegación por etapas: el ciclo que vive el paciente. */}
            <ol className="flex flex-wrap items-center gap-1 rounded-lg border p-1.5">
                {pasos.map((paso, i) => (
                    <li key={paso.clave} className="flex-1">
                        <button
                            type="button"
                            onClick={() => setPasoActual(i)}
                            aria-current={i === pasoActual ? 'step' : undefined}
                            className={cn(
                                'flex w-full items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-sm whitespace-nowrap transition-colors',
                                i === pasoActual
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-5 shrink-0 items-center justify-center rounded-full border text-xs',
                                    i === pasoActual && 'border-current',
                                )}
                            >
                                {i < pasoActual ? (
                                    <Check className="size-3" />
                                ) : (
                                    i + 1
                                )}
                            </span>
                            {paso.titulo}
                        </button>
                    </li>
                ))}
            </ol>

            {clavePaso === 'paciente' && (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Datos generales
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <div className="flex items-center justify-between gap-2">
                                    <Label>Paciente</Label>
                                    <NuevoPacienteModal
                                        regimenes={regimenes}
                                        onCreado={(id) =>
                                            setData('paciente_id', id)
                                        }
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
                                    onCambio={(v) =>
                                        setData('sala_operatoria_id', v)
                                    }
                                    placeholder="Sin sala"
                                    placeholderBusqueda="Buscar sala…"
                                />
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
                                        <BuscadorSelect
                                            opciones={opcionesProcedimientos}
                                            valor={fila.id}
                                            onCambio={(v) =>
                                                actualizarFila(
                                                    'procedimientos',
                                                    i,
                                                    { id: v },
                                                )
                                            }
                                            placeholder="Seleccione procedimiento"
                                            placeholderBusqueda="Buscar por nombre o código CUPS…"
                                        />
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
                </>
            )}

            {clavePaso === 'pre' && (
                <>
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <CardTitle className="text-base">
                                        Preparación del paciente
                                    </CardTitle>
                                    <CardDescription>
                                        Desde que ingresa hasta que entra a
                                        sala. La sala todavía no se ocupa.
                                    </CardDescription>
                                </div>
                                {puedeSugerirPre && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={sugerirDesdeProtocolo}
                                    >
                                        <Wand2 className="size-4" /> Sugerir del
                                        protocolo
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
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
                        </CardContent>
                    </Card>

                    {tarjetaPersonal(
                        'pre',
                        'Quien prepara al paciente y alista la sala: enfermería, instrumentador, camillero.',
                    )}
                    {tarjetaInsumos(
                        'pre',
                        'Lo que se consume antes de entrar: bata, gorro, vía, antiséptico, profilaxis.',
                    )}
                </>
            )}

            {clavePaso === 'quirurgica' && (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Tiempos de sala
                            </CardTitle>
                            <CardDescription>
                                Solo la entrada a sala es obligatoria. Incisión
                                y cierre permiten separar el tiempo operando del
                                tiempo de sala.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
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
                                            setData(
                                                'hora_inicio',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={error('hora_inicio')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="hora_fin">
                                        Salida de sala
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
                                    <Label htmlFor="hora_incision">
                                        Incisión (opcional)
                                    </Label>
                                    <Input
                                        id="hora_incision"
                                        type="datetime-local"
                                        value={data.hora_incision}
                                        onChange={(e) =>
                                            setData(
                                                'hora_incision',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={error('hora_incision')}
                                    />
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
                                            setData(
                                                'hora_cierre',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={error('hora_cierre')}
                                    />
                                </div>
                            </div>
                            {duracion !== null && (
                                <p className="text-xs text-muted-foreground">
                                    {duracion} min de sala
                                    {minutosNeto !== null && (
                                        <>
                                            {' · '}
                                            {minutosNeto} min de tiempo
                                            quirúrgico neto{' · '}
                                            <span className="text-amber-700 dark:text-amber-500">
                                                {duracion - minutosNeto} min de
                                                sala sin operar
                                            </span>
                                        </>
                                    )}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {tarjetaPersonal(
                        'quirurgica',
                        'El equipo quirúrgico. Sus minutos son la base del costo TDABC de talento humano.',
                    )}
                    {tarjetaInsumos(
                        'quirurgica',
                        'Lo consumido operando: suturas, gasas, material de osteosíntesis, implantes.',
                    )}

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
                                        <BuscadorSelect
                                            opciones={opcionesEquipos}
                                            valor={fila.id}
                                            onCambio={(v) =>
                                                actualizarFila(
                                                    'equipos_medicos',
                                                    i,
                                                    { id: v },
                                                )
                                            }
                                            placeholder="Seleccione equipo"
                                            placeholderBusqueda="Buscar equipo…"
                                        />
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
                </>
            )}

            {clavePaso === 'post' && (
                <>
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <CardTitle className="text-base">
                                        Recuperación
                                    </CardTitle>
                                    <CardDescription>
                                        Desde que sale de sala hasta el egreso.
                                        Si el paciente sigue hospitalizado, deja
                                        el egreso vacío.
                                    </CardDescription>
                                </div>
                                {puedeSugerirPost && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={sugerirDesdeProtocolo}
                                    >
                                        <Wand2 className="size-4" /> Sugerir del
                                        protocolo
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
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
                                {minutosPost !== null && (
                                    <p className="text-xs text-muted-foreground">
                                        {minutosPost} min de recuperación
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {tarjetaPersonal(
                        'post',
                        'Quien atiende al paciente en recuperación, y quien procesa el instrumental usado.',
                    )}
                    {tarjetaInsumos(
                        'post',
                        'Lo consumido después: analgesia, curaciones, material de esterilización.',
                    )}
                </>
            )}

            {esUltimo && (
                <>
                    {cicloTotal !== null && (
                        <div className="flex items-baseline justify-between gap-2 rounded-lg border px-4 py-3 text-sm">
                            <span className="font-medium">
                                Ciclo completo del paciente
                            </span>
                            <span className="tabular-nums">
                                {cicloTotal} min
                            </span>
                        </div>
                    )}

                    <EstimacionCosto
                        estimacion={estimacion}
                        duracionMinutos={duracion}
                    />

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
                                        No tiene hora de salida de sala: sin
                                        ella no hay duración real que costear.
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
                                {sinEgreso && !sinHoraFin && (
                                    <p>
                                        No tiene salida de recuperación:
                                        mientras el paciente siga en el hospital
                                        el ciclo no ha terminado, y darlo por
                                        realizado sería un dato que no ocurrió.
                                    </p>
                                )}
                                <p>
                                    Puedes guardarlo así y completarlo después
                                    con «Cerrar» desde el listado; entretanto
                                    queda marcado como «No contabilizada».
                                </p>
                            </AlertDescription>
                        </Alert>
                    )}
                </>
            )}

            <div className="flex items-center gap-3">
                {pasoActual > 0 && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setPasoActual(pasoActual - 1)}
                    >
                        Atrás
                    </Button>
                )}
                {esUltimo ? (
                    <Button type="submit" disabled={processing}>
                        {textoEnviar}
                    </Button>
                ) : (
                    <Button
                        type="button"
                        onClick={() => setPasoActual(pasoActual + 1)}
                    >
                        Siguiente
                    </Button>
                )}
                <Button asChild variant="outline">
                    <Link href={hrefCancelar}>Cancelar</Link>
                </Button>
            </div>
        </form>
    );
}
