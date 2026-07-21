import type { LinkPaginacion } from '@/types/parametros';

export type PacienteFila = {
    id: number;
    tipo_documento: string;
    documento: string;
    nombres: string;
    apellidos: string;
    fecha_nacimiento: string | null;
    sexo: string | null;
    regimen: string;
    asegurador: string | null;
    zona: string;
    municipio: string | null;
    cirugias_count: number;
};

export type PaginadoPacientes = {
    data: PacienteFila[];
    links: LinkPaginacion[];
    total: number;
    from: number | null;
    to: number | null;
};
