import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * Descarga de un listado en CSV arrastrando los filtros activos, para que lo
 * exportado coincida con lo que se está viendo en pantalla.
 *
 * Es una navegación normal del navegador, no de Inertia: la respuesta es un
 * archivo, no una página.
 */
export function BotonExportar({
    url,
    filtros = {},
    texto = 'Exportar CSV',
}: {
    url: string;
    filtros?: Record<string, string>;
    texto?: string;
}) {
    const parametros = new URLSearchParams(
        Object.entries(filtros).filter(([, v]) => v !== ''),
    ).toString();

    return (
        <Button asChild variant="outline">
            <a href={parametros === '' ? url : `${url}?${parametros}`}>
                <Download className="size-4" />
                {texto}
            </a>
        </Button>
    );
}
