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
    // «Cesárea [SEMILLA]: costo…» no debe quedar «Cesárea : costo…».
    const limpio = texto
        .replace(MARCA_SEMILLA, ' ')
        .replace(/\s+([:;,.])/g, '$1')
        .trim();

    // Se mira la marca y no la diferencia de textos: «Algo : x» cambia al
    // limpiar la puntuación sin ser un dato de prueba.
    return { limpio, esSemilla: /\[SEMILLA[^\]]*\]/.test(texto) };
}
