import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { LinkPaginacion } from '@/types/parametros';

export function Paginacion({ links, total, from, to }: { links: LinkPaginacion[]; total: number; from: number | null; to: number | null }) {
    if (total === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
                Mostrando {from ?? 0}–{to ?? 0} de {total}
            </p>
            <div className="flex flex-wrap gap-1">
                {links.map((link, i) =>
                    link.url ? (
                        <Button key={i} asChild variant={link.active ? 'default' : 'outline'} size="sm">
                            <Link href={link.url} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                        </Button>
                    ) : (
                        <Button key={i} variant="outline" size="sm" disabled dangerouslySetInnerHTML={{ __html: link.label }} />
                    ),
                )}
            </div>
        </div>
    );
}
