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
        <Card className="gap-0 py-0">
            <CardHeader className="px-5 pt-5 pb-2">
                <CardTitle className="font-sans text-[10px] font-semibold tracking-[1px] text-[#5B687C] uppercase">
                    {titulo}
                </CardTitle>
            </CardHeader>
            <CardContent className="px-5 pb-5">
                <div className="font-sans text-[25px] leading-tight tabular-nums">
                    {valor}
                </div>
                {detalle && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {detalle}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
