import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Brain,
    Building2,
    History,
    Lightbulb,
    MessagesSquare,
    ShieldAlert,
    Sparkles,
    UserRoundCheck,
    LayoutGrid,
    SlidersHorizontal,
    Stethoscope,
    Syringe,
    Users,
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

// Captura de datos (Capa 2). El digitador solo ve el registro de
// procedimientos; da de alta pacientes desde el propio formulario.
// El menú decía «Registro de procedimientos» y la página, «Procedimientos»:
// el mismo módulo con dos nombres, y ese nombre repetido en Parámetros y en
// Costeo. Aquí y en la página se llama igual.
const registroNavItems: NavItem[] = [
    {
        title: 'Cirugías realizadas',
        href: '/cirugias',
        icon: Syringe,
    },
];

const pacientesNavItems: NavItem[] = [
    {
        title: 'Pacientes',
        href: '/pacientes',
        icon: Users,
    },
];

// El contador de sobrecostos sin revisar viaja en las props compartidas, así
// que se ve desde cualquier pantalla: sin él, la alerta solo existiría para
// quien ya hubiera decidido entrar a buscarla.
const costeoNavItems = (pendientes: number): NavItem[] => [
    {
        title: 'Costeo quirúrgico',
        href: '/costeo',
        icon: Stethoscope,
        badge: pendientes > 0 ? pendientes : undefined,
    },
];

const parametrosNavItems: NavItem[] = [
    {
        title: 'Parámetros',
        href: '/parametros',
        icon: SlidersHorizontal,
    },
];

// Alta de hospitales/centros médicos y de sus usuarios: es el módulo con el
// que el super_admin da de alta un tenant, así que solo él lo ve.
const institucionesNavItems: NavItem[] = [
    {
        title: 'Instituciones',
        href: '/instituciones',
        icon: Building2,
    },
];

const digitadoresNavItems: NavItem[] = [
    {
        title: 'Digitadores',
        href: '/digitadores',
        icon: UsersRound,
    },
];

// Capa 4 — Conocimiento. El profesional (personal quirúrgico) ve solo el
// repositorio, las comunidades y las mejores prácticas; la gestión —tablero,
// alertas de las reglas, recomendaciones— es del administrador.
const conocimientoNavItems = (
    gestiona: boolean,
    pendientes: { alertas: number; revision: number; devueltas: number },
): NavItem[] => [
    ...(gestiona
        ? [
              {
                  title: 'Tablero de conocimiento',
                  href: '/conocimiento',
                  icon: Brain,
              },
              {
                  title: 'Alertas y reglas',
                  href: '/conocimiento/alertas',
                  icon: ShieldAlert,
                  badge:
                      pendientes.alertas > 0 ? pendientes.alertas : undefined,
              },
          ]
        : []),
    {
        title: 'Lecciones aprendidas',
        href: '/conocimiento/lecciones',
        icon: BookOpen,
        badge:
            pendientes.revision + pendientes.devueltas > 0
                ? pendientes.revision + pendientes.devueltas
                : undefined,
    },
    ...(gestiona
        ? [
              {
                  title: 'Recomendaciones',
                  href: '/conocimiento/recomendaciones',
                  icon: Lightbulb,
              },
          ]
        : []),
    {
        title: 'Mejores prácticas',
        href: '/conocimiento/mejores-practicas',
        icon: Sparkles,
    },
    {
        title: 'Comunidades',
        href: '/conocimiento/comunidades',
        icon: MessagesSquare,
    },
];

const profesionalesNavItems: NavItem[] = [
    {
        title: 'Profesionales',
        href: '/conocimiento/profesionales',
        icon: UserRoundCheck,
    },
];

export function AppSidebar() {
    const { auth, alertasPendientes, conocimientoPendientes } = usePage().props;
    const role = auth.user?.role;
    const esDigitador = role === 'digitador';
    const esProfesional = role === 'profesional';
    const pendientesConocimiento = conocimientoPendientes ?? {
        alertas: 0,
        revision: 0,
        devueltas: 0,
    };
    const esAdminHospital = role === 'admin_hospital';
    // El dashboard es consolidado multi-hospital: solo tiene sentido para el
    // super_admin, así que el resto de roles ni siquiera lo ven en el menú.
    const esSuperAdmin = role === 'super_admin';

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
                            <Link
                                href={
                                    esDigitador
                                        ? '/cirugias'
                                        : esProfesional
                                          ? '/conocimiento/lecciones'
                                          : esSuperAdmin
                                            ? dashboard()
                                            : '/parametros'
                                }
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <HospitalSwitcher />
            </SidebarHeader>

            {/* Colapsada, cada SidebarGroupLabel se oculta con un `-mt-8` que
                arrastra su grupo hacia arriba; en el primero eso metía el ícono
                debajo del logo (y el overflow oculto lo recortaba). El padding
                superior devuelve esos 32px y deja algo de aire. */}
            <SidebarContent className="px-2 group-data-[collapsible=icon]:pt-10">
                {esDigitador ? (
                    // El digitador solo registra: su índice es la pantalla con
                    // el botón, sin histórico ni costos.
                    <NavMain items={registroNavItems} label="Registro" />
                ) : esProfesional ? (
                    <NavMain
                        items={conocimientoNavItems(
                            false,
                            pendientesConocimiento,
                        )}
                        label="Conocimiento"
                    />
                ) : (
                    <>
                        {esSuperAdmin && (
                            <>
                                <NavMain
                                    items={generalNavItems}
                                    label="General"
                                />
                                <NavMain
                                    items={institucionesNavItems}
                                    label="Administración"
                                    className="mt-[14px]"
                                />
                            </>
                        )}
                        <NavMain
                            items={parametrosNavItems}
                            label="Configuración"
                            className={esSuperAdmin ? 'mt-[14px]' : undefined}
                        />
                        <NavMain
                            items={[...pacientesNavItems, ...registroNavItems]}
                            label="Captura"
                            className="mt-[14px]"
                        />
                        <NavMain
                            items={costeoNavItems(alertasPendientes ?? 0)}
                            label="Análisis"
                            className="mt-[14px]"
                        />
                        <NavMain
                            items={conocimientoNavItems(
                                true,
                                pendientesConocimiento,
                            )}
                            label="Conocimiento"
                            className="mt-[14px]"
                        />
                        {esAdminHospital && (
                            <NavMain
                                items={[
                                    ...digitadoresNavItems,
                                    ...profesionalesNavItems,
                                ]}
                                label="Equipo"
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
