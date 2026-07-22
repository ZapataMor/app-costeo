import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type DefinicionFiltro = {
    clave: string;
    etiqueta: string;
    /** Opciones del desplegable; el valor vacío significa «todos». */
    opciones: { valor: string; etiqueta: string }[];
};

/**
 * Barra de búsqueda y filtros de un listado. Envía por GET a la misma URL,
 * así los filtros quedan en el enlace (compartible y navegable con atrás) y
 * la paginación los conserva vía `withQueryString()`.
 */
export function FiltrosListado({
    url,
    valores,
    placeholderBusqueda = 'Buscar…',
    filtros = [],
    extra,
}: {
    url: string;
    valores: Record<string, string>;
    placeholderBusqueda?: string;
    filtros?: DefinicionFiltro[];
    extra?: ReactNode;
}) {
    const [estado, setEstado] = useState<Record<string, string>>(valores);

    const aplicar = (siguiente: Record<string, string>) => {
        setEstado(siguiente);

        // Los vacíos no viajan: mantiene la URL legible.
        const parametros = Object.fromEntries(
            Object.entries(siguiente).filter(([, v]) => v !== ''),
        );

        router.get(url, parametros, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const hayFiltrosActivos = Object.values(estado).some((v) => v !== '');

    return (
        <div className="flex flex-wrap items-end gap-2">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    aplicar(estado);
                }}
                className="flex gap-2"
            >
                <Input
                    value={estado.q ?? ''}
                    onChange={(e) =>
                        setEstado({ ...estado, q: e.target.value })
                    }
                    placeholder={placeholderBusqueda}
                    aria-label="Buscar"
                    className="w-64"
                />
                <Button type="submit" variant="outline">
                    <Search className="size-4" />
                    Buscar
                </Button>
            </form>

            {filtros.map((filtro) => (
                <Select
                    key={filtro.clave}
                    // El valor vacío no es válido en Radix: se usa un centinela.
                    value={
                        estado[filtro.clave] === ''
                            ? '__todos'
                            : (estado[filtro.clave] ?? '__todos')
                    }
                    onValueChange={(v) =>
                        aplicar({
                            ...estado,
                            [filtro.clave]: v === '__todos' ? '' : v,
                        })
                    }
                >
                    <SelectTrigger
                        className="w-auto min-w-40"
                        aria-label={filtro.etiqueta}
                    >
                        <SelectValue placeholder={filtro.etiqueta} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__todos">
                            {filtro.etiqueta}: todos
                        </SelectItem>
                        {filtro.opciones.map((opcion) => (
                            <SelectItem
                                key={opcion.valor}
                                value={opcion.valor}
                                className="capitalize"
                            >
                                {opcion.etiqueta}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ))}

            {extra}

            {hayFiltrosActivos && (
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() =>
                        aplicar(
                            Object.fromEntries(
                                Object.keys(estado).map((k) => [k, '']),
                            ),
                        )
                    }
                >
                    <X className="size-4" />
                    Limpiar
                </Button>
            )}
        </div>
    );
}
