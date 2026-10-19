import type { RolUsuario } from './auth';

export type NivelComplejidad = 'baja' | 'media' | 'alta';

/** Fila del listado de instituciones (con conteos). */
export type InstitucionResumen = {
    id: number;
    nombre: string;
    nit: string;
    nivel_complejidad: NivelComplejidad | string;
    municipio: string | null;
    departamento: string;
    usuarios: number;
    cirugias: number;
};

/** Institución completa, con su capacidad TDABC. */
export type Institucion = {
    id: number;
    nombre: string;
    nit: string;
    nivel_complejidad: NivelComplejidad | string;
    municipio: string | null;
    departamento: string;
    horas_dia: number;
    dias_mes: number;
    minutos_efectivos_hora: number;
    factor_indirecto: number;
    minutos_disponibles_mes: number;
    created_at: string | null;
};

export type UsuarioInstitucion = {
    id: number;
    name: string;
    email: string;
    role: RolUsuario;
    activo: boolean;
    created_at: string | null;
};

export const etiquetasRol: Record<RolUsuario, string> = {
    super_admin: 'Super administrador',
    admin_hospital: 'Administrador',
    digitador: 'Digitador',
};

export const etiquetasNivel: Record<string, string> = {
    baja: 'Baja',
    media: 'Media',
    alta: 'Alta',
};
