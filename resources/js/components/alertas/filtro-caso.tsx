import { Link } from '@inertiajs/react';
import { ArrowLeft, X } from 'lucide-react';
import { NombreDato } from '@/components/nombre-dato';
import { mesLargo } from '@/lib/formato';

export type CasoAlerta = {
    procedimiento: { id: number; codigo_cups: string; nombre: string } | null;
    sin_procedimiento?: boolean;
    mes: string | null;
};

/**
 * Franja que dice qué caso de la bandeja única se está viendo, con el
 * camino de vuelta. Sin ella, al llegar filtrado desde /alertas no había
 * forma de saber por qué la lista era tan corta.
 */
export function FiltroCaso({
    caso,
    hrefSinFiltro,
}: {
    caso: CasoAlerta;
    hrefSinFiltro: string;
}) {
    const hayCaso =
        caso.procedimiento !== null ||
        caso.sin_procedimiento === true ||
        caso.mes !== null;

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <Link
                href="/alertas"
                className="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft className="size-4" />
                Bandeja de alertas
            </Link>
            {hayCaso && (
                <span className="inline-flex flex-wrap items-center gap-2 rounded-full border px-3 py-1">
                    <span className="text-muted-foreground">Caso:</span>
                    <span className="font-medium">
                        {caso.procedimiento ? (
                            <NombreDato texto={caso.procedimiento.nombre} />
                        ) : caso.sin_procedimiento ? (
                            'Salas y hospital'
                        ) : (
                            'Todos los procedimientos'
                        )}
                        {caso.mes && ` · ${mesLargo(caso.mes)}`}
                    </span>
                    <Link
                        href={hrefSinFiltro}
                        className="inline-flex items-center text-muted-foreground hover:text-foreground"
                        aria-label="Quitar el filtro del caso"
                        title="Quitar el filtro del caso"
                    >
                        <X className="size-4" />
                    </Link>
                </span>
            )}
        </div>
    );
}
