import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Vistas del módulo de costeo, en el orden en que se recorren: del resumen
 * al detalle. Eran siete; «Composición del costo» pasó a ser una vista de la
 * tabla de procedimientos y «Costos fuera de lo normal» se unió a
 * «Variabilidad», porque las tres repetían la misma tabla por procedimiento.
 */
const VISTAS = [
    { href: '/costeo', titulo: 'Resumen' },
    { href: '/costeo/procedimientos', titulo: 'Procedimientos' },
    { href: '/costeo/rentabilidad', titulo: 'Rentabilidad' },
    { href: '/costeo/variabilidad', titulo: 'Variabilidad y atípicos' },
    { href: '/costeo/personal', titulo: 'Personal' },
] as const;

/**
 * Pestañas del módulo. Conservan el periodo elegido (`desde`/`hasta`/`todo`):
 * pasar de Resumen a Rentabilidad no debe cambiar la ventana de fechas.
 */
export function NavegacionCosteo() {
    const { url } = usePage();
    const [ruta, consulta = ''] = url.split('?');
    const periodo = new URLSearchParams(consulta);
    const conservar = new URLSearchParams();

    for (const clave of ['desde', 'hasta', 'todo']) {
        const valor = periodo.get(clave);

        if (valor) {
            conservar.set(clave, valor);
        }
    }

    const sufijo = conservar.toString() ? `?${conservar.toString()}` : '';

    return (
        <nav
            aria-label="Vistas de costeo"
            className="-mx-1 flex gap-1 overflow-x-auto overflow-y-hidden px-1 shadow-[inset_0_-1px_0_var(--border)]"
        >
            {VISTAS.map((vista) => {
                const activa =
                    vista.href === '/costeo'
                        ? ruta === '/costeo'
                        : ruta.startsWith(vista.href);

                return (
                    <Link
                        key={vista.href}
                        href={`${vista.href}${sufijo}`}
                        prefetch
                        aria-current={activa ? 'page' : undefined}
                        className={`border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors ${
                            activa
                                ? 'border-foreground font-semibold text-foreground'
                                : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground'
                        }`}
                    >
                        {vista.titulo}
                    </Link>
                );
            })}
        </nav>
    );
}

/**
 * Encabezado de las vistas de análisis.
 *
 * Cuatro de los seis paneles entraban directamente con el selector de fechas,
 * sin título: el usuario llegaba desde una tarjeta y no tenía confirmación de
 * dónde había caído. Ahora todas abren igual que `/costeo`, con las pestañas
 * del módulo debajo (`navegacion={false}` para usarlo fuera de costeo).
 */
export function EncabezadoCosteo({
    titulo,
    descripcion,
    accion,
    navegacion = true,
}: {
    titulo: string;
    descripcion?: string;
    accion?: ReactNode;
    navegacion?: boolean;
}) {
    return (
        <div className="mb-1 flex flex-col gap-3">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="font-serif text-[32px] leading-tight font-semibold text-[#161B2F] dark:text-[#F3F0ED]">
                        {titulo}
                    </h1>
                    {descripcion && (
                        <p className="mt-1 max-w-[70ch] text-[13.5px] text-[#74787E] dark:text-[#A6AAB2]">
                            {descripcion}
                        </p>
                    )}
                </div>
                {accion}
            </div>
            {navegacion && <NavegacionCosteo />}
        </div>
    );
}
