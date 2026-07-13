import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ChartColumnBig,
    LayoutGrid,
    SlidersHorizontal,
    Stethoscope,
    Syringe,
    TrendingUp,
    Waves,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { HospitalSwitcher } from '@/components/hospital-switcher';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const generalNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const mainNavItems: NavItem[] = [
    {
        title: 'Cirugías',
        href: '/cirugias',
        icon: Syringe,
    },
    {
        title: 'Costeo quirúrgico',
        href: '/costeo',
        icon: Stethoscope,
    },
    {
        title: 'Costo por componente',
        href: '/costeo/componentes',
        icon: ChartColumnBig,
    },
    {
        title: 'Outliers de costo',
        href: '/costeo/outliers',
        icon: AlertTriangle,
    },
    {
        title: 'Rentabilidad',
        href: '/costeo/rentabilidad',
        icon: TrendingUp,
    },
    {
        title: 'Variabilidad',
        href: '/costeo/variabilidad',
        icon: Waves,
    },
];

const parametrosNavItems: NavItem[] = [
    {
        title: 'Parámetros',
        href: '/parametros',
        icon: SlidersHorizontal,
    },
];

export function AppSidebar() {
    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="border-0 bg-[#17181F] p-0"
        >
            <SidebarHeader className="gap-4 px-4 pt-5 pb-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto p-1 hover:bg-transparent data-[active=true]:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <HospitalSwitcher />
            </SidebarHeader>

            <SidebarContent className="px-2">
                <NavMain items={generalNavItems} label="General" />
                <NavMain items={parametrosNavItems} className="my-[14px]" />
                <NavMain items={mainNavItems} label="Costeo" />
            </SidebarContent>

            <SidebarFooter className="mx-4 mb-4 border-t border-white/[.09] px-0 pt-3 pb-0">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
