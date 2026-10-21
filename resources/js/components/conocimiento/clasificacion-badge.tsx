import { Badge } from '@/components/ui/badge';
import type { ClasificacionDesviacion } from '@/types/cirugias';

const ESTILOS: Record<
    ClasificacionDesviacion,
    { texto: string; clase: string }
> = {
    subejecucion: {
        texto: 'Subejecución',
        clase: 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200',
    },
    en_rango: {
        texto: 'En rango',
        clase: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    },
    sobrecosto_leve: {
        texto: 'Sobrecosto leve',
        clase: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
    },
    sobrecosto_alto: {
        texto: 'Sobrecosto alto',
        clase: 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
    },
};

/**
 * Banda de la desviación real vs. estándar (pipeline de la tesis:
 * −5 % / 5 % / 20 %, configurable por hospital).
 */
export function ClasificacionBadge({
    clasificacion,
}: {
    clasificacion: ClasificacionDesviacion | null;
}) {
    if (clasificacion === null) {
        return (
            <Badge variant="outline" className="text-muted-foreground">
                Sin estándar
            </Badge>
        );
    }

    const estilo = ESTILOS[clasificacion];

    return (
        <Badge variant="outline" className={estilo.clase}>
            {estilo.texto}
        </Badge>
    );
}
