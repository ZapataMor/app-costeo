import type { ClasificacionDesviacion } from '@/types/cirugias';
import type { LinkPaginacion } from '@/types/parametros';

export type Paginado<T> = {
    data: T[];
    links: LinkPaginacion[];
    total: number;
    from: number | null;
    to: number | null;
};

export type Opcion = { valor: string; etiqueta: string };

export type CausaCatalogo = {
    valor: string;
    etiqueta: string;
    evitable: boolean;
};

export type Severidad = 'informativa' | 'advertencia' | 'critica';

export type ProcedimientoRef = {
    id: number;
    codigo_cups: string;
    nombre: string;
};

// ── Tablero ───────────────────────────────────────────────────────────────

export type ResumenConocimiento = {
    lecciones: {
        meta_mensual: number;
        publicadas_mes_actual: number;
        por_mes: { mes: string; publicadas: number }[];
        por_estado: { estado: string; etiqueta: string; n: number }[];
        compartidas_red: number;
        de_la_red: number;
    };
    alertas: {
        pendientes: number;
        impacto_pendiente: number;
        por_severidad: { severidad: Severidad; n: number }[];
        por_tipo: {
            tipo: string;
            nombre: string;
            pendientes: number;
            revisadas: number;
            impacto_pendiente: number;
        }[];
    };
    sobrecostos: {
        total: number;
        revisadas: number;
        tasa_revision: number | null;
        exceso_evitable: number;
        exceso_no_evitable: number;
        por_causa: {
            causa: string;
            etiqueta: string;
            evitable: boolean;
            n: number;
            exceso: number;
        }[];
    };
    recomendaciones: {
        estado: string;
        etiqueta: string;
        n: number;
        ahorro_mensual: number;
    }[];
    desviacion: ({ mes: string } & Record<ClasificacionDesviacion, number>)[];
    comunidades: {
        comunidades: number;
        hilos_activos: number;
        respuestas_30_dias: number;
        participantes: number;
    };
    procedimientos_criticos: {
        id: number;
        nombre: string;
        n: number;
        exceso: number;
    }[];
};

// ── Reglas y alertas ──────────────────────────────────────────────────────

export type ReglaConocimiento = {
    id: number;
    tipo: string;
    nombre: string;
    descripcion: string | null;
    alcance: 'cirugia' | 'periodo';
    severidad: Severidad;
    activa: boolean;
    evaluada_en: string | null;
    parametros: {
        clave: string;
        etiqueta: string;
        unidad: string;
        valor: number;
        por_defecto: number;
    }[];
    pendientes: number;
    total: number;
};

export type AlertaConocimiento = {
    id: number;
    tipo: string;
    tipo_nombre: string;
    severidad: Severidad;
    titulo: string;
    descripcion: string;
    cirugia_id: number | null;
    procedimiento: ProcedimientoRef | null;
    sala: string | null;
    periodo: string | null;
    valor: number | null;
    umbral: number | null;
    impacto_cop: number | null;
    datos: Record<string, unknown> | null;
    estado: 'pendiente' | 'revisada' | 'descartada';
    causa: string | null;
    causa_etiqueta: string | null;
    comentario: string | null;
    revisor: string | null;
    revisado_en: string | null;
    detectada_en: string | null;
    lecciones: number;
};

// ── Lecciones ─────────────────────────────────────────────────────────────

export type LeccionResumen = {
    id: number;
    tipo: 'leccion' | 'mejor_practica';
    tipo_etiqueta: string;
    titulo: string;
    extracto: string;
    categoria: string;
    categoria_etiqueta: string;
    causa_etiqueta: string | null;
    procedimiento: ProcedimientoRef | null;
    especialidad: string | null;
    impacto_estimado_cop: number | null;
    estado: 'borrador' | 'en_revision' | 'publicada' | 'archivada';
    estado_etiqueta: string;
    autor: string | null;
    hospital: string | null;
    compartida_red: boolean;
    etiquetas: string[];
    publicada_en: string | null;
    actualizada_en: string | null;
    utiles: number;
    de_la_red: boolean;
};

export type LeccionDetalle = LeccionResumen & {
    contexto: string;
    leccion: string;
    recomendacion: string | null;
    revisor: string | null;
    revisado_en: string | null;
    motivo_devolucion: string | null;
    cirugias: { id: number; fecha: string }[];
    alerta_sobrecosto: { id: number; cirugia_id: number } | null;
    alerta_conocimiento: { id: number; titulo: string } | null;
    valorada: boolean;
    hilos: { id: number; titulo: string; comunidad_practica_id: number }[];
};

export type ProcedimientoCatalogo = ProcedimientoRef & { especialidad: string };

export type CatalogosLeccion = {
    categorias: Opcion[];
    causas: CausaCatalogo[];
    tipos: Opcion[];
    procedimientos: ProcedimientoCatalogo[];
};

// ── Recomendaciones ───────────────────────────────────────────────────────

export type Recomendacion = {
    id: number;
    tipo: string;
    tipo_etiqueta: string;
    titulo: string;
    descripcion: string;
    procedimiento: ProcedimientoRef | null;
    insumo: string | null;
    sala: string | null;
    ahorro_mensual_estimado: number;
    evidencia: Record<string, unknown> | null;
    estado: 'nueva' | 'aceptada' | 'implementada' | 'descartada';
    estado_etiqueta: string;
    nota: string | null;
    responsable: string | null;
    fecha_compromiso: string | null;
    gestionada_en: string | null;
    implementada_en: string | null;
    generada_en: string;
};

// ── Comunidades ───────────────────────────────────────────────────────────

export type ComunidadResumen = {
    id: number;
    nombre: string;
    especialidad: string | null;
    descripcion: string | null;
    miembros: number;
    hilos: number;
    ultima_actividad: string | null;
    soy_miembro: boolean;
};

export type HiloResumen = {
    id: number;
    titulo: string;
    autor: string | null;
    respuestas: number;
    resuelto: boolean;
    fijado: boolean;
    cerrado: boolean;
    enlazable_tipo: string | null;
    leccion_aprendida_id: number | null;
    ultima_actividad_en: string;
};

export type HiloDetalle = {
    id: number;
    titulo: string;
    cuerpo: string;
    autor: string | null;
    autor_id: number | null;
    creado_en: string | null;
    fijado: boolean;
    cerrado: boolean;
    comunidad: { id: number; nombre: string };
    enlace: { tipo: string; titulo: string; url: string } | null;
    leccion: { id: number; titulo: string } | null;
    respuestas: {
        id: number;
        cuerpo: string;
        autor: string | null;
        es_solucion: boolean;
        creado_en: string | null;
    }[];
};

// ── Mejores prácticas ─────────────────────────────────────────────────────

export type ProcedimientoPracticas = ProcedimientoRef & {
    especialidad: string;
    n: number;
    costo_promedio: number | null;
    desviacion_promedio: number | null;
    en_rango_pct: number | null;
    lecciones: number;
    analizable: boolean;
};

export type PerfilGrupo = {
    n: number;
    costo_total: number | null;
    recurso_humano: number | null;
    sala: number | null;
    equipos: number | null;
    insumos: number | null;
    indirecto: number | null;
    duracion: number | null;
    estancia: number | null;
    tasa_complicaciones: number | null;
};

export type AnalisisPracticas =
    | { suficiente: false; n: number; minimo: number }
    | {
          suficiente: true;
          n: number;
          desde: string;
          hasta: string;
          grupos: { mejores: PerfilGrupo; resto: PerfilGrupo };
          insumos: {
              insumo_id: number;
              insumo: string;
              unidad: string;
              cantidad_mejores: number;
              cantidad_resto: number;
              diferencia_por_cirugia: number;
          }[];
          clasificacion: {
              clasificacion: ClasificacionDesviacion;
              n: number;
          }[];
          profesionales: {
              etiqueta: string;
              es_propio: boolean;
              n: number;
              indice_costo: number | null;
              indice_duracion: number | null;
              tasa_complicaciones: number | null;
          }[];
          tendencia: { mes: string; n: number; costo_promedio: number }[];
      };

export type LeccionBreve = {
    id: number;
    titulo: string;
    tipo: string;
    tipo_etiqueta: string;
    extracto: string;
    publicada_en: string | null;
    hospital: string | null;
};
