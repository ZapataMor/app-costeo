import { Link, usePage } from '@inertiajs/react';
import {
    BellRing,
    BookOpen,
    Brain,
    Building2,
    History,
    House,
    Lightbulb,
    MessagesSquare,
    Sparkles,
    UserRoundCheck,
    LayoutGrid,
    SlidersHorizontal,
    Stethoscope,
    Syringe,
    Users,
    UsersRound,
} from 'lucide-react';
import { AppLogoMarca, AppLogoTexto } from '@/components/app-logo';
import { HospitalSwitcher } from '@/components/hospital-switcher';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

// Portada del hospital: cómo va el mes y qué pide acción. Es a donde llega
// el administrador al iniciar sesión.
const inicioNavItems: NavItem[] = [
    {
        title: 'Inicio',
        href: '/inicio',
        icon: House,
    },
];

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

// Una sola bandeja de alertas. Antes había dos contadores (sobrecostos en
// Costeo y reglas en Conocimiento) para dos pantallas casi iguales; ahora el
// número cuenta casos —procedimientos con algo prioritario—, no alertas
// sueltas. Viaja en las props compartidas para verse desde cualquier pantalla.
const alertasNavItems = (casos: number): NavItem[] => [
    {
        title: 'Alertas',
        href: '/alertas',
        icon: BellRing,
        badge: casos > 0 ? casos : undefined,
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
// Las alertas de las reglas se atienden en la bandeja única (/alertas).
const conocimientoNavItems = (
    gestiona: boolean,
    pendientes: { alertas: number; revision: number; devueltas: number },
): NavItem[] => [
    ...(gestiona
        ? [
              {
                  // Va bajo el grupo «Conocimiento»: el nombre largo se
                  // cortaba en «Tablero de conocimien…».
                  title: 'Tablero',
                  href: '/conocimiento',
                  icon: Brain,
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
    const { auth, alertasPorRevisar, conocimientoPendientes } = usePage().props;
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
    const { state, toggleSidebar } = useSidebar();

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="border-0 bg-[#17181F] p-0"
        >
            <SidebarHeader className="gap-4 px-4 pt-5 pb-3">
                {/* El ícono de la app recoge y expande la sidebar (reemplaza el
                    botón de la barra superior); el nombre lleva a la portada. */}
                <div className="flex items-center gap-2 p-1">
                    <button
                        type="button"
                        onClick={toggleSidebar}
                        aria-label={
                            state === 'expanded'
                                ? 'Recoger menú'
                                : 'Expandir menú'
                        }
                        title={
                            state === 'expanded'
                                ? 'Recoger menú'
                                : 'Expandir menú'
                        }
                        className="shrink-0 cursor-pointer rounded-[9px] transition-opacity hover:opacity-85 focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none"
                    >
                        <AppLogoMarca />
                    </button>
                    <Link
                        href={
                            esDigitador
                                ? '/cirugias'
                                : esProfesional
                                  ? '/conocimiento/lecciones'
                                  : esSuperAdmin
                                    ? dashboard()
                                    : '/inicio'
                        }
                        prefetch
                        className="min-w-0 flex-1 group-data-[collapsible=icon]:hidden"
                    >
                        <AppLogoTexto />
                    </Link>
                </div>
                <HospitalSwitcher />
            </SidebarHeader>

            {/* Colapsada, cada SidebarGroupLabel se oculta con un `-mt-8` que
                arrastra su grupo hacia arriba; en el primero eso metía el ícono
                debajo del logo (y el overflow oculto lo recortaba). El padding
                superior devuelve esos 32px y deja algo de aire. El primer grupo
                del administrador no lleva etiqueta: ahí basta un poco de aire. */}
            <SidebarContent
                className={`px-2 ${esDigitador || esProfesional ? 'group-data-[collapsible=icon]:pt-10' : 'group-data-[collapsible=icon]:pt-2'}`}
            >
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
                    // De lo primordial a lo ocasional: arriba lo de todos los
                    // días (cómo vamos y qué pide acción), luego el costeo y,
                    // plegados, Conocimiento y Administración.
                    <>
                        <NavMain
                            items={[
                                ...inicioNavItems,
                                ...(esSuperAdmin ? generalNavItems : []),
                                ...alertasNavItems(alertasPorRevisar ?? 0),
                            ]}
                        />
                        <NavMain
                            items={[
                                ...costeoNavItems,
                                ...registroNavItems,
                                ...pacientesNavItems,
                            ]}
                            label="Costeo"
                            className="mt-[14px]"
                        />
                        <NavMain
                            items={conocimientoNavItems(
                                true,
                                pendientesConocimiento,
                            )}
                            label="Conocimiento"
                            className="mt-[14px]"
                            plegable
                        />
                        <NavMain
                            items={[
                                ...parametrosNavItems,
                                ...(esAdminHospital
                                    ? [
                                          ...digitadoresNavItems,
                                          ...profesionalesNavItems,
                                      ]
                                    : []),
                                ...(esSuperAdmin ? institucionesNavItems : []),
                                ...historialNavItems,
                            ]}
                            label="Administración"
                            className="mt-[14px]"
                            plegable
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
