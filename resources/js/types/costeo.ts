import type { LinkPaginacion } from '@/types/parametros';

export type ProcedimientoResumen = {
    id: number;
    codigo_cups: string;
    nombre: string;
};

// ── Explorador de procedimientos (Costeo → Procedimientos) ──────────────

export type FiltrosProcedimientosCosteo = {
    q: string;
    especialidad: string;
    complejidad: string;
};

export type ProcedimientoCosteoFila = {
    id: number;
    codigo_cups: string;
    nombre: string;
    especialidad: string;
    complejidad: string;
    duracion_estimada_minutos: number;
    n_realizadas: number;
    n_costeadas: number;
    costo_promedio: number | null;
};

export type PaginadoProcedimientosCosteo = {
    data: ProcedimientoCosteoFila[];
    links: LinkPaginacion[];
    total: number;
    from: number | null;
    to: number | null;
};

export type ProcedimientoCosteoInfo = {
    id: number;
    codigo_cups: string;
    nombre: string;
    especialidad: string;
    complejidad: string;
    duracion_estimada_minutos: number;
    tarifa_soat: string | null;
};

export type EstadisticasProcedimiento = {
    n_realizadas: number;
    n_costeadas: number;
    costo_promedio: number | null;
    costo_minimo: number | null;
    costo_maximo: number | null;
    duracion_promedio_minutos: number | null;
};

export type FiltrosInstanciasCirugia = {
    desde: string;
    hasta: string;
    estado: string;
};

export type InstanciaCirugiaFila = {
    id: number;
    fecha: string | null;
    hora_inicio: string | null;
    hora_fin: string | null;
    paciente: { nombres: string; apellidos: string } | null;
    sala: string | null;
    estado: string;
    duracion_minutos: number | null;
    costo_total: string | null;
};

export type PaginadoInstanciasCirugia = {
    data: InstanciaCirugiaFila[];
    links: LinkPaginacion[];
    total: number;
    from: number | null;
    to: number | null;
};

export type CostoPorProcedimiento = {
    procedimiento: ProcedimientoResumen;
    n: number;
    costo_promedio: number;
    costo_minimo: number;
    costo_maximo: number;
};

export type CostosKpi = {
    global: {
        n_cirugias_costeadas: number;
        costo_promedio: number;
        costo_minimo: number | null;
        costo_maximo: number | null;
    };
    por_procedimiento: CostoPorProcedimiento[];
};

export type ComponentesProcedimiento = {
    procedimiento: ProcedimientoResumen;
    n: number;
    recurso_humano: number;
    sala: number;
    equipos: number;
    insumos: number;
    indirectos: number;
    total: number;
};

export type PuntoOutlier = {
    cirugia_id: number;
    fecha: string | null;
    costo_total: number;
    z: number;
    es_outlier: boolean;
    criterios: string[];
};

export type GrupoOutliers = {
    procedimiento: ProcedimientoResumen;
    n: number;
    media: number;
    desviacion: number;
    coeficiente_variacion: number | null;
    limites: {
        z_inferior: number;
        z_superior: number;
        iqr_inferior: number;
        iqr_superior: number;
    };
    total_outliers: number;
    puntos: PuntoOutlier[];
};

export type MargenProcedimiento = {
    procedimiento: ProcedimientoResumen;
    n: number;
    costo_promedio: number;
    facturado_promedio: number | null;
    tarifa_soat: number | null;
    tarifa_referencia: number | null;
    margen_vs_facturado: number | null;
    margen_vs_facturado_pct: number | null;
    margen_vs_referencia: number | null;
    margen_vs_referencia_pct: number | null;
};

export type VariabilidadProcedimiento = {
    procedimiento: ProcedimientoResumen;
    n: number;
    media: number;
    desviacion: number;
    coeficiente_variacion: number | null;
    nivel_variabilidad: 'alta' | 'media' | 'baja' | null;
};

export type GlosasRecaudo = {
    n_facturas: number;
    valor_facturado: number;
    valor_glosado: number;
    valor_recaudado: number;
    tasa_glosas: number | null;
    tasa_recaudo: number | null;
};

export type Completitud = {
    total_cirugias_realizadas: number;
    chequeos: Record<
        string,
        { registradas: number; porcentaje: number | null }
    >;
    completas: number;
    completitud_global: number | null;
};

export type UtilizacionSalas = {
    mes: string;
    global: {
        minutos_usados: number;
        minutos_disponibles: number;
        utilizacion_pct: number | null;
    };
    por_sala: {
        sala: { id: number; nombre: string };
        minutos_usados: number;
        minutos_disponibles: number;
        utilizacion_pct: number | null;
    }[];
};

// ── Costeo por persona (Costeo → Personal) ──────────────────────────────

export type FiltrosPersonalCosteo = {
    q: string;
    rol: string;
};

/**
 * Dos lecturas distintas del mismo profesional: lo que cuestan sus minutos
 * (costo propio) y lo que cuestan las cirugías que encabeza (costo inducido),
 * más el índice de esas cirugías contra el promedio de su procedimiento.
 */
export type PersonalCosteoFila = {
    id: number;
    nombre: string;
    rol: string;
    especialidad: string | null;
    activo: boolean;
    n_cirugias: number;
    n_participaciones: number;
    minutos_total: number;
    minutos_promedio: number | null;
    costo_propio_total: number;
    costo_propio_promedio: number | null;
    n_como_cirujano: number;
    costo_inducido_total: number | null;
    costo_inducido_promedio: number | null;
    indice_costo: number | null;
    indice_duracion: number | null;
    n_comparables: number;
};

export type PersonaCosteo = PersonalCosteoFila & {
    costo_mensual_actual: number;
    costo_por_minuto_actual: number;
};

export type TotalesPersonal = {
    n_personas_con_actividad: number;
    costo_propio_total: number;
    minutos_total: number;
};

export type DesglosePersonal = {
    clave: string;
    n_participaciones: number;
    minutos: number;
    costo_propio: number;
};

export type ProcedimientoDePersona = {
    procedimiento: ProcedimientoResumen;
    n: number;
    costo_promedio_suyo: number;
    costo_promedio_hospital: number | null;
    indice_costo: number | null;
};

export type ParticipacionHistorica = {
    cirugia_id: number;
    fecha: string;
    procedimiento: ProcedimientoResumen | null;
    rol: string;
    fase: string;
    minutos: number;
    duracion_cirugia: number | null;
    costo_propio: number;
    costo_total_cirugia: number | null;
    indice_costo: number | null;
};

export type PaginadoHistorialPersona = {
    data: ParticipacionHistorica[];
    links: LinkPaginacion[];
    total: number;
    from: number | null;
    to: number | null;
};

// ── Alertas de sobrecosto (Costeo → Alertas) ────────────────────────────

/** Una línea del desglose: cuánto costó ese componente frente a lo habitual. */
export type AtribucionComponente = {
    componente: string;
    etiqueta: string;
    costo: number;
    esperado: number;
    exceso: number;
    /** Fracción del exceso total que aporta este componente (0–1). */
    aporte_pct: number;
};

export type AlertaSobrecosto = {
    id: number;
    cirugia_id: number;
    fecha: string | null;
    procedimiento: {
        id: number | null;
        codigo_cups: string | null;
        nombre: string | null;
    };
    costo_total: number;
    costo_esperado: number;
    exceso: number;
    exceso_pct: number;
    z: number | null;
    criterios: string[];
    n_baseline: number;
    atribucion: AtribucionComponente[];
    componente_dominante: string;
    componente_dominante_etiqueta: string;
    causas_sugeridas: string[];
    estado: 'pendiente' | 'revisada' | 'descartada';
    estado_etiqueta: string;
    causa: string | null;
    causa_etiqueta: string | null;
    causa_evitable: boolean | null;
    causa_detalle: string | null;
    revisor: string | null;
    revisado_en: string | null;
    detectado_en: string;
};

export type CausaCatalogo = {
    valor: string;
    etiqueta: string;
    /** Si el sobrecosto era gestionable: separa la pérdida recuperable. */
    evitable: boolean;
};

export type ResumenAlertas = {
    pendientes: number;
    revisadas: number;
    exceso_total: number;
    exceso_evitable: number;
    por_causa: {
        causa: string;
        etiqueta: string;
        evitable: boolean;
        n: number;
        exceso: number;
    }[];
};

export type PaginadoAlertas = {
    data: AlertaSobrecosto[];
    links: LinkPaginacion[];
    total: number;
    from: number | null;
    to: number | null;
};
