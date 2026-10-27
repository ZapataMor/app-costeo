import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label,
    className,
    plegable = false,
}: {
    items: NavItem[];
    label?: string;
    className?: string;
    // Los grupos de uso ocasional (Conocimiento, Administración) arrancan
    // cerrados para que el menú no se vea saturado; se abren solos si la
    // página actual es de ese grupo.
    plegable?: boolean;
}) {
    const { currentUrl, isCurrentUrl } = useCurrentUrl();
    const { state, isMobile } = useSidebar();
    // Cuenta también las subpáginas (/parametros/insumos abre Administración).
    const contieneActual = items.some((item) => {
        const href = toUrl(item.href);

        return currentUrl === href || currentUrl.startsWith(`${href}/`);
    });
    const [abierto, setAbierto] = useState(contieneActual);
    // El layout es persistente: al navegar a una página del grupo, se abre.
    const [contieneAnterior, setContieneAnterior] = useState(contieneActual);

    if (contieneActual !== contieneAnterior) {
        setContieneAnterior(contieneActual);

        if (contieneActual) {
            setAbierto(true);
        }
    }

    const menu = (
        <SidebarMenu>
            {items.map((item) => (
                <SidebarMenuItem key={item.title}>
                    <SidebarMenuButton
                        asChild
                        isActive={isCurrentUrl(item.href)}
                        tooltip={{ children: item.title }}
                        className="h-10 rounded-[9px] border border-transparent px-3 text-[13.5px] text-sidebar-foreground/60 hover:border-white/10 hover:bg-white/[.05] hover:text-[#D4CDCB] data-[active=true]:border-white/[.18] data-[active=true]:bg-white/[.05] data-[active=true]:text-[#D4CDCB] [&>svg]:size-[18px]"
                    >
                        <Link href={item.href} prefetch>
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                            {/* Colapsado el sidebar solo se ve el ícono:
                                el contador se oculta con él para no dejar
                                un número flotando sin a qué referirse. */}
                            {item.badge ? (
                                <span className="ml-auto rounded-full bg-[#9E3B3B] px-1.5 py-0.5 text-[11px] leading-none font-medium text-white group-data-[collapsible=icon]:hidden">
                                    {item.badge}
                                </span>
                            ) : null}
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            ))}
        </SidebarMenu>
    );

    // Recogida a íconos no hay etiqueta que pulsar: se muestran todos.
    if (!plegable || !label || (state === 'collapsed' && !isMobile)) {
        return (
            <SidebarGroup className={`px-2 py-0 ${className ?? ''}`}>
                {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
                {menu}
            </SidebarGroup>
        );
    }

    // Cerrado, el grupo suma los contadores de sus módulos para que nada
    // pendiente quede escondido.
    const pendientes = items.reduce(
        (suma, item) => suma + (item.badge ?? 0),
        0,
    );

    return (
        <Collapsible open={abierto} onOpenChange={setAbierto} asChild>
            <SidebarGroup className={`px-2 py-0 ${className ?? ''}`}>
                <SidebarGroupLabel asChild>
                    <CollapsibleTrigger className="group/plegable w-full cursor-pointer gap-2 hover:text-sidebar-foreground">
                        {label}
                        {!abierto && pendientes > 0 ? (
                            <span className="rounded-full bg-[#9E3B3B] px-1.5 py-0.5 text-[10px] leading-none font-medium text-white">
                                {pendientes}
                            </span>
                        ) : null}
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/plegable:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>{menu}</CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
