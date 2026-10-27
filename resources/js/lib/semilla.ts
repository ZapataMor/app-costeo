/**
 * Los datos de prueba llevan la marca «[SEMILLA]» (o «[SEMILLA-CARGA]») en el
 * propio nombre para no confundirlos con datos reales. Pintada en medio del
 * texto estorba la lectura, así que la UI la separa y la muestra aparte.
 */
const MARCA_SEMILLA = /\s*\[SEMILLA[^\]]*\]\s*/g;

export function separarMarcaSemilla(texto: string): {
    limpio: string;
    esSemilla: boolean;
} {
    const limpio = texto.replace(MARCA_SEMILLA, ' ').trim();

    return { limpio, esSemilla: limpio !== texto.trim() };
}
