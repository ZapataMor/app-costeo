export type NivelConfiabilidad = 'medido' | 'estimado' | 'supuesto';

export type LinkPaginacion = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginado<T> = {
    data: T[];
    links: LinkPaginacion[];
    total: number;
    from: number | null;
    to: number | null;
};

type Trazable = {
    fuente: string | null;
    nivel_confiabilidad: NivelConfiabilidad;
};

export type RecursoHumanoParam = Trazable & {
    id: number;
    nombre: string;
    rol: string;
    especialidad: string | null;
    salario_mensual: string;
    prestaciones_mensuales: string;
    costos_indirectos_mensuales: string;
    activo: boolean;
};

export type InsumoParam = Trazable & {
    id: number;
    codigo: string;
    nombre: string;
    categoria: string;
    codigo_atc: string | null;
    unidad: string;
    costo_unitario: string;
    activo: boolean;
};

export type EquipoMedicoParam = Trazable & {
    id: number;
    nombre: string;
    codigo: string | null;
    valor_adquisicion: string | null;
    vida_util_anios: number | null;
    costo_hora: string;
    activo: boolean;
};

export type SalaOperatoriaParam = Trazable & {
    id: number;
    nombre: string;
    ubicacion: string | null;
    costo_hora: string;
    equipamiento: string[] | null;
    activa: boolean;
};

export type ProcedimientoParam = Trazable & {
    id: number;
    codigo_cups: string;
    nombre: string;
    especialidad: string;
    complejidad: string;
    /** Tiempo de sala estándar: la fase quirúrgica del ciclo. */
    duracion_estimada_minutos: number;
    minutos_prequirurgico: number | null;
    minutos_recuperacion: number | null;
    minutos_recambio: number | null;
    tarifa_soat: string | null;
    /** Líneas de la plantilla del protocolo; solo vienen en el listado. */
    plantilla_insumos_count?: number;
    plantilla_personal_count?: number;
    plantilla_equipos_count?: number;
};

export type HospitalConfig = {
    id: number;
    nombre: string;
    nit: string;
    municipio: string | null;
    departamento: string;
    horas_dia: number;
    dias_mes: number;
    factor_indirecto: string;
};
