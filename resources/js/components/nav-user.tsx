import { usePage } from '@inertiajs/react';
import { BotonTema } from '@/components/boton-tema';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSidebar } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useIsMobile } from '@/hooks/use-mobile';

/**
 * Pie de la sidebar: el avatar con las iniciales abre el menú del usuario
 * (su nombre y correo se ven dentro del menú) y a su lado va el cambio de
 * tema. Recogida, los dos se apilan.
 */
export function NavUser() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const getInitials = useInitials();

    if (!auth.user) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-2 px-1 group-data-[collapsible=icon]:flex-col group-data-[collapsible=icon]:px-0">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label={`Menú de ${auth.user.name}`}
                        title={auth.user.name}
                        className="shrink-0 cursor-pointer rounded-full transition-opacity hover:opacity-85 focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none data-[state=open]:ring-2 data-[state=open]:ring-white/25"
                        data-test="sidebar-menu-button"
                    >
                        <Avatar className="h-[34px] w-[34px] overflow-hidden rounded-full">
                            <AvatarImage
                                src={auth.user.avatar}
                                alt={auth.user.name}
                            />
                            <AvatarFallback className="rounded-full bg-[#5B687C] text-xs font-semibold text-[#D4CDCB]">
                                {getInitials(auth.user.name)}
                            </AvatarFallback>
                        </Avatar>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    className="min-w-56 rounded-lg"
                    align="start"
                    side={!isMobile && state === 'collapsed' ? 'right' : 'top'}
                >
                    <UserMenuContent user={auth.user} />
                </DropdownMenuContent>
            </DropdownMenu>
            <BotonTema />
        </div>
    );
}
