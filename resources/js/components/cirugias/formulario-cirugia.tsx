import { Link, useForm } from '@inertiajs/react';
import {
    Check,
    ClipboardList,
    Plus,
    Trash2,
    TriangleAlert,
    Wand2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { BuscadorSelect } from '@/components/buscador-select';
import {
    calcularEstimacion,
    EstimacionCosto,
} from '@/components/cirugias/estimacion-costo';
import { NuevoPacienteModal } from '@/components/cirugias/nuevo-paciente-modal';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { etiqueta } from '@/lib/formato';
import { cn } from '@/lib/utils';
import type {
    CatalogoEquipoMedico,
    CatalogoInsumo,
    CatalogoPaciente,
    CatalogoProcedimiento,
    CatalogoRecurso,
    CatalogoSala,
    ConsumoFila,
    DatosCirugia,
    EquipoMedicoFila,
    FaseCiclo,
    MiembroFila,
    ParametrosTdabc,
    PlantillaProcedimiento,
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

/** Hoy en `Y-m-d`, en la zona del navegador. */
function hoy(): string {
    return new Date().toLocaleDateString('en-CA');
}

// Casi siempre se registra el mismo día en que se operó: teclear la fecha
// era el primer paso obligatorio de un formulario que ya es largo.
const vacio: DatosCirugia = {
    paciente_id: '',
    sala_operatoria_id: '',
    fecha: hoy(),
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

const PLANTILLA_VACIA: PlantillaProcedimiento = {
    insumos: [],
    personal: [],
    equipos: [],
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

    /**
     * Con lo mínimo para guardar ya capturado. Sirve para no advertir sobre
     * un formulario vacío: al abrirlo, «no se contabilizará» y un costo
     * estimado en $0 son ciertos pero se leen como un reproche antes de que
     * el usuario haya escrito nada.
     */
    const listoParaGuardar =
        data.paciente_id !== '' &&
        data.procedimientos.some((fila) => fila.id !== '') &&
        data.hora_inicio !== '';

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

    /**
     * Plantilla del protocolo: lo que este procedimiento usa siempre. Es la
     * que hace que el registro no empiece en blanco.
     */
    const plantilla = protocolo?.plantilla ?? PLANTILLA_VACIA;

    /**
     * Convierte una línea de personal de la plantilla en las filas del
     * formulario que implica: una por persona pedida.
     *
     * La persona fija solo se asigna a la primera —dos filas con la misma
     * persona, rol y fase son la misma participación contada dos veces, y el
     * backend las rechaza—; las demás quedan a la espera de nombre, que es
     * exactamente el estado real: «faltan dos ayudantes por identificar».
     */
    const expandirPersonal = (
        fila: PlantillaProcedimiento['personal'][number],
    ): MiembroFila[] => {
        // Si la persona fija ya está capturada en esa fase con ese rol, las
        // filas nuevas van sin nombre: repetirla sería contarla dos veces.
        const yaEsta =
            fila.recurso_humano_id !== '' &&
            data.equipo.some(
                (m) =>
                    m.recurso_humano_id === fila.recurso_humano_id &&
                    m.rol === fila.rol &&
                    m.fase === fila.fase,
            );

        return Array.from({ length: Math.max(1, fila.cantidad) }, (_, i) => ({
            recurso_humano_id: i === 0 && !yaEsta ? fila.recurso_humano_id : '',
            rol: fila.rol,
            fase: fila.fase,
            // Con minutos fijados por la plantilla no se ponen horas: el
            // backend deriva los minutos de las horas cuando ambas vienen, y
            // ganaría el rango de sala sobre el estándar del protocolo.
            hora_inicio:
                fila.minutos === '' && fila.fase === 'quirurgica'
                    ? data.hora_inicio
                    : '',
            hora_fin:
                fila.minutos === '' && fila.fase === 'quirurgica'
                    ? data.hora_fin
                    : '',
            minutos_participacion:
                fila.minutos !== ''
                    ? fila.minutos
                    : fila.fase === 'quirurgica'
                      ? String(duracion ?? '')
                      : '',
        }));
    };

    /** Una línea de la plantilla ya está puesta en el formulario. */
    const yaEstaElInsumo = (insumoId: string, fase: FaseCiclo): boolean =>
        data.consumos.some((c) => c.insumo_id === insumoId && c.fase === fase);

    const cuantosDelRol = (rol: string, fase: FaseCiclo): number =>
        data.equipo.filter((m) => m.rol === rol && m.fase === fase).length;

    const yaEstaElEquipo = (equipoId: string): boolean =>
        data.equipos_medicos.some((e) => e.id === equipoId);

    /**
     * Lo que la plantilla pide y todavía no está capturado, por fase. Es la
     * lista de verificación del digitador: mientras quede algo aquí, o falta
     * registrarlo o hubo una desviación que vale la pena mirar.
     *
     * Los opcionales cuentan como faltantes —se ofrecen para agregarlos de un
     * clic— pero nunca se prellenan solos.
     */
    const insumosFaltantes = (fase: FaseCiclo) =>
        plantilla.insumos.filter(
            (fila) =>
                fila.fase === fase && !yaEstaElInsumo(fila.insumo_id, fase),
        );

    const personalFaltante = (fase: FaseCiclo) =>
        plantilla.personal
            .filter((fila) => fila.fase === fase)
            .map((fila) => ({
                fila,
                faltan: Math.max(
                    0,
                    fila.cantidad - cuantosDelRol(fila.rol, fase),
                ),
            }))
            .filter(({ faltan }) => faltan > 0);

    const equiposFaltantes = () =>
        plantilla.equipos.filter(
            (fila) => !yaEstaElEquipo(fila.equipo_medico_id),
        );

    /** Fuera de la plantilla: se usó algo que el protocolo no contempla. */
    const insumoEsExtra = (fila: ConsumoFila): boolean =>
        plantilla.insumos.length > 0 &&
        !plantilla.insumos.some(
            (p) => p.insumo_id === fila.insumo_id && p.fase === fila.fase,
        );

    const miembroEsExtra = (fila: MiembroFila): boolean =>
        plantilla.personal.length > 0 &&
        !plantilla.personal.some(
            (p) => p.rol === fila.rol && p.fase === fila.fase,
        );

    const equipoEsExtra = (fila: EquipoMedicoFila): boolean =>
        plantilla.equipos.length > 0 &&
        !plantilla.equipos.some((p) => p.equipo_medico_id === fila.id);

    /**
     * Pone todo lo que falta de la plantilla, sin tocar lo ya capturado: el
     * dato real siempre gana sobre el estándar. Deja fuera los opcionales,
     * que se agregan uno a uno desde su chip.
     */
    const aplicarPlantilla = (origen: PlantillaProcedimiento = plantilla) => {
        const consumos: ConsumoFila[] = origen.insumos
            .filter(
                (fila) =>
                    !fila.opcional &&
                    !yaEstaElInsumo(fila.insumo_id, fila.fase),
            )
            .map((fila) => ({
                insumo_id: fila.insumo_id,
                fase: fila.fase,
                cantidad: fila.cantidad,
            }));

        const equipo: MiembroFila[] = origen.personal
            .filter((fila) => !fila.opcional)
            .flatMap((fila) => {
                const faltan =
                    fila.cantidad - cuantosDelRol(fila.rol, fila.fase);

                return faltan > 0
                    ? expandirPersonal(fila).slice(0, faltan)
                    : [];
            });

        const equiposMedicos: EquipoMedicoFila[] = origen.equipos
            .filter(
                (fila) =>
                    !fila.opcional && !yaEstaElEquipo(fila.equipo_medico_id),
            )
            .map((fila) => ({
                id: fila.equipo_medico_id,
                minutos_uso:
                    fila.minutos_uso !== ''
                        ? fila.minutos_uso
                        : String(duracion ?? ''),
            }));

        setData((actual) => ({
            ...actual,
            consumos: [...actual.consumos, ...consumos],
            equipo: [...actual.equipo, ...equipo],
            equipos_medicos: [...actual.equipos_medicos, ...equiposMedicos],
        }));
    };

    /**
     * Elegir el procedimiento trae su plantilla puesta. Solo al registrar y
     * solo desde el principal: al corregir, el registro ya tiene lo que de
     * verdad se usó y volcarle el estándar encima sería reintroducir lo que
     * alguien quitó a propósito. Ahí queda el botón «Traer la plantilla».
     */
    const elegirProcedimiento = (indice: number, id: string) => {
        actualizarFila('procedimientos', indice, { id });

        const elegido = procedimientos.find((p) => String(p.id) === id);
        const esPrincipal =
            data.procedimientos[indice]?.es_principal ||
            data.procedimientos.length === 1;

        if (!esCorreccion && esPrincipal && elegido) {
            aplicarPlantilla(elegido.plantilla);
        }
    };

    /** Tamaño de la plantilla del protocolo elegido. */
    const lineasDePlantilla =
        plantilla.insumos.length +
        plantilla.personal.length +
        plantilla.equipos.length;

    /** Cuántas líneas obligatorias de la plantilla siguen sin capturarse. */
    const pendientesDePlantilla =
        plantilla.insumos.filter(
            (f) => !f.opcional && !yaEstaElInsumo(f.insumo_id, f.fase),
        ).length +
        plantilla.personal
            .filter((f) => !f.opcional)
            .reduce(
                (suma, f) =>
                    suma +
                    Math.max(0, f.cantidad - cuantosDelRol(f.rol, f.fase)),
                0,
            ) +
        plantilla.equipos.filter(
            (f) => !f.opcional && !yaEstaElEquipo(f.equipo_medico_id),
        ).length;

    /**
     * Completa con los tiempos de sala al equipo quirúrgico que quedó sin
     * minutos —lo típico cuando la plantilla se aplicó antes de conocer las
     * horas—. Sin esto, prellenar el personal obligaría a teclear la misma
     * hora tantas veces como personas hay en el quirófano.
     */
    const aplicarTiemposDeSala = () => {
        setData(
            'equipo',
            data.equipo.map((fila) =>
                fila.fase === 'quirurgica' && fila.minutos_participacion === ''
                    ? {
                          ...fila,
                          hora_inicio: data.hora_inicio,
                          hora_fin: data.hora_fin,
                          minutos_participacion: String(duracion ?? ''),
                      }
                    : fila,
            ),
        );
    };

    const faltanTiemposDeSala =
        duracion !== null &&
        data.equipo.some(
            (fila) =>
                fila.fase === 'quirurgica' && fila.minutos_participacion === '',
        );

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

    const nombreInsumo = (id: string): string =>
        insumos.find((i) => String(i.id) === id)?.nombre ?? 'Insumo';

    const nombreEquipo = (id: string): string =>
        equiposMedicos.find((e) => String(e.id) === id)?.nombre ?? 'Equipo';

    /**
     * Lo que la plantilla pide y todavía no está: un clic lo agrega. Es la
     * otra mitad de preorganizar —quitar lo que no se usó es un botón, y
     * volver a ponerlo también, sin buscar de nuevo en todo el catálogo—.
     */
    const chipsFaltantes = (
        titulo: string,
        chips: { clave: string; etiqueta: string; opcional: boolean }[],
        onAgregar: (clave: string) => void,
    ) =>
        chips.length > 0 && (
            <div className="flex flex-wrap items-center gap-1.5 border-t pt-3">
                <span className="text-xs text-muted-foreground">{titulo}</span>
                {chips.map(({ clave, etiqueta, opcional }) => (
                    <Button
                        key={clave}
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() => onAgregar(clave)}
                    >
                        <Plus className="size-3" />
                        {etiqueta}
                        {opcional && (
                            <span className="text-muted-foreground">
                                (opcional)
                            </span>
                        )}
                    </Button>
                ))}
            </div>
        );

    /** Marca la línea que el protocolo no contempla: es la desviación. */
    const marcaExtra = (esExtra: boolean) =>
        esExtra && (
            <Badge
                variant="outline"
                className="border-amber-400/70 text-amber-700 dark:text-amber-500"
            >
                Fuera de plantilla
            </Badge>
        );

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
                                etiquetaAccesible="Persona del equipo"
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
                                <SelectTrigger aria-label="Rol quirúrgico">
                                    <SelectValue placeholder="Rol" />
                                </SelectTrigger>
                                <SelectContent>
                                    {rolesQuirurgicos.map((rol) => (
                                        <SelectItem key={rol} value={rol}>
                                            {etiqueta(rol)}
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
                        {marcaExtra(miembroEsExtra(fila))}
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

                {fase === 'quirurgica' && faltanTiemposDeSala && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="ml-2"
                        onClick={aplicarTiemposDeSala}
                    >
                        <Wand2 className="size-4" /> Usar los tiempos de sala
                    </Button>
                )}

                {chipsFaltantes(
                    'De la plantilla falta:',
                    personalFaltante(fase).map(({ fila, faltan }) => ({
                        clave: `${fila.rol}|${fila.fase}|${fila.recurso_humano_id}`,
                        etiqueta:
                            faltan > 1 ? `${faltan} × ${fila.rol}` : fila.rol,
                        opcional: fila.opcional,
                    })),
                    (clave) => {
                        const linea = plantilla.personal.find(
                            (p) =>
                                `${p.rol}|${p.fase}|${p.recurso_humano_id}` ===
                                clave,
                        );

                        if (linea === undefined) {
                            return;
                        }

                        const faltan =
                            linea.cantidad -
                            cuantosDelRol(linea.rol, linea.fase);

                        setData('equipo', [
                            ...data.equipo,
                            ...expandirPersonal(linea).slice(
                                0,
                                Math.max(1, faltan),
                            ),
                        ]);
                    },
                )}
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
                                etiquetaAccesible="Insumo"
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
                        {marcaExtra(insumoEsExtra(fila))}
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

                {chipsFaltantes(
                    'De la plantilla falta:',
                    insumosFaltantes(fase).map((fila) => ({
                        clave: fila.insumo_id,
                        etiqueta: `${nombreInsumo(fila.insumo_id)} × ${fila.cantidad}`,
                        opcional: fila.opcional,
                    })),
                    (insumoId) => {
                        const linea = plantilla.insumos.find(
                            (p) => p.insumo_id === insumoId && p.fase === fase,
                        );

                        setData('consumos', [
                            ...data.consumos,
                            {
                                insumo_id: insumoId,
                                fase,
                                cantidad: linea?.cantidad ?? '',
                            },
                        ]);
                    },
                )}
            </CardContent>
        </Card>
    );

    const clavePaso = pasos[pasoActual].clave;
    const esUltimo = pasoActual === pasos.length - 1;

    return (
        <form
            onSubmit={enviar}
            className="grid max-w-6xl gap-4 lg:grid-cols-[minmax(0,1fr)_290px] lg:items-start"
        >
            <div className="min-w-0 space-y-4">
                {/* Navegación por etapas: el ciclo que vive el paciente. */}
                <ol className="flex flex-wrap items-center gap-1 rounded-lg border p-1.5">
                    {pasos.map((paso, i) => (
                        <li key={paso.clave} className="flex-1">
                            <button
                                type="button"
                                onClick={() => setPasoActual(i)}
                                aria-current={
                                    i === pasoActual ? 'step' : undefined
                                }
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
                                        onCambio={(v) =>
                                            setData('paciente_id', v)
                                        }
                                        placeholder="Seleccione paciente"
                                        placeholderBusqueda="Buscar por documento, nombre o apellido…"
                                        sinResultados="Ningún paciente coincide. Use «Nuevo paciente»."
                                        etiquetaAccesible="Paciente"
                                    />
                                    <InputError
                                        message={error('paciente_id')}
                                    />
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
                                        etiquetaAccesible="Sala operatoria"
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
                                {/* Tipo y estado compartían un único rótulo
                                «Tipo / Estado» sobre dos desplegables: son
                                dos decisiones distintas y ninguno de los dos
                                tenía nombre accesible. */}
                                <div className="grid gap-2">
                                    <Label htmlFor="tipo">Tipo</Label>
                                    <Select
                                        value={data.tipo}
                                        onValueChange={(v) =>
                                            setData('tipo', v)
                                        }
                                    >
                                        <SelectTrigger
                                            id="tipo"
                                            aria-label="Tipo"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {tipos.map((t) => (
                                                <SelectItem key={t} value={t}>
                                                    {etiqueta(t)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={error('tipo')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="estado">Estado</Label>
                                    <Select
                                        value={data.estado}
                                        onValueChange={(v) =>
                                            setData('estado', v)
                                        }
                                    >
                                        <SelectTrigger
                                            id="estado"
                                            aria-label="Estado"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {estados.map((est) => (
                                                <SelectItem
                                                    key={est}
                                                    value={est}
                                                >
                                                    {etiqueta(est)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={error('estado')} />
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
                                            setData(
                                                'observaciones',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={error('observaciones')}
                                    />
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
                                                opciones={
                                                    opcionesProcedimientos
                                                }
                                                valor={fila.id}
                                                onCambio={(v) =>
                                                    elegirProcedimiento(i, v)
                                                }
                                                placeholder="Seleccione procedimiento"
                                                placeholderBusqueda="Buscar por nombre o código CUPS…"
                                                etiquetaAccesible={`Procedimiento ${i + 1}`}
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

                                {/* Qué trae preorganizado el protocolo elegido:
                                el digitador ve de entrada que no parte de
                                cero, y desde dónde ajustar la excepción. */}
                                {protocolo !== null && (
                                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-muted/40 p-3 text-sm">
                                        {lineasDePlantilla === 0 ? (
                                            <p className="text-muted-foreground">
                                                «{protocolo.nombre}» no tiene
                                                plantilla: habrá que capturar
                                                insumos, personal y equipos a
                                                mano.
                                            </p>
                                        ) : (
                                            <p className="text-muted-foreground">
                                                El protocolo trae{' '}
                                                <span className="font-medium text-foreground">
                                                    {lineasDePlantilla} líneas
                                                </span>{' '}
                                                preorganizadas.
                                                {pendientesDePlantilla > 0
                                                    ? ` Faltan ${pendientesDePlantilla} por poner en este registro.`
                                                    : ' Ya están todas puestas: quita lo que no se use y agrega lo demás.'}
                                            </p>
                                        )}
                                        {pendientesDePlantilla > 0 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    aplicarPlantilla()
                                                }
                                            >
                                                <ClipboardList className="size-4" />{' '}
                                                Traer la plantilla
                                            </Button>
                                        )}
                                    </div>
                                )}
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
                                            <Wand2 className="size-4" /> Sugerir
                                            del protocolo
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
                                    Solo la entrada a sala es obligatoria.
                                    Incisión y cierre permiten separar el tiempo
                                    operando del tiempo de sala.
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
                                                setData(
                                                    'hora_fin',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={error('hora_fin')}
                                        />
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
                                                    {duracion - minutosNeto} min
                                                    de sala sin operar
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
                                                etiquetaAccesible="Equipo médico"
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
                                        {marcaExtra(equipoEsExtra(fila))}
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
                                                minutos_uso: String(
                                                    duracion ?? '',
                                                ),
                                            },
                                        ])
                                    }
                                >
                                    <Plus className="size-4" /> Agregar equipo
                                </Button>

                                {chipsFaltantes(
                                    'De la plantilla falta:',
                                    equiposFaltantes().map((fila) => ({
                                        clave: fila.equipo_medico_id,
                                        etiqueta: nombreEquipo(
                                            fila.equipo_medico_id,
                                        ),
                                        opcional: fila.opcional,
                                    })),
                                    (equipoId) => {
                                        const linea = plantilla.equipos.find(
                                            (p) =>
                                                p.equipo_medico_id === equipoId,
                                        );

                                        setData('equipos_medicos', [
                                            ...data.equipos_medicos,
                                            {
                                                id: equipoId,
                                                minutos_uso:
                                                    linea?.minutos_uso !== ''
                                                        ? (linea?.minutos_uso ??
                                                          '')
                                                        : String(
                                                              duracion ?? '',
                                                          ),
                                            },
                                        ]);
                                    },
                                )}
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
                                            Desde que sale de sala hasta el
                                            egreso. Si el paciente sigue
                                            hospitalizado, deja el egreso vacío.
                                        </CardDescription>
                                    </div>
                                    {puedeSugerirPost && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={sugerirDesdeProtocolo}
                                        >
                                            <Wand2 className="size-4" /> Sugerir
                                            del protocolo
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
                                        message={error(
                                            'hora_salida_recuperacion',
                                        )}
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
                        {/* Resumen antes de guardar: hasta aquí se enviaba sin
                        haber visto nunca junto lo capturado en los tres
                        pasos, y corregir después cuesta más que revisar. */}
                        <ResumenRegistro
                            data={data}
                            catalogos={catalogos}
                            cicloTotal={cicloTotal}
                            duracion={duracion}
                            minutosPre={minutosPre}
                            minutosPost={minutosPost}
                            onIrAPaso={setPasoActual}
                            pasos={pasos}
                        />

                        {listoParaGuardar && noContabilizable && (
                            <Alert className="border-amber-300/70 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                <TriangleAlert className="size-4" />
                                <AlertTitle>
                                    Este procedimiento no se contabilizará
                                    todavía
                                </AlertTitle>
                                <AlertDescription className="text-amber-800 dark:text-amber-300/90">
                                    {sinHoraFin && (
                                        <p>
                                            No tiene hora de salida de sala: sin
                                            ella no hay duración real que
                                            costear.
                                        </p>
                                    )}
                                    {sinEstadoRealizada && (
                                        <p>
                                            Su estado es «
                                            {etiquetaValor(
                                                data.estado,
                                            ).toLowerCase()}
                                            »: solo los procedimientos
                                            realizados se costean y entran a los
                                            indicadores.
                                        </p>
                                    )}
                                    {sinEgreso && !sinHoraFin && (
                                        <p>
                                            No tiene salida de recuperación:
                                            mientras el paciente siga en el
                                            hospital el ciclo no ha terminado, y
                                            darlo por realizado sería un dato
                                            que no ocurrió.
                                        </p>
                                    )}
                                    <p className="mt-1 font-medium">
                                        Es lo normal al registrar: guárdalo así
                                        y ciérralo con «Cerrar» desde el listado
                                        cuando el paciente egrese.
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
            </div>

            {/* La estimación acompaña los tres pasos en vez de aparecer
                    solo al final: ver el costo crecer mientras se captura es
                    lo que convierte el formulario en una herramienta y no en
                    un trámite. */}
            <aside className="space-y-4 lg:sticky lg:top-4">
                {estimacion !== null && estimacion.total > 0 && (
                    <EstimacionCosto
                        estimacion={estimacion}
                        duracionMinutos={duracion}
                    />
                )}

                {!esCorreccion && (
                    <p className="rounded-lg border bg-muted/40 p-3 text-xs text-muted-foreground">
                        El egreso del paciente se registra después: al guardar,
                        este procedimiento queda abierto y se completa con{' '}
                        <strong>«Cerrar»</strong> desde el listado cuando salga
                        de recuperación.
                    </p>
                )}
            </aside>
        </form>
    );
}

/** Etiqueta legible de un estado o tipo, con sus tildes. */
function etiquetaValor(valor: string): string {
    return etiqueta(valor);
}

/**
 * Repaso de lo capturado en los tres pasos, con un enlace de vuelta a cada
 * uno. Se enseña justo antes del botón de guardar.
 */
function ResumenRegistro({
    data,
    catalogos,
    cicloTotal,
    duracion,
    minutosPre,
    minutosPost,
    onIrAPaso,
    pasos,
}: {
    data: DatosCirugia;
    catalogos: CatalogosCirugia;
    cicloTotal: number | null;
    duracion: number | null;
    minutosPre: number | null;
    minutosPost: number | null;
    onIrAPaso: (indice: number) => void;
    pasos: { clave: ClavePaso; titulo: string; fase: FaseCiclo | null }[];
}) {
    const paciente = catalogos.pacientes.find(
        (p) => String(p.id) === data.paciente_id,
    );
    const sala = catalogos.salas.find(
        (s) => String(s.id) === data.sala_operatoria_id,
    );
    const principal = catalogos.procedimientos.find(
        (p) =>
            String(p.id) ===
            (data.procedimientos.find((f) => f.es_principal)?.id ??
                data.procedimientos[0]?.id),
    );

    const irA = (clave: ClavePaso) => {
        const indice = pasos.findIndex((paso) => paso.clave === clave);

        if (indice >= 0) {
            onIrAPaso(indice);
        }
    };

    const porFase = (fase: FaseCiclo) => ({
        personas: data.equipo.filter((f) => f.fase === fase).length,
        insumos: data.consumos.filter((f) => f.fase === fase).length,
    });

    const filas: {
        clave: ClavePaso;
        titulo: string;
        contenido: React.ReactNode;
    }[] = [
        {
            clave: 'paciente',
            titulo: 'Paciente y procedimiento',
            contenido: (
                <>
                    {paciente
                        ? `${paciente.apellidos}, ${paciente.nombres}`
                        : 'Sin paciente'}
                    {' · '}
                    {principal?.nombre ?? 'Sin procedimiento'}
                    {sala ? ` · ${sala.nombre}` : ' · Sin sala'}
                </>
            ),
        },
        {
            clave: 'pre',
            titulo: 'Pre-quirúrgico',
            contenido: (
                <>
                    {minutosPre !== null
                        ? `${minutosPre} min de preparación`
                        : 'Sin hora de ingreso'}
                    {' · '}
                    {porFase('pre').personas} personas ·{' '}
                    {porFase('pre').insumos} insumos
                </>
            ),
        },
        {
            clave: 'quirurgica',
            titulo: 'Quirúrgico',
            contenido: (
                <>
                    {duracion !== null
                        ? `${duracion} min de sala`
                        : 'Sin salida de sala'}
                    {' · '}
                    {porFase('quirurgica').personas} personas ·{' '}
                    {porFase('quirurgica').insumos} insumos ·{' '}
                    {data.equipos_medicos.length} equipos
                </>
            ),
        },
    ];

    if (pasos.some((paso) => paso.clave === 'post')) {
        filas.push({
            clave: 'post',
            titulo: 'Post-quirúrgico',
            contenido: (
                <>
                    {minutosPost !== null
                        ? `${minutosPost} min de recuperación`
                        : 'Sin egreso'}
                    {' · '}
                    {porFase('post').personas} personas ·{' '}
                    {porFase('post').insumos} insumos
                </>
            ),
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    Repaso antes de guardar
                </CardTitle>
                <CardDescription>
                    Toque cualquier bloque para volver a él y corregirlo.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
                {filas.map((fila) => (
                    <button
                        key={fila.clave}
                        type="button"
                        onClick={() => irA(fila.clave)}
                        className="flex w-full flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-muted"
                    >
                        <span className="font-medium">{fila.titulo}</span>
                        <span className="text-muted-foreground">
                            {fila.contenido}
                        </span>
                    </button>
                ))}

                {cicloTotal !== null && (
                    <div className="flex items-baseline justify-between gap-2 border-t px-2 pt-3 text-sm">
                        <span className="font-medium">
                            Ciclo completo del paciente
                        </span>
                        <span className="tabular-nums">{cicloTotal} min</span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
