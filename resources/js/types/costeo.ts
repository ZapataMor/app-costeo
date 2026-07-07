export type ProcedimientoResumen = {
    id: number;
    codigo_cups: string;
    nombre: string;
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
    chequeos: Record<string, { registradas: number; porcentaje: number | null }>;
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
