import { SidebarTrigger } from '@/components/ui/sidebar';

/**
 * Barra superior del panel, solo en móvil: ahí la sidebar es un panel oculto
 * y hace falta un botón para abrirla. En escritorio no hay barra (el contenido
 * sube): la sidebar se recoge con el ícono de la app y el cambio de tema está
 * en su pie, junto al avatar.
 */
export function AppSidebarHeader() {
    return (
        <header className="flex h-[70px] shrink-0 items-center px-4 md:hidden">
            <SidebarTrigger className="size-9 shrink-0 rounded-[9px] border border-[#5B687C]/30 text-[#5B687C] shadow-none hover:border-[#5B687C]/50 hover:bg-transparent hover:text-[#161B2F] dark:border-[#A6AAB2]/25 dark:text-[#A6AAB2] dark:hover:text-[#F3F0ED]" />
        </header>
    );
}
