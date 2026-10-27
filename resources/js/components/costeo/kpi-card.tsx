import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Comparacion, Tono } from '@/lib/semaforo';
import { NOMBRE_TONO, PUNTO_TONO, TEXTO_TONO } from '@/lib/semaforo';

/**
 * Indicador principal: el primer nivel de la jerarquía de cada tablero.
 *
 * El título iba en 10 px, mayúsculas y #5B687C (2,9:1 sobre la tarjeta
 * oscura); ahora es una frase normal y legible, y la cifra pesa más que todo
 * lo demás de la tarjeta.
 *
 * Con `tono`, un punto de semáforo dice si el valor está en meta; con
 * `comparacion`, una línea dice hacia dónde se movió frente al periodo
 * anterior. Sin ellos la tarjeta es la de siempre.
 */
export function KpiCard({
    titulo,
    valor,
    detalle,
    tono,
    comparacion,
}: {
    titulo: string;
    valor: string;
    detalle?: string;
    tono?: Tono;
    comparacion?: Comparacion;
}) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="px-5 pt-5 pb-2">
                <CardTitle className="flex items-start gap-2 font-sans text-[13px] leading-snug font-medium text-[var(--etiqueta)]">
                    {tono && tono !== 'neutro' && (
                        <span
                            className={`mt-1 size-2 shrink-0 rounded-full ${PUNTO_TONO[tono]}`}
                            title={NOMBRE_TONO[tono]}
                            aria-hidden
                        />
                    )}
                    <span>{titulo}</span>
                    {tono && tono !== 'neutro' && (
                        <span className="sr-only">: {NOMBRE_TONO[tono]}</span>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent className="px-5 pb-5">
                {/* Las cifras de miles de millones («$ 1.501.087.500») no
                    caben a 26 px en una fila de cinco tarjetas. */}
                <div
                    className={`font-sans leading-tight font-semibold tabular-nums ${valor.length > 13 ? 'text-[21px]' : 'text-[26px]'}`}
                >
                    {valor}
                </div>
                {comparacion && (
                    <p
                        className={`mt-1 text-[12.5px] font-medium tabular-nums ${TEXTO_TONO[comparacion.tono]}`}
                    >
                        {comparacion.texto}
                    </p>
                )}
                {detalle && (
                    <p className="mt-1 text-[12.5px] text-muted-foreground">
                        {detalle}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
