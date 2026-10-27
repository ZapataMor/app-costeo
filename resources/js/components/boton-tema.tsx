import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * Cambia entre modo claro y oscuro. Vive en el pie de la sidebar, junto al
 * avatar; la sidebar es oscura en ambos temas, así que sus colores no cambian.
 */
export function BotonTema() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const dark = resolvedAppearance === 'dark';
    const etiqueta = dark ? 'Activar modo claro' : 'Activar modo oscuro';

    return (
        <button
            type="button"
            aria-label={etiqueta}
            title={etiqueta}
            className="flex size-[34px] shrink-0 cursor-pointer items-center justify-center rounded-[9px] border border-white/[.12] text-[#A6AAB2] transition-colors hover:border-white/25 hover:text-[#F3F0ED] focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none"
            onClick={(event) => {
                const caja = event.currentTarget.getBoundingClientRect();

                updateAppearance(dark ? 'light' : 'dark', {
                    x: caja.left + caja.width / 2,
                    y: caja.top + caja.height / 2,
                });
            }}
        >
            {dark ? (
                <Sun className="size-[17px]" />
            ) : (
                <Moon className="size-[17px]" />
            )}
        </button>
    );
}
