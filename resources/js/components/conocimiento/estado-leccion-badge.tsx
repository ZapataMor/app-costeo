import { Badge } from '@/components/ui/badge';
import type { LeccionResumen } from '@/types/conocimiento';

const CLASES: Record<LeccionResumen['estado'], string> = {
    borrador:
        'border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
    en_revision:
        'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
    publicada:
        'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    archivada:
        'border-zinc-300 bg-zinc-100 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300',
};

export function EstadoLeccionBadge({
    estado,
    etiqueta,
}: {
    estado: LeccionResumen['estado'];
    etiqueta: string;
}) {
    return (
        <Badge variant="outline" className={CLASES[estado]}>
            {etiqueta}
        </Badge>
    );
}
