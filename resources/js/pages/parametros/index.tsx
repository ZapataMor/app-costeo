import { Head, Link } from '@inertiajs/react';
import {
    BedDouble,
    Building2,
    ListChecks,
    MonitorSpeaker,
    Package,
    Plus,
    Users,
} from 'lucide-react';
import type { ComponentType } from 'react';
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

interface ItemResumen {
    id: number;
    nombre: string;
    detalle: string | null;
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
    hospitalActivo: {
        id: number;
        nombre: string;
        horas_dia: number;
        dias_mes: number;
        factor_indirecto: number;
    } | null;
}

const definiciones: {
    clave: keyof ParametrosIndexProps['modulos'];
    titulo: string;
    descripcion: string;
    href: string;
    icono: ComponentType<{ className?: string }>;
}[] = [
    {
        clave: 'recursosHumanos',
        titulo: 'Recursos humanos',
        descripcion: 'Personal quirúrgico y su estructura salarial.',
        href: '/parametros/recursos-humanos',
        icono: Users,
    },
    {
        clave: 'insumos',
        titulo: 'Insumos',
        descripcion: 'Insumos y dispositivos con su costo unitario.',
        href: '/parametros/insumos',
        icono: Package,
    },
    {
        clave: 'equiposMedicos',
        titulo: 'Equipos médicos',
        descripcion: 'Equipos con depreciación y costo por minuto.',
        href: '/parametros/equipos-medicos',
        icono: MonitorSpeaker,
    },
    {
        clave: 'salasOperatorias',
        titulo: 'Salas operatorias',
        descripcion: 'Salas con sus costos fijos de operación.',
        href: '/parametros/salas-operatorias',
        icono: BedDouble,
    },
    {
        clave: 'procedimientos',
        titulo: 'Procedimientos',
        descripcion: 'Catálogo CUPS de procedimientos quirúrgicos.',
        href: '/parametros/procedimientos',
        icono: ListChecks,
    },
];

export default function ParametrosIndex({ modulos, hospitalActivo: hospital }: ParametrosIndexProps) {
    return (
        <>
            <Head title="Parámetros" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Parámetros (Capa 1)</h1>
                    <p className="text-sm text-muted-foreground">
                        Catálogos base del costeo TDABC. Entre a cada módulo para ver el listado completo y su CRUD.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {definiciones.map(({ clave, titulo, descripcion, href, icono: Icono }) => {
                        const modulo = modulos[clave];

                        return (
                            <Card key={clave} className="flex flex-col">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Icono className="size-5 shrink-0 text-muted-foreground" />
                                        <span className="flex-1 truncate">{titulo}</span>
                                        <Badge variant="secondary">{modulo.total}</Badge>
                                    </CardTitle>
                                    <CardDescription>{descripcion}</CardDescription>
                                </CardHeader>
                                <CardContent className="flex-1">
                                    {modulo.items.length === 0 ? (
                                        <p className="py-4 text-center text-sm text-muted-foreground">
                                            Sin registros todavía.
                                        </p>
                                    ) : (
                                        <table className="w-full text-sm">
                                            <tbody>
                                                {modulo.items.map((item) => (
                                                    <tr key={item.id} className="border-b last:border-0">
                                                        <td className="max-w-0 truncate py-2 pr-2">{item.nombre}</td>
                                                        <td className="py-2 text-right text-xs whitespace-nowrap text-muted-foreground capitalize">
                                                            {item.detalle ?? ''}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                    {modulo.total > modulo.items.length && (
                                        <p className="pt-2 text-xs text-muted-foreground">
                                            y {modulo.total - modulo.items.length} más…
                                        </p>
                                    )}
                                </CardContent>
                                <CardFooter className="gap-2">
                                    <Button asChild variant="outline" size="sm" className="flex-1">
                                        <Link href={href} prefetch>
                                            Ver listado y CRUD
                                        </Link>
                                    </Button>
                                    <Button asChild size="sm">
                                        <Link href={`${href}/create`} prefetch>
                                            <Plus className="size-4" />
                                            Nuevo
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        );
                    })}

                    <Card className="flex flex-col">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="size-5 shrink-0 text-muted-foreground" />
                                <span className="flex-1 truncate">Hospital</span>
                            </CardTitle>
                            <CardDescription>
                                Capacidad TDABC del hospital activo: horas por día, días por mes y factor de costos indirectos.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex-1 text-sm">
                            {hospital ? (
                                <dl className="space-y-2">
                                    <div className="flex justify-between border-b pb-2">
                                        <dt className="text-muted-foreground">Hospital</dt>
                                        <dd className="max-w-48 truncate font-medium">{hospital.nombre}</dd>
                                    </div>
                                    <div className="flex justify-between border-b pb-2">
                                        <dt className="text-muted-foreground">Horas por día</dt>
                                        <dd className="font-medium tabular-nums">{hospital.horas_dia}</dd>
                                    </div>
                                    <div className="flex justify-between border-b pb-2">
                                        <dt className="text-muted-foreground">Días por mes</dt>
                                        <dd className="font-medium tabular-nums">{hospital.dias_mes}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">Factor indirecto</dt>
                                        <dd className="font-medium tabular-nums">{hospital.factor_indirecto}</dd>
                                    </div>
                                </dl>
                            ) : (
                                <p className="py-4 text-center text-muted-foreground">
                                    Está en la vista consolidada. Seleccione un hospital en el dashboard o en el selector para editar su configuración.
                                </p>
                            )}
                        </CardContent>
                        <CardFooter>
                            <Button asChild variant="outline" size="sm" className="flex-1" disabled={!hospital}>
                                <Link href="/parametros/hospital" prefetch>
                                    {hospital ? 'Editar configuración' : 'Requiere hospital activo'}
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
