import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

export function EncabezadoListado({
    titulo,
    descripcion,
    hrefNuevo,
    textoNuevo,
}: {
    titulo: string;
    descripcion: string;
    hrefNuevo: string;
    textoNuevo: string;
}) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <Heading title={titulo} description={descripcion} />
            <Button asChild>
                <Link href={hrefNuevo} prefetch>
                    <Plus className="size-4" />
                    {textoNuevo}
                </Link>
            </Button>
        </div>
    );
}
