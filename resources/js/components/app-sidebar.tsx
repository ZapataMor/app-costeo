import { Link, usePage } from '@inertiajs/react';
import {
    History,
    LayoutGrid,
    SlidersHorizontal,
    Stethoscope,
    Syringe,
    UsersRound,
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

const historialNavItems: NavItem[] = [
    {
        title: 'Historial',
        href: '/historial',
        icon: History,
    },
];

// Módulo de registro de procedimientos (antes «Cirugías»): único módulo
// visible para el digitador.
const registroNavItems: NavItem[] = [
    {
        title: 'Registro de procedimientos',
        href: '/cirugias',
        icon: Syringe,
    },
];

const costeoNavItems: NavItem[] = [
    {
        title: 'Costeo quirúrgico',
        href: '/costeo',
        icon: Stethoscope,
    },
];

const parametrosNavItems: NavItem[] = [
    {
        title: 'Parámetros',
        href: '/parametros',
        icon: SlidersHorizontal,
    },
];

const digitadoresNavItems: NavItem[] = [
    {
        title: 'Digitadores',
        href: '/digitadores',
        icon: UsersRound,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const role = auth.user?.role;
    const esDigitador = role === 'digitador';
    const esAdminHospital = role === 'admin_hospital';

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
                            <Link href={esDigitador ? '/cirugias' : dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <HospitalSwitcher />
            </SidebarHeader>

            <SidebarContent className="px-2">
                {esDigitador ? (
                    // El digitador solo registra procedimientos.
                    <NavMain items={registroNavItems} label="Registro" />
                ) : (
                    <>
                        <NavMain items={generalNavItems} label="General" />
                        <NavMain
                            items={parametrosNavItems}
                            className="my-[14px]"
                        />
                        <NavMain
                            items={[...registroNavItems, ...costeoNavItems]}
                            label="Costeo"
                        />
                        {esAdminHospital && (
                            <NavMain
                                items={digitadoresNavItems}
                                label="Administración"
                                className="mt-[14px]"
                            />
                        )}
                        <NavMain
                            items={historialNavItems}
                            className="mt-[14px]"
                        />
                    </>
                )}
            </SidebarContent>

            <SidebarFooter className="mx-4 mb-4 border-t border-white/[.09] px-0 pt-3 pb-0">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
