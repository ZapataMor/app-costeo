import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { NivelConfiabilidad } from '@/types/parametros';

const estilos: Record<NivelConfiabilidad, string> = {
    medido: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    estimado:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    supuesto: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

export function NivelConfiabilidadBadge({
    nivel,
}: {
    nivel: NivelConfiabilidad;
}) {
    return (
        <Badge
            variant="outline"
            className={cn('border-transparent capitalize', estilos[nivel])}
        >
            {nivel}
        </Badge>
    );
}
