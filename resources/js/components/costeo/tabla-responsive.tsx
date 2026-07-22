import type { ReactNode } from 'react';

/**
 * Tabla que desborda hacia dentro, no hacia fuera.
 *
 * Las tablas de los paneles vivían sueltas dentro de la tarjeta: en móvil
 * medían más que su contenedor y las últimas columnas quedaban recortadas,
 * sin scroll que permitiera alcanzarlas.
 */
export function TablaResponsive({ children }: { children: ReactNode }) {
    return (
        <div className="-mx-2 overflow-x-auto px-2">
            <table className="w-full min-w-[520px] text-sm">{children}</table>
        </div>
    );
}
