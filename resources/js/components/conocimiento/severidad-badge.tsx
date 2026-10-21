import { Badge } from '@/components/ui/badge';
import type { Severidad } from '@/types/conocimiento';

const ESTILOS: Record<Severidad, { texto: string; clase: string }> = {
    critica: {
        texto: 'Crítica',
        clase: 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
    },
    advertencia: {
        texto: 'Advertencia',
        clase: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
    },
    informativa: {
        texto: 'Informativa',
        clase: 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200',
    },
};

export function SeveridadBadge({ severidad }: { severidad: Severidad }) {
    const estilo = ESTILOS[severidad];

    return (
        <Badge variant="outline" className={estilo.clase}>
            {estilo.texto}
        </Badge>
    );
}
