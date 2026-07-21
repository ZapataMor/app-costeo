import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type OpcionBuscador = {
    valor: string;
    etiqueta: string;
    /** Texto secundario en la lista (código, rol, unidad…). */
    detalle?: string;
    /** Términos extra por los que debe encontrarse (código CUPS, documento…). */
    busqueda?: string;
};

/**
 * Selector con búsqueda por teclado. Reemplaza al `Select` nativo en los
 * catálogos que crecen —insumos, procedimientos, pacientes, personal—, donde
 * desplegar cientos de opciones y bajar con la rueda era inviable.
 *
 * La búsqueda es local (el catálogo ya viaja completo en la página) y cubre
 * etiqueta, detalle y los términos extra de `busqueda`.
 */
export function BuscadorSelect({
    opciones,
    valor,
    onCambio,
    placeholder = 'Seleccione',
    placeholderBusqueda = 'Buscar…',
    sinResultados = 'Sin coincidencias.',
    className,
    id,
}: {
    opciones: OpcionBuscador[];
    valor: string;
    onCambio: (valor: string) => void;
    placeholder?: string;
    placeholderBusqueda?: string;
    sinResultados?: string;
    className?: string;
    id?: string;
}) {
    const [abierto, setAbierto] = useState(false);

    const seleccionada = opciones.find((o) => o.valor === valor);

    return (
        <Popover open={abierto} onOpenChange={setAbierto}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={abierto}
                    className={cn(
                        'w-full justify-between font-normal',
                        seleccionada === undefined && 'text-muted-foreground',
                        className,
                    )}
                >
                    <span className="truncate">
                        {seleccionada?.etiqueta ?? placeholder}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-(--radix-popover-trigger-width) p-0"
                align="start"
            >
                <Command
                    filter={(value, search) =>
                        value.toLowerCase().includes(search.toLowerCase())
                            ? 1
                            : 0
                    }
                >
                    <CommandInput placeholder={placeholderBusqueda} />
                    <CommandList>
                        <CommandEmpty>{sinResultados}</CommandEmpty>
                        <CommandGroup>
                            {opciones.map((opcion) => (
                                <CommandItem
                                    key={opcion.valor}
                                    // cmdk filtra por este value, no por el
                                    // contenido renderizado.
                                    value={[
                                        opcion.etiqueta,
                                        opcion.detalle,
                                        opcion.busqueda,
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                    onSelect={() => {
                                        onCambio(opcion.valor);
                                        setAbierto(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            opcion.valor === valor
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        {opcion.etiqueta}
                                    </span>
                                    {opcion.detalle && (
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {opcion.detalle}
                                        </span>
                                    )}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
