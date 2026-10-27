import { Head, Link } from '@inertiajs/react';
import {
    BedDouble,
    Building2,
    Coins,
    ListChecks,
    MonitorSpeaker,
    Package,
    Plus,
    Settings2,
    Users,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import EquipoMedicoController from '@/actions/App/Http/Controllers/Parametros/EquipoMedicoController';
import InsumoController from '@/actions/App/Http/Controllers/Parametros/InsumoController';
import ProcedimientoQuirurgicoController from '@/actions/App/Http/Controllers/Parametros/ProcedimientoQuirurgicoController';
import RecursoHumanoController from '@/actions/App/Http/Controllers/Parametros/RecursoHumanoController';
import SalaOperatoriaController from '@/actions/App/Http/Controllers/Parametros/SalaOperatoriaController';
import { EquipoMedicoForm } from '@/components/parametros/forms/equipo-medico-form';
import { InsumoForm } from '@/components/parametros/forms/insumo-form';
import { ProcedimientoForm } from '@/components/parametros/forms/procedimiento-form';
import { RecursoHumanoForm } from '@/components/parametros/forms/recurso-humano-form';
import { SalaOperatoriaForm } from '@/components/parametros/forms/sala-operatoria-form';
import { ModalFormulario } from '@/components/parametros/modal-formulario';
import { Button } from '@/components/ui/button';

interface ParametrosIndexProps {
    // Conteo de registros de cada catálogo del hospital activo.
    modulos: {
        recursosHumanos: number;
        insumos: number;
        equiposMedicos: number;
        salasOperatorias: number;
        procedimientos: number;
        costosIndirectos: number;
    };
    catalogos: Catalogos;
    hospitalActivo: { id: number; nombre: string } | null;
}

interface Catalogos {
    roles: string[];
    categorias: string[];
    complejidades: string[];
    nivelesConfiabilidad: string[];
}

const definiciones: {
    clave: keyof ParametrosIndexProps['modulos'];
    titulo: string;
    tituloNuevo: string;
    descripcion: string;
    href: string;
    icono: ComponentType<{ className?: string }>;
    // Opcional: un catálogo con formulario propio (campos condicionados,
    // vigencias) no cabe en el modal del hub: su "Añadir" abre la página
    // de creación.
    formulario?: (cerrar: () => void, catalogos: Catalogos) => ReactNode;
}[] = [
    {
        clave: 'recursosHumanos',
        titulo: 'Recursos humanos',
        tituloNuevo: 'Nuevo recurso humano',
        href: '/parametros/recursos-humanos',
        descripcion:
            'Personal quirúrgico y su salario, del que sale el costo de cada minuto de trabajo.',
        icono: Users,
        formulario: (cerrar, c) => (
            <RecursoHumanoForm
                action={RecursoHumanoController.store.form()}
                roles={c.roles}
                nivelesConfiabilidad={c.nivelesConfiabilidad}
                onSuccess={cerrar}
            />
        ),
    },
    {
        clave: 'insumos',
        titulo: 'Insumos',
        tituloNuevo: 'Nuevo insumo',
        href: '/parametros/insumos',
        descripcion:
            'Medicamentos, materiales y dispositivos que se consumen en cirugía, con su costo unitario.',
        icono: Package,
        formulario: (cerrar, c) => (
            <InsumoForm
                action={InsumoController.store.form()}
                categorias={c.categorias}
                nivelesConfiabilidad={c.nivelesConfiabilidad}
                onSuccess={cerrar}
            />
        ),
    },
    {
        clave: 'equiposMedicos',
        titulo: 'Equipos médicos',
        tituloNuevo: 'Nuevo equipo médico',
        href: '/parametros/equipos-medicos',
        descripcion:
            'Equipos del quirófano con su depreciación y su costo por hora de uso.',
        icono: MonitorSpeaker,
        formulario: (cerrar, c) => (
            <EquipoMedicoForm
                action={EquipoMedicoController.store.form()}
                nivelesConfiabilidad={c.nivelesConfiabilidad}
                onSuccess={cerrar}
            />
        ),
    },
    {
        clave: 'salasOperatorias',
        titulo: 'Salas operatorias',
        tituloNuevo: 'Nueva sala operatoria',
        href: '/parametros/salas-operatorias',
        descripcion: 'Quirófanos con sus costos fijos de operación por hora.',
        icono: BedDouble,
        formulario: (cerrar, c) => (
            <SalaOperatoriaForm
                action={SalaOperatoriaController.store.form()}
                nivelesConfiabilidad={c.nivelesConfiabilidad}
                onSuccess={cerrar}
            />
        ),
    },
    {
        clave: 'procedimientos',
        titulo: 'Procedimientos',
        tituloNuevo: 'Nuevo procedimiento quirúrgico',
        href: '/parametros/procedimientos',
        descripcion:
            'Cirugías del catálogo CUPS con su duración estimada y tarifa SOAT.',
        icono: ListChecks,
        formulario: (cerrar, c) => (
            <ProcedimientoForm
                action={ProcedimientoQuirurgicoController.store.form()}
                complejidades={c.complejidades}
                nivelesConfiabilidad={c.nivelesConfiabilidad}
                onSuccess={cerrar}
            />
        ),
    },
    {
        clave: 'costosIndirectos',
        titulo: 'Costos indirectos',
        tituloNuevo: 'Nuevo concepto de costo indirecto',
        href: '/parametros/costos-indirectos',
        descripcion:
            'Gastos generales del hospital que se reparten entre las cirugías (CIF).',
        icono: Coins,
    },
];

export default function ParametrosIndex({
    modulos,
    catalogos,
    hospitalActivo: hospital,
}: ParametrosIndexProps) {
    return (
        <>
            <Head title="Parámetros" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-[32px] leading-[1.1] font-semibold text-[#161B2F] dark:text-[#EDE7E5]">
                        Parámetros
                    </h1>
                    <p className="mt-1.5 mb-1 max-w-[620px] text-[13.5px] text-[#737778] dark:text-[#9EA0A5]">
                        Catálogos base del costeo. Entre a cada uno para ver o
                        corregir sus registros.
                    </p>
                </div>

                <div className="grid gap-[18px] sm:grid-cols-2 xl:grid-cols-3">
                    {definiciones.map(
                        ({
                            clave,
                            titulo,
                            tituloNuevo,
                            descripcion,
                            href,
                            icono,
                            formulario,
                        }) => (
                            <CardCatalogo
                                key={clave}
                                titulo={titulo}
                                descripcion={descripcion}
                                href={href}
                                icono={icono}
                                total={modulos[clave]}
                                accion={
                                    formulario ? (
                                        <ModalFormulario
                                            titulo={tituloNuevo}
                                            textoBoton="Añadir"
                                            variante="outline"
                                            tamanoBoton="sm"
                                        >
                                            {(cerrar) =>
                                                formulario(cerrar, catalogos)
                                            }
                                        </ModalFormulario>
                                    ) : (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={`${href}/create`}
                                                prefetch
                                            >
                                                <Plus className="size-4" />
                                                Añadir
                                            </Link>
                                        </Button>
                                    )
                                }
                            />
                        ),
                    )}

                    <CardCatalogo
                        titulo="Hospital"
                        descripcion="Jornada, días por mes y minutos efectivos con los que se calcula la capacidad."
                        href={hospital ? '/parametros/hospital' : null}
                        icono={Building2}
                        accion={
                            hospital ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link href="/parametros/hospital" prefetch>
                                        <Settings2 className="size-4" />
                                        Configurar
                                    </Link>
                                </Button>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Requiere un hospital activo.
                                </p>
                            )
                        }
                    />
                </div>
            </div>
        </>
    );
}

/**
 * Card minimalista del hub: toda la superficie enlaza al catálogo (enlace
 * extendido sobre la card) y la acción queda por encima con `relative z-10`
 * para no anidar un botón dentro de un enlace.
 */
function CardCatalogo({
    titulo,
    descripcion,
    href,
    icono: Icono,
    total,
    accion,
}: {
    titulo: string;
    descripcion: string;
    href: string | null;
    icono: ComponentType<{ className?: string }>;
    total?: number;
    accion: ReactNode;
}) {
    return (
        <div
            className={`relative flex flex-col gap-5 rounded-xl bg-card p-5 text-card-foreground shadow-[0_1px_2px_rgba(23,24,31,.06),0_12px_28px_-14px_rgba(23,24,31,.32)] transition-[box-shadow,transform,background-color] duration-200 dark:shadow-[0_1px_2px_rgba(0,0,0,.45),0_16px_34px_-14px_rgba(0,0,0,.8)] ${href ? 'hover:-translate-y-0.5 hover:bg-[#5B687C]/[0.04] hover:shadow-[0_2px_4px_rgba(23,24,31,.08),0_20px_40px_-16px_rgba(23,24,31,.42)] dark:hover:bg-white/[0.03] dark:hover:shadow-[0_2px_4px_rgba(0,0,0,.5),0_24px_44px_-16px_rgba(0,0,0,.9)]' : 'opacity-70'}`}
        >
            {href && (
                <Link
                    href={href}
                    prefetch
                    aria-label={`Abrir ${titulo}`}
                    className="absolute inset-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />
            )}
            <div className="flex flex-col gap-2">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <Icono className="size-5 shrink-0 text-muted-foreground" />
                        <h2 className="truncate font-semibold">{titulo}</h2>
                    </div>
                    {total !== undefined && (
                        <span className="text-[28px] leading-none font-semibold text-[#161B2F] tabular-nums dark:text-[#EDE7E5]">
                            {total}
                        </span>
                    )}
                </div>
                <p className="text-[13px] leading-snug text-muted-foreground">
                    {descripcion}
                </p>
            </div>
            <div className="relative z-10 mt-auto flex">{accion}</div>
        </div>
    );
}

ParametrosIndex.layout = {
    breadcrumbs: [{ title: 'Parámetros', href: '/parametros' }],
};
