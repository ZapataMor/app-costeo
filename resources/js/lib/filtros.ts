/** Opciones de los desplegables de filtro, a partir de un enum del backend. */
export function opcionesDesdeValores(
    valores: string[],
): { valor: string; etiqueta: string }[] {
    return valores.map((valor) => ({
        valor,
        etiqueta: valor.replace(/_/g, ' '),
    }));
}

/** Filtro de estado activo/inactivo, común a los catálogos de Capa 1. */
export const opcionesActivo = [
    { valor: '1', etiqueta: 'Activos' },
    { valor: '0', etiqueta: 'Inactivos' },
];
