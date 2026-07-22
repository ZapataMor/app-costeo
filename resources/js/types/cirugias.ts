import type { LinkPaginacion } from '@/types/parametros';

export type CirugiaFila = {
    id: number;
    fecha: string | null;
    paciente: { nombres: string; apellidos: string } | null;
    procedimiento_principal: { codigo_cups: string; nombre: string } | null;
    tipo: string;
    estado: string;
    duracion_minutos: number | null;
    costo_total: string | null;
    puede_cerrarse: boolean;
    /** Marca que falta para avanzar el cierre; null si el ciclo ya cerró. */
    paso_cierre: 'sala' | 'ciclo' | null;
    hora_inicio: string;
    hora_fin: string | null;
};

export type PaginadoCirugias = {
    data: CirugiaFila[];
    links: LinkPaginacion[];
    total: number;
    from: number | null;
    to: number | null;
};

export type CatalogoPaciente = { id: number; nombres: string; apellidos: string; tipo_documento: string; documento: string };
export type CatalogoSala = { id: number; nombre: string; costo_hora: string };
export type CatalogoProcedimiento = {
    id: number;
    codigo_cups: string;
    nombre: string;
    /** Tiempo de sala estándar. */
    duracion_estimada_minutos: number;
    minutos_prequirurgico: number | null;
    minutos_recuperacion: number | null;
};
export type CatalogoRecurso = { id: number; nombre: string; rol: string; especialidad: string | null; costo_mensual: number };

/** Parámetros TDABC del hospital activo: base de la estimación en vivo. */
export type ParametrosTdabc = {
    minutos_disponibles_mes: number | null;
    factor_indirecto: number | null;
};
export type CatalogoInsumo = { id: number; codigo: string; nombre: string; unidad: string; costo_unitario: string };
export type CatalogoEquipoMedico = { id: number; nombre: string; costo_hora: string };

/** Filas repetibles del formulario de captura (todo en texto: viene de inputs). */
export type ProcedimientoFila = { id: string; es_principal: boolean };
export type MiembroFila = {
    recurso_humano_id: string;
    rol: string;
    /** Entrada y salida del quirófano; vacías si se digitaron los minutos. */
    hora_inicio: string;
    hora_fin: string;
    minutos_participacion: string;
};
export type ConsumoFila = { insumo_id: string; cantidad: string };
export type EquipoMedicoFila = { id: string; minutos_uso: string };

export type DatosCirugia = {
    paciente_id: string;
    sala_operatoria_id: string;
    fecha: string;
    hora_ingreso_paciente: string;
    hora_inicio: string;
    hora_incision: string;
    hora_cierre: string;
    hora_fin: string;
    hora_salida_recuperacion: string;
    tipo: string;
    estado: string;
    diagnostico_cie10: string;
    observaciones: string;
    procedimientos: ProcedimientoFila[];
    equipo: MiembroFila[];
    consumos: ConsumoFila[];
    equipos_medicos: EquipoMedicoFila[];
};

export type CirugiaDetalle = {
    id: number;
    fecha: string | null;
    hora_ingreso_paciente: string | null;
    hora_inicio: string | null;
    hora_incision: string | null;
    hora_cierre: string | null;
    hora_fin: string | null;
    hora_salida_recuperacion: string | null;
    duracion_minutos: number | null;
    minutos_prequirurgico: number | null;
    minutos_quirurgico_neto: number | null;
    minutos_recuperacion: number | null;
    ciclo_total_minutos: number | null;
    tipo: string;
    estado: string;
    diagnostico_cie10: string | null;
    observaciones: string | null;
    paciente: { nombres: string; apellidos: string } | null;
    sala: { nombre: string; costo_hora: string } | null;
    procedimientos: { id: number; codigo_cups: string; nombre: string; es_principal: boolean }[];
    equipo: { nombre: string | null; rol: string; minutos_participacion: number }[];
    consumos: {
        insumo: string | null;
        unidad: string | null;
        cantidad: string;
        costo_unitario_registrado: string;
        costo_total: string;
    }[];
    equipos_medicos: { nombre: string; minutos_uso: number | null }[];
};

export type DetalleCosto = {
    minutos_disponibles_mes: number;
    recurso_humano: {
        recurso_humano_id: number;
        nombre: string;
        rol: string;
        minutos: number;
        costo_por_minuto: number;
        costo: number;
    }[];
    sala: {
        sala_operatoria_id: number;
        nombre: string;
        minutos: number;
        costo_hora: number;
        costo: number;
    } | null;
    equipos: {
        equipo_medico_id: number;
        nombre: string;
        minutos: number;
        costo_hora: number;
        costo: number;
    }[];
    insumos: {
        insumo_id: number;
        nombre: string | null;
        cantidad: number;
        costo_unitario: number;
        costo: number;
    }[];
};

export type Facturacion = {
    id: number;
    cirugia_id: number;
    valor_facturado: string;
    valor_glosado: string;
    valor_recaudado: string;
    tarifa_referencia_soat: string | null;
    fecha_facturacion: string | null;
};

export type ResultadoClinico = {
    id: number;
    cirugia_id: number;
    complicacion_intraoperatoria: boolean;
    descripcion_complicacion_intra: string | null;
    complicacion_postoperatoria: boolean;
    descripcion_complicacion_post: string | null;
    dias_estancia: number;
    reingreso_30_dias: boolean;
    mortalidad: boolean;
};

export type CostoCirugia = {
    id: number;
    cirugia_id: number;
    costo_recurso_humano: string;
    costo_sala: string;
    costo_equipos: string;
    costo_insumos: string;
    costo_directo: string;
    costo_indirecto: string;
    costo_total: string;
    detalle: DetalleCosto | null;
    calculado_en: string | null;
};
