import { Link } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

export function EncabezadoListado({
    titulo,
    descripcion,
    hrefNuevo,
    textoNuevo,
    hrefAtras,
    accion,
}: {
    titulo: string;
    descripcion: string;
    hrefNuevo?: string;
    textoNuevo?: string;
    hrefAtras?: string;
    accion?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex items-start gap-2">
                {hrefAtras && (
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        aria-label="Volver"
                    >
                        <Link href={hrefAtras} prefetch>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                )}
                <Heading title={titulo} description={descripcion} />
            </div>
            {accion ??
                (hrefNuevo && (
                    <Button asChild>
                        <Link href={hrefNuevo} prefetch>
                            <Plus className="size-4" />
                            {textoNuevo}
                        </Link>
                    </Button>
                ))}
        </div>
    );
}
