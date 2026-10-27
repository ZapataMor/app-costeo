import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * Barra superior del panel: solo el cambio de tema. El título de la página ya
 * está en la propia página, el hospital activo se elige en la sidebar y la
 * sidebar se recoge con el ícono de la app. En móvil la sidebar es un panel
 * oculto, así que ahí sí hace falta el botón para abrirla.
 */
export function AppSidebarHeader() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const dark = resolvedAppearance === 'dark';

    return (
        <header className="flex h-[70px] shrink-0 items-center gap-3 px-4 md:pr-6 md:pl-[34px]">
            <SidebarTrigger className="size-9 shrink-0 rounded-[9px] border border-[#5B687C]/30 text-[#5B687C] shadow-none hover:border-[#5B687C]/50 hover:bg-transparent hover:text-[#161B2F] md:hidden dark:border-[#A6AAB2]/25 dark:text-[#A6AAB2] dark:hover:text-[#F3F0ED]" />
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="ml-auto size-9 rounded-[9px] border-[#5B687C]/30 bg-transparent text-[#5B687C] shadow-none hover:border-[#5B687C]/50 hover:bg-transparent hover:text-[#161B2F] dark:border-[#A6AAB2]/25 dark:text-[#A6AAB2] dark:hover:text-[#F3F0ED]"
                aria-label={dark ? 'Activar modo claro' : 'Activar modo oscuro'}
                title={dark ? 'Activar modo claro' : 'Activar modo oscuro'}
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
            </Button>
        </header>
    );
}
