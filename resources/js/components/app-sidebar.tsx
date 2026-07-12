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

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
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
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <HospitalSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label="Costeo" />
                <NavMain items={parametrosNavItems} label="Parámetros (Capa 1)" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
