/**
 * Paleta y utilidades comunes de las gráficas.
 *
 * Los colores estaban repetidos como literales en cada página, así que el
 * mismo componente («insumos», «sala») salía de un color en un panel y de
 * otro en el siguiente. Aquí hay una sola definición y las páginas la usan.
 */

/** Componentes del costo TDABC, en el orden en que se apilan. */
export const COMPONENTES = [
    { clave: 'recurso_humano', nombre: 'Talento humano', color: '#5B687C' },
    { clave: 'sala', nombre: 'Sala operatoria', color: '#4C837C' },
    { clave: 'equipos', nombre: 'Equipos médicos', color: '#A47E53' },
    { clave: 'insumos', nombre: 'Insumos', color: '#A99C98' },
    { clave: 'indirectos', nombre: 'Indirectos', color: '#161B2F' },
] as const;

export type ClaveComponente = (typeof COMPONENTES)[number]['clave'];

export const COLOR = {
    costo: '#9E3B3B',
    tarifa: '#5B687C',
    referencia: '#4C837C',
    alerta: '#9E3B3B',
    atencion: '#A47E53',
    bien: '#4C837C',
    neutro: '#5B687C',
} as const;

export const COLOR_POR_NIVEL: Record<string, string> = {
    alta: COLOR.alerta,
    media: COLOR.atencion,
    baja: COLOR.bien,
};

/**
 * Nombre corto para el eje X. Los ejes usaban el código CUPS, que es
 * inequívoco pero ilegible: nadie en un comité de dirección reconoce
 * «740001» como una cesárea.
 */
export function etiquetaEje(nombre: string, max = 24): string {
    // Los datos sembrados llevan el sufijo [SEMILLA]: estorba en un eje.
    const limpio = nombre.replace(/\s*\[SEMILLA\]\s*/g, '').trim();

    return limpio.length > max ? `${limpio.slice(0, max - 1)}…` : limpio;
}

/** Márgenes uniformes: las gráficas se recortaban distinto en cada página. */
export const MARGEN = { top: 16, right: 24, bottom: 8, left: 8 } as const;
