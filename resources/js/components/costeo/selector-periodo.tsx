import { router, usePage } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import { useState } from 'react';
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

type Consulta = Record<string, string>;

/**
 * Atajos. «Últimos 3 meses» no manda fechas: es el periodo por defecto del
 * servidor, anclado a la última cirugía realizada (no a hoy), y así un
 * hospital que carga con retraso no ve la pantalla vacía.
 */
const atajos: { clave: string; etiqueta: string; consulta: () => Consulta }[] =
    [
        { clave: 'defecto', etiqueta: 'Últimos 3 meses', consulta: () => ({}) },
        {
            clave: 'mes',
            etiqueta: 'Este mes',
            consulta: () => ({
                desde: hoy().slice(0, 7) + '-01',
                hasta: hoy(),
            }),
        },
        {
            clave: 'anio',
            etiqueta: '12 meses',
            consulta: () => ({ desde: haceMeses(12), hasta: hoy() }),
        },
        {
            clave: 'todo',
            etiqueta: 'Toda la historia',
            consulta: () => ({ todo: '1' }),
        },
    ];

/** Qué atajo describe la URL actual, para marcarlo como activo. */
function atajoActivo(urlActual: string): string | null {
    const consulta = new URLSearchParams(urlActual.split('?')[1] ?? '');

    if (consulta.get('todo')) {
        return 'todo';
    }

    if (!consulta.get('desde') && !consulta.get('hasta')) {
        return 'defecto';
    }

    for (const atajo of atajos.slice(1, 3)) {
        const esperado = atajo.consulta();

        if (
            consulta.get('desde') === esperado.desde &&
            consulta.get('hasta') === esperado.hasta
        ) {
            return atajo.clave;
        }
    }

    return null;
}

/**
 * Ventana temporal de los indicadores. Viaja por la URL, de modo que un panel
 * filtrado se puede compartir tal cual.
 *
 * El periodo por defecto ya no es «toda la historia» sino los últimos 3 meses
 * con cirugías: sin una ventana acotada ningún indicador tiene con qué
 * compararse. `conservar` mantiene otros filtros de la página al cambiarlo.
 */
export function SelectorPeriodo({
    url,
    periodo,
    etiqueta,
    conservar = {},
}: {
    url: string;
    periodo: Periodo;
    etiqueta: string;
    conservar?: Consulta;
}) {
    const [rango, setRango] = useState({
        desde: periodo.desde ?? '',
        hasta: periodo.hasta ?? '',
    });
    const activo = atajoActivo(usePage().url);

    const ir = (consulta: Consulta) => {
        router.get(
            url,
            Object.fromEntries(
                Object.entries({ ...conservar, ...consulta }).filter(
                    ([, v]) => v !== '',
                ),
            ),
            { preserveState: false, preserveScroll: true, replace: true },
        );
    };

    const aplicarRango = () => {
        if (
            rango.desde === (periodo.desde ?? '') &&
            rango.hasta === (periodo.hasta ?? '')
        ) {
            return;
        }

        ir(rango);
    };

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border bg-card px-3 py-2">
            <div className="flex min-w-0 items-center gap-2">
                <CalendarRange className="size-4 shrink-0 text-muted-foreground" />
                <span className="text-sm font-medium">{etiqueta}</span>
            </div>

            <div
                className="flex flex-wrap gap-1"
                role="group"
                aria-label="Periodos frecuentes"
            >
                {atajos.map((atajo) => (
                    <button
                        key={atajo.clave}
                        type="button"
                        aria-pressed={activo === atajo.clave}
                        onClick={() => ir(atajo.consulta())}
                        className={`rounded-md px-2.5 py-1 text-[13px] transition-colors ${
                            activo === atajo.clave
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        }`}
                    >
                        {atajo.etiqueta}
                    </button>
                ))}
            </div>

            <div className="flex w-full items-center gap-2 sm:ml-auto sm:w-auto">
                <Input
                    type="date"
                    aria-label="Desde"
                    className="h-8 min-w-0 flex-1 sm:w-[9.5rem] sm:flex-none"
                    value={rango.desde}
                    onChange={(e) =>
                        setRango({ ...rango, desde: e.target.value })
                    }
                    onBlur={aplicarRango}
                />
                <span className="text-sm text-muted-foreground">a</span>
                <Input
                    type="date"
                    aria-label="Hasta"
                    className="h-8 min-w-0 flex-1 sm:w-[9.5rem] sm:flex-none"
                    value={rango.hasta}
                    onChange={(e) =>
                        setRango({ ...rango, hasta: e.target.value })
                    }
                    onBlur={aplicarRango}
                />
            </div>
        </div>
    );
}

/**
 * «?desde=…&hasta=…» del periodo resuelto, para que un enlace a otra vista
 * (la ficha de un procedimiento, «ver todos») muestre las mismas fechas.
 */
export function consultaDePeriodo(periodo: Periodo): string {
    const consulta = new URLSearchParams(
        Object.entries(periodo).filter(
            (par): par is [string, string] => par[1] !== null,
        ),
    ).toString();

    return consulta ? `?${consulta}` : '';
}

/**
 * Parámetros de periodo tal como vienen en la URL (`desde`, `hasta`, `todo`),
 * para conservarlos al aplicar otro filtro sin fijar el periodo por defecto
 * como fechas explícitas.
 */
export function periodoDeLaUrl(urlActual: string): Record<string, string> {
    const consulta = new URLSearchParams(urlActual.split('?')[1] ?? '');
    const salida: Record<string, string> = {};

    for (const clave of ['desde', 'hasta', 'todo']) {
        const valor = consulta.get(clave);

        if (valor) {
            salida[clave] = valor;
        }
    }

    return salida;
}
