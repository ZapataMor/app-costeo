import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {titulo}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold tabular-nums">{valor}</div>
                {detalle && (
                    <p className="mt-1 text-xs text-muted-foreground">{detalle}</p>
                )}
            </CardContent>
        </Card>
    );
}
