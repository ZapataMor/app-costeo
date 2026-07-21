import { router } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type Periodo = { desde: string | null; hasta: string | null };

/** Fecha de hoy en Y-m-d, en la zona horaria del navegador. */
function hoy(): string {
    return new Date().toLocaleDateString('en-CA');
}

function haceMeses(meses: number): string {
    const fecha = new Date();
    fecha.setMonth(fecha.getMonth() - meses);

    return fecha.toLocaleDateString('en-CA');
}

const atajos = [
    { etiqueta: 'Este mes', desde: () => hoy().slice(0, 7) + '-01', hasta: hoy },
    { etiqueta: '3 meses', desde: () => haceMeses(3), hasta: hoy },
    { etiqueta: '12 meses', desde: () => haceMeses(12), hasta: hoy },
];

/**
 * Ventana temporal de los indicadores. Antes todo se calculaba sobre la
 * historia completa del hospital, así que no se podía responder «¿cómo vamos
 * este trimestre?» ni comparar periodos.
 *
 * Viaja por la URL, de modo que un panel filtrado se puede compartir tal cual.
 */
export function SelectorPeriodo({
    url,
    periodo,
    etiqueta,
}: {
    url: string;
    periodo: Periodo;
    etiqueta: string;
}) {
    const [rango, setRango] = useState({
        desde: periodo.desde ?? '',
        hasta: periodo.hasta ?? '',
    });

    const aplicar = (siguiente: { desde: string; hasta: string }) => {
        setRango(siguiente);

        router.get(
            url,
            Object.fromEntries(
                Object.entries(siguiente).filter(([, v]) => v !== ''),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const activo = rango.desde !== '' || rango.hasta !== '';

    return (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-card px-3 py-2">
            <CalendarRange className="size-4 shrink-0 text-muted-foreground" />
            <span className="mr-1 text-sm font-medium">{etiqueta}</span>

            <Input
                type="date"
                aria-label="Desde"
                className="w-40"
                value={rango.desde}
                onChange={(e) => setRango({ ...rango, desde: e.target.value })}
                onBlur={() => aplicar(rango)}
            />
            <span className="text-sm text-muted-foreground">a</span>
            <Input
                type="date"
                aria-label="Hasta"
                className="w-40"
                value={rango.hasta}
                onChange={(e) => setRango({ ...rango, hasta: e.target.value })}
                onBlur={() => aplicar(rango)}
            />

            {atajos.map((atajo) => (
                <Button
                    key={atajo.etiqueta}
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        aplicar({ desde: atajo.desde(), hasta: atajo.hasta() })
                    }
                >
                    {atajo.etiqueta}
                </Button>
            ))}

            {activo && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => aplicar({ desde: '', hasta: '' })}
                >
                    Toda la historia
                </Button>
            )}
        </div>
    );
}
