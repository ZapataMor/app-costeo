import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label,
    className,
}: {
    items: NavItem[];
    label?: string;
    className?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className={`px-2 py-0 ${className ?? ''}`}>
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
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
        </SidebarGroup>
    );
}
