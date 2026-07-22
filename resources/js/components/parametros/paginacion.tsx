import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { LinkPaginacion } from '@/types/parametros';

/**
 * Los `links` de Laravel llegan como [anterior, ...páginas, siguiente], y las
 * etiquetas de los extremos son claves de traducción (`pagination.previous`).
 * Se descartan: los extremos se rotulan aquí y solo se pintan cuando de verdad
 * hay a dónde ir.
 */
function partir(links: LinkPaginacion[]): {
    anterior: LinkPaginacion | undefined;
    paginas: LinkPaginacion[];
    siguiente: LinkPaginacion | undefined;
} {
    if (links.length < 3) {
        return { anterior: undefined, paginas: [], siguiente: undefined };
    }

    return {
        anterior: links[0],
        paginas: links.slice(1, -1),
        siguiente: links[links.length - 1],
    };
}

export function Paginacion({
    links,
    total,
    from,
    to,
}: {
    links: LinkPaginacion[];
    total: number;
    from: number | null;
    to: number | null;
}) {
    if (total === 0) {
        return null;
    }

    const { anterior, paginas, siguiente } = partir(links);

    // Con una sola página los controles no llevan a ninguna parte: sobra
    // toda la fila de botones y basta con el conteo.
    const unaSolaPagina = paginas.length <= 1;

    return (
        <div className="flex items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
                Mostrando {from ?? 0}–{to ?? 0} de {total}
            </p>
            {!unaSolaPagina && (
                <div className="flex flex-wrap items-center gap-1">
                    {anterior?.url && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={anterior.url} preserveScroll>
                                <ChevronLeft className="size-4" />
                                Anterior
                            </Link>
                        </Button>
                    )}

                    {paginas.map((pagina, i) =>
                        pagina.url ? (
                            <Button
                                key={i}
                                asChild
                                variant={pagina.active ? 'default' : 'outline'}
                                size="sm"
                                aria-current={
                                    pagina.active ? 'page' : undefined
                                }
                            >
                                <Link href={pagina.url} preserveScroll>
                                    {pagina.label}
                                </Link>
                            </Button>
                        ) : (
                            // El «…» que Laravel intercala cuando hay muchas
                            // páginas: es texto, no un destino.
                            <span
                                key={i}
                                className="px-2 text-sm text-muted-foreground"
                            >
                                {pagina.label}
                            </span>
                        ),
                    )}

                    {siguiente?.url && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={siguiente.url} preserveScroll>
                                Siguiente
                                <ChevronRight className="size-4" />
                            </Link>
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
