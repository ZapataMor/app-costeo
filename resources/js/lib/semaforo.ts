import { pctVariacion } from '@/lib/formato';

/**
 * Semáforo de los indicadores. Un número suelto («Recaudo 63,1 %») no dice
 * si es bueno o malo: cada indicador con meta lleva un tono, y los que se
 * comparan con el periodo anterior, la flecha en el color que corresponde.
 */
export type Tono = 'bien' | 'atencion' | 'alerta' | 'neutro';

/**
 * Umbrales en un solo lugar para que el mismo indicador no salga verde en
 * una pantalla y ámbar en otra.
 */
export const UMBRALES = {
    // Meta de la tesis: completitud de la base > 90 % (Fundamentación,
    // indicador 16). Bajo 75 % los tableros dejan de ser confiables.
    completitud: { bien: 0.9, atencion: 0.75 },
    // Margen sobre lo facturado: perder hasta un 10 % es atención; más, alerta.
    // [VERIFICAR] con el cliente el umbral de pérdida tolerable.
    margen: { bien: 0, atencion: -0.1 },
    // Variación del costo promedio frente al mes anterior: subir más de 5 %
    // es alerta. La meta de la tesis es bajarlo entre 15 y 30 %.
    variacionCosto: { bien: 0, atencion: 0.05 },
    // Desviación del costo real sobre el estándar del protocolo: los mismos
    // cortes de la clasificación por defecto (en rango ≤ 5 %, leve ≤ 20 %,
    // alto > 20 %; backlog #1). Cada hospital puede moverlos en su
    // configuración; aquí se usan los del pipeline doctoral.
    desviacionEstandar: { bien: 0.05, atencion: 0.2 },
} as const;

/** Tono de un valor donde más es mejor (completitud, margen). */
export function tonoMayorEsMejor(
    valor: number | null | undefined,
    umbral: { bien: number; atencion: number },
): Tono {
    if (valor == null) {
        return 'neutro';
    }

    if (valor >= umbral.bien) {
        return 'bien';
    }

    return valor >= umbral.atencion ? 'atencion' : 'alerta';
}

/** Tono de un valor donde menos es mejor (variación del costo). */
export function tonoMenorEsMejor(
    valor: number | null | undefined,
    umbral: { bien: number; atencion: number },
): Tono {
    if (valor == null) {
        return 'neutro';
    }

    if (valor <= umbral.bien) {
        return 'bien';
    }

    return valor <= umbral.atencion ? 'atencion' : 'alerta';
}

export type Comparacion = { texto: string; tono: Tono };

/**
 * Variación relativa contra el periodo anterior: «▲ +4,2 % vs. sept.».
 * `mejorSi` dice en qué dirección es buena noticia.
 */
export function compararRelativo(
    actual: number | null | undefined,
    anterior: number | null | undefined,
    mejorSi: 'sube' | 'baja',
    referencia: string,
): Comparacion | undefined {
    if (actual == null || anterior == null || anterior === 0) {
        return undefined;
    }

    const cambio = (actual - anterior) / Math.abs(anterior);

    return {
        texto: `${flecha(cambio)} ${pctVariacion(cambio)} vs. ${referencia}`,
        tono: tonoDeCambio(cambio, mejorSi),
    };
}

/**
 * Diferencia en puntos porcentuales, para indicadores que ya son un
 * porcentaje (completitud, margen): «▼ −2,1 pts vs. sept.».
 */
export function compararPuntos(
    actual: number | null | undefined,
    anterior: number | null | undefined,
    mejorSi: 'sube' | 'baja',
    referencia: string,
): Comparacion | undefined {
    if (actual == null || anterior == null) {
        return undefined;
    }

    const cambio = actual - anterior;
    const puntos = (cambio * 100).toFixed(1).replace('.', ',');
    const conSigno = cambio > 0 ? `+${puntos}` : puntos.replace('-', '−');

    return {
        texto: `${flecha(cambio)} ${conSigno} pts vs. ${referencia}`,
        tono: tonoDeCambio(cambio, mejorSi),
    };
}

function flecha(cambio: number): string {
    if (Math.abs(cambio) < 0.0005) {
        return '=';
    }

    return cambio > 0 ? '▲' : '▼';
}

function tonoDeCambio(cambio: number, mejorSi: 'sube' | 'baja'): Tono {
    if (Math.abs(cambio) < 0.0005) {
        return 'neutro';
    }

    const mejora = mejorSi === 'sube' ? cambio > 0 : cambio < 0;

    return mejora ? 'bien' : 'alerta';
}

/** Clases de texto por tono, con contraste suficiente en ambos temas. */
export const TEXTO_TONO: Record<Tono, string> = {
    bien: 'text-emerald-700 dark:text-emerald-400',
    atencion: 'text-amber-700 dark:text-amber-400',
    alerta: 'text-red-700 dark:text-red-400',
    neutro: 'text-muted-foreground',
};

/** Color sólido del punto del semáforo. */
export const PUNTO_TONO: Record<Tono, string> = {
    bien: 'bg-emerald-600 dark:bg-emerald-400',
    atencion: 'bg-amber-500 dark:bg-amber-400',
    alerta: 'bg-red-600 dark:bg-red-400',
    neutro: 'bg-muted-foreground/40',
};

export const NOMBRE_TONO: Record<Tono, string> = {
    bien: 'en meta',
    atencion: 'requiere atención',
    alerta: 'fuera de meta',
    neutro: 'sin referencia',
};
