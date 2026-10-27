import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * Indicador principal: el primer nivel de la jerarquía de cada tablero.
 *
 * El título iba en 10 px, mayúsculas y #5B687C (2,9:1 sobre la tarjeta
 * oscura); ahora es una frase normal y legible, y la cifra pesa más que todo
 * lo demás de la tarjeta.
 */

export function KpiCard({
    titulo,
    valor,
    detalle,
}: {
    titulo: string;
    valor: string;
    detalle?: string;
}) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="px-5 pt-5 pb-2">
                <CardTitle className="font-sans text-[13px] leading-snug font-medium text-[var(--etiqueta)]">
                    {titulo}
                </CardTitle>
            </CardHeader>
            <CardContent className="px-5 pb-5">
                <div className="font-sans text-[26px] leading-tight font-semibold tabular-nums">
                    {valor}
                </div>
                {detalle && (
                    <p className="mt-1 text-[12.5px] text-muted-foreground">
                        {detalle}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
