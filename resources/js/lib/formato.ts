/**
 * Formato único de toda la aplicación. Estaba repartido entre `Intl`,
 * `toLocaleString` y plantillas sueltas, así que la misma cifra salía como
 * «0.98×» en una pantalla y «1,03×» en otra, y las fechas como «2026-07-12»
 * en las tablas y «12/7/2026» en el detalle.
 */

const formatoCop = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

/** Versión abreviada para ejes de gráficas, donde no cabe el número entero. */
const formatoCopCorto = new Intl.NumberFormat('es-CO', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const formatoNumero = new Intl.NumberFormat('es-CO');

const formatoMes = new Intl.DateTimeFormat('es-CO', { month: 'short' });

const formatoHora = new Intl.DateTimeFormat('es-CO', {
    hour: '2-digit',
    minute: '2-digit',
});

export function cop(valor: number | null | undefined): string {
    return valor == null ? '—' : formatoCop.format(valor);
}

/** «$ 1,2 M» — para ejes verticales, no para cifras que se van a leer. */
export function copCorto(valor: number | null | undefined): string {
    return valor == null ? '—' : `$ ${formatoCopCorto.format(valor)}`;
}

export function pct(valor: number | null | undefined, decimales = 1): string {
    return valor == null ? '—' : `${(valor * 100).toFixed(decimales)} %`;
}

/** Variación con signo explícito: «+8,4 %» / «−3,1 %». */
export function pctVariacion(
    valor: number | null | undefined,
    decimales = 1,
): string {
    if (valor == null) {
        return '—';
    }

    const numero = (valor * 100).toFixed(decimales).replace('.', ',');

    return valor > 0 ? `+${numero} %` : numero.replace('-', '−') + ' %';
}

export function numero(valor: number | null | undefined): string {
    return valor == null ? '—' : formatoNumero.format(valor);
}

export function minutos(valor: number | null | undefined): string {
    return valor == null ? '—' : `${formatoNumero.format(valor)} min`;
}

/** Índice relativo: «1,03×». Con coma, como el resto de decimales. */
export function veces(valor: number | null | undefined): string {
    return valor == null ? '—' : `${valor.toFixed(2).replace('.', ',')}×`;
}

/** Acepta «2026-07-12» sin que el navegador lo corra un día por la zona. */
function aFecha(valor: string | Date | null | undefined): Date | null {
    if (valor == null || valor === '') {
        return null;
    }

    if (valor instanceof Date) {
        return valor;
    }

    // Una fecha suelta se parsea como UTC y en Colombia retrocede un día:
    // se fuerza a hora local añadiendo el mediodía.
    const texto = /^\d{4}-\d{2}-\d{2}$/.test(valor)
        ? `${valor}T12:00:00`
        : valor;
    const fecha = new Date(texto);

    return Number.isNaN(fecha.getTime()) ? null : fecha;
}

/**
 * «12 jul 2026».
 *
 * Compuesta a mano: `Intl` en es-CO devuelve «12 de jul de 2026», que en una
 * columna de tabla ocupa el doble y no aporta nada.
 */
export function fecha(valor: string | Date | null | undefined): string {
    const parsed = aFecha(valor);

    if (parsed === null) {
        return '—';
    }

    const dia = String(parsed.getDate()).padStart(2, '0');
    const mes = formatoMes.format(parsed).replace('.', '');

    return `${dia} ${mes} ${parsed.getFullYear()}`;
}

/** «12 jul 2026, 03:00 p. m.» */
export function fechaHora(valor: string | Date | null | undefined): string {
    const parsed = aFecha(valor);

    return parsed === null
        ? '—'
        : `${fecha(parsed)}, ${formatoHora.format(parsed)}`;
}

/**
 * «jul 26» a partir de «2026-07», para ejes de series mensuales.
 *
 * El mes y el año se componen a mano: `Intl` con `month + year` en es-CO
 * devuelve «abr de 26», que en un eje se lee como ruido.
 */
export function mesCorto(valor: string): string {
    const parsed = aFecha(`${valor}-01`);

    if (parsed === null) {
        return valor;
    }

    const mes = formatoMes.format(parsed).replace('.', '');

    return `${mes} ${String(parsed.getFullYear()).slice(-2)}`;
}

/** Enums que llegan como `en_recuperacion`: «En recuperación». */
const ETIQUETAS: Record<string, string> = {
    en_proceso: 'En proceso',
    en_recuperacion: 'En recuperación',
    programada: 'Programada',
    realizada: 'Realizada',
    cancelada: 'Cancelada',
    urgencia: 'Urgencia',
    pre: 'Pre-quirúrgica',
    quirurgica: 'Quirúrgica',
    post: 'Post-quirúrgica',
    cirujano: 'Cirujano',
    ayudante: 'Ayudante',
    anestesiologo: 'Anestesiólogo',
    instrumentador: 'Instrumentador',
    circulante: 'Circulante',
    enfermero: 'Enfermero',
    camillero: 'Camillero',
};

/**
 * Etiqueta legible de un valor de enum. Antes cada pantalla hacía
 * `replace('_', ' ')` + `capitalize`, que deja «en recuperacion» sin tilde y
 * «anestesiologo» sin acento.
 */
export function etiqueta(valor: string | null | undefined): string {
    if (valor == null || valor === '') {
        return '—';
    }

    if (ETIQUETAS[valor] !== undefined) {
        return ETIQUETAS[valor];
    }

    const texto = valor.replaceAll('_', ' ');

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}
