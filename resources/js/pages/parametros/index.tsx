import { Head, Link } from '@inertiajs/react';
import {
    BedDouble,
    Building2,
    ListChecks,
    MonitorSpeaker,
    Package,
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop } from '@/lib/formato';

interface ItemResumen {
    id: number;
    nombre: string;
    detalle: string | null;
    valor: number | null;
    unidad: string | null;
}

interface ModuloResumen {
    total: number;
    items: ItemResumen[];
}

interface ParametrosIndexProps {
    modulos: {
        recursosHumanos: ModuloResumen;
        insumos: ModuloResumen;
        equiposMedicos: ModuloResumen;
        salasOperatorias: ModuloResumen;
        procedimientos: ModuloResumen;
    };
    catalogos: Catalogos;
    hospitalActivo: {
        id: number;
        nombre: string;
        horas_dia: number;
        dias_mes: number;
        factor_indirecto: number;
    } | null;
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
    formulario: (cerrar: () => void, catalogos: Catalogos) => ReactNode;
}[] = [
    {
        clave: 'recursosHumanos',
        titulo: 'Recursos humanos',
        tituloNuevo: 'Nuevo recurso humano',
        descripcion: 'Personal quirúrgico y su estructura salarial.',
        href: '/parametros/recursos-humanos',
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
        descripcion: 'Insumos y dispositivos con su costo unitario.',
        href: '/parametros/insumos',
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
        descripcion: 'Equipos con depreciación y costo por minuto.',
        href: '/parametros/equipos-medicos',
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
        descripcion: 'Salas con sus costos fijos de operación.',
        href: '/parametros/salas-operatorias',
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
        descripcion: 'Catálogo CUPS de procedimientos quirúrgicos.',
        href: '/parametros/procedimientos',
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
                        Catálogos base del costeo TDABC. Entre a cada módulo
                        para ver el listado completo y su CRUD.
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
                            icono: Icono,
                            formulario,
                        }) => {
                            const modulo = modulos[clave];

                            return (
                                <Card key={clave} className="flex flex-col">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Icono className="size-5 shrink-0 text-muted-foreground" />
                                            <span className="flex-1 truncate">
                                                {titulo}
                                            </span>
                                            <Badge variant="secondary">
                                                {modulo.total}
                                            </Badge>
                                        </CardTitle>
                                        <CardDescription>
                                            {descripcion}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex-1">
                                        {modulo.items.length === 0 ? (
                                            <p className="py-4 text-center text-sm text-muted-foreground">
                                                Sin registros todavía.
                                            </p>
                                        ) : (
                                            <ul className="text-sm">
                                                {modulo.items.map((item) => (
                                                    <li
                                                        key={item.id}
                                                        className="flex items-center justify-between gap-3 border-b py-2 last:border-0"
                                                    >
                                                        <div className="min-w-0">
                                                            <p
                                                                className="truncate"
                                                                title={
                                                                    item.nombre
                                                                }
                                                            >
                                                                {item.nombre}
                                                            </p>
                                                            {item.detalle && (
                                                                <p
                                                                    className="truncate text-xs text-muted-foreground"
                                                                    title={
                                                                        item.detalle
                                                                    }
                                                                >
                                                                    {
                                                                        item.detalle
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        {item.valor !==
                                                            null && (
                                                            <div className="shrink-0 text-right">
                                                                <p className="font-medium tabular-nums">
                                                                    {cop(
                                                                        item.valor,
                                                                    )}
                                                                </p>
                                                                {item.unidad && (
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {
                                                                            item.unidad
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        {modulo.total > modulo.items.length && (
                                            <p className="pt-2 text-xs text-muted-foreground">
                                                y{' '}
                                                {modulo.total -
                                                    modulo.items.length}{' '}
                                                más…
                                            </p>
                                        )}
                                    </CardContent>
                                    <CardFooter className="gap-2">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="flex-1"
                                        >
                                            <Link href={href} prefetch>
                                                Ver listado y CRUD
                                            </Link>
                                        </Button>
                                        <ModalFormulario
                                            titulo={tituloNuevo}
                                            textoBoton="Nuevo"
                                            tamanoBoton="sm"
                                        >
                                            {(cerrar) =>
                                                formulario(cerrar, catalogos)
                                            }
                                        </ModalFormulario>
                                    </CardFooter>
                                </Card>
                            );
                        },
                    )}

                    <Card className="flex flex-col">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="size-5 shrink-0 text-muted-foreground" />
                                <span className="flex-1 truncate">
                                    Hospital
                                </span>
                            </CardTitle>
                            <CardDescription>
                                Capacidad TDABC del hospital activo: horas por
                                día, días por mes y factor de costos indirectos.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex-1 text-sm">
                            {hospital ? (
                                <dl className="space-y-2">
                                    <div className="flex justify-between border-b pb-2">
                                        <dt className="text-muted-foreground">
                                            Hospital
                                        </dt>
                                        <dd className="max-w-48 truncate font-medium">
                                            {hospital.nombre}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between border-b pb-2">
                                        <dt className="text-muted-foreground">
                                            Horas por día
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {hospital.horas_dia}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between border-b pb-2">
                                        <dt className="text-muted-foreground">
                                            Días por mes
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {hospital.dias_mes}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            Factor indirecto
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {hospital.factor_indirecto}
                                        </dd>
                                    </div>
                                </dl>
                            ) : (
                                <p className="py-4 text-center text-muted-foreground">
                                    Está en la vista consolidada. Seleccione un
                                    hospital en el dashboard o en el selector
                                    para editar su configuración.
                                </p>
                            )}
                        </CardContent>
                        <CardFooter>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="flex-1"
                                disabled={!hospital}
                            >
                                <Link href="/parametros/hospital" prefetch>
                                    {hospital
                                        ? 'Editar configuración'
                                        : 'Requiere hospital activo'}
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </>
    );
}

ParametrosIndex.layout = {
    breadcrumbs: [{ title: 'Parámetros', href: '/parametros' }],
};
