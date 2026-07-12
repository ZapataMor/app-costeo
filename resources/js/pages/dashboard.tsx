import { Head, router, usePage } from '@inertiajs/react';
import { Building2, Globe, Stethoscope, Syringe, Users } from 'lucide-react';
import HospitalActivoController from '@/actions/App/Http/Controllers/HospitalActivoController';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop } from '@/lib/formato';
import { dashboard } from '@/routes';

interface HospitalResumen {
    id: number;
    nombre: string;
    municipio: string | null;
    departamento: string;
    nivel_complejidad: string;
    cirugias: number;
    pacientes: number;
    costo_total: number;
}

interface DashboardProps {
    hospitales: HospitalResumen[];
}

export default function Dashboard({ hospitales }: DashboardProps) {
    const { auth, hospital } = usePage().props;
    const esSuperAdmin = auth.user?.role === 'super_admin';

    /**
     * Fija el hospital activo de la sesión (o la vista consolidada con
     * null) y entra al módulo de costeo de ese contexto.
     */
    const seleccionar = (hospitalId: number | null) => {
        router.post(
            HospitalActivoController.store.url(),
            { hospital_id: hospitalId },
            {
                preserveScroll: true,
                onSuccess: () => router.visit('/costeo'),
            },
        );
    };

    const totales = hospitales.reduce(
        (acc, h) => ({
            cirugias: acc.cirugias + h.cirugias,
            pacientes: acc.pacientes + h.pacientes,
            costo_total: acc.costo_total + h.costo_total,
        }),
        { cirugias: 0, pacientes: 0, costo_total: 0 },
    );

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Centros clínicos y hospitalarios</h1>
                    <p className="text-sm text-muted-foreground">
                        {esSuperAdmin
                            ? 'Seleccione un centro para navegar su información, o "Todos" para la vista consolidada.'
                            : 'Resumen de su centro.'}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {esSuperAdmin && (
                        <Card
                            role="button"
                            tabIndex={0}
                            onClick={() => seleccionar(null)}
                            onKeyDown={(e) => e.key === 'Enter' && seleccionar(null)}
                            className={`cursor-pointer transition-colors hover:border-primary ${hospital.activo === null ? 'border-primary' : ''}`}
                        >
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Globe className="size-5 text-muted-foreground" />
                                    Todos
                                </CardTitle>
                                <CardDescription>
                                    Vista consolidada de los {hospitales.length} centros
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-3 gap-2 text-sm">
                                <Indicador icono={Syringe} etiqueta="Cirugías" valor={String(totales.cirugias)} />
                                <Indicador icono={Users} etiqueta="Pacientes" valor={String(totales.pacientes)} />
                                <Indicador icono={Stethoscope} etiqueta="Costo total" valor={cop(totales.costo_total)} />
                            </CardContent>
                        </Card>
                    )}

                    {hospitales.map((h) => {
                        const activo = hospital.activo?.id === h.id;

                        return (
                            <Card
                                key={h.id}
                                role="button"
                                tabIndex={0}
                                onClick={() => esSuperAdmin && seleccionar(h.id)}
                                onKeyDown={(e) => e.key === 'Enter' && esSuperAdmin && seleccionar(h.id)}
                                className={`transition-colors ${esSuperAdmin ? 'cursor-pointer hover:border-primary' : ''} ${activo ? 'border-primary' : ''}`}
                            >
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="size-5 shrink-0 text-muted-foreground" />
                                        <span className="truncate">{h.nombre}</span>
                                    </CardTitle>
                                    <CardDescription className="flex flex-wrap items-center gap-2">
                                        {[h.municipio, h.departamento].filter(Boolean).join(', ')}
                                        <Badge variant="secondary">Nivel {h.nivel_complejidad}</Badge>
                                        {activo && <Badge>Activo</Badge>}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid grid-cols-3 gap-2 text-sm">
                                    <Indicador icono={Syringe} etiqueta="Cirugías" valor={String(h.cirugias)} />
                                    <Indicador icono={Users} etiqueta="Pacientes" valor={String(h.pacientes)} />
                                    <Indicador icono={Stethoscope} etiqueta="Costo total" valor={cop(h.costo_total)} />
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

function Indicador({
    icono: Icono,
    etiqueta,
    valor,
}: {
    icono: typeof Syringe;
    etiqueta: string;
    valor: string;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                <Icono className="size-3.5" />
                {etiqueta}
            </span>
            <span className="font-medium tabular-nums">{valor}</span>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
