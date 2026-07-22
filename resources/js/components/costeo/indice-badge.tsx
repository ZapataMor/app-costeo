import { Badge } from '@/components/ui/badge';

/**
 * Índice ajustado por case-mix: costo de la cirugía ÷ promedio de su mismo
 * procedimiento. Se colorea solo fuera de la banda ±10 %, porque por debajo
 * de esa diferencia el dato no distingue desempeño de ruido.
 */
export function IndiceBadge({
    valor,
    sinDato = '—',
}: {
    valor: number | null;
    sinDato?: string;
}) {
    if (valor === null) {
        return <span className="text-muted-foreground">{sinDato}</span>;
    }

    const clase =
        valor > 1.1
            ? 'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
            : valor < 0.9
              ? 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
              : '';

    return (
        <Badge variant="outline" className={`tabular-nums ${clase}`}>
            {valor.toFixed(2)}×
        </Badge>
    );
}
