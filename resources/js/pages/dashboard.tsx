import { Head, router, usePage } from '@inertiajs/react';
import { Globe, Stethoscope, Syringe, Users } from 'lucide-react';
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
            <div className="flex h-full flex-1 flex-col p-4">
                <div>
                    <h1 className="text-[32px] leading-[1.1] font-semibold text-[#161B2F] dark:text-[#EDE7E5]">
                        Centros clínicos y hospitalarios
                    </h1>
                    <p className="mt-1.5 mb-[26px] max-w-[620px] text-[13.5px] text-[#737778] dark:text-[#9EA0A5]">
                        {esSuperAdmin
                            ? 'Seleccione un centro para trabajar su costeo TDABC, o consulte la vista consolidada de toda la red.'
                            : 'Resumen de su centro.'}
                    </p>
                </div>

                <div className="grid grid-cols-[repeat(auto-fill,minmax(288px,1fr))] gap-[18px]">
                    {esSuperAdmin && (
                        <Card
                            role="button"
                            tabIndex={0}
                            onClick={() => seleccionar(null)}
                            onKeyDown={(e) =>
                                e.key === 'Enter' && seleccionar(null)
                            }
                            data-active={hospital.activo === null}
                            className="sicoq-hcard cursor-pointer gap-0 py-0"
                        >
                            <CardHeader className="flex-row items-center gap-[11px] px-[22px] pt-[22px] pb-5">
                                <div className="flex size-[38px] shrink-0 items-center justify-center rounded-[10px] border border-[#5B687C]/20 text-[#5B687C]">
                                    <Globe className="size-5" />
                                </div>
                                <div className="min-w-0">
                                    <CardTitle className="text-xl leading-[1.1] font-semibold">
                                        Todos
                                    </CardTitle>
                                    <CardDescription className="mt-0.5 text-[11.5px]">
                                        Vista consolidada de los{' '}
                                        {hospitales.length} centros
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="mx-[22px] grid grid-cols-2 gap-x-3 gap-y-4 border-t border-[#5B687C]/15 px-0 pt-4 pb-5">
                                <Indicador
                                    icono={Syringe}
                                    etiqueta="Cirugías"
                                    valor={String(totales.cirugias)}
                                />
                                <Indicador
                                    icono={Users}
                                    etiqueta="Pacientes"
                                    valor={String(totales.pacientes)}
                                />
                                <Indicador
                                    icono={Stethoscope}
                                    etiqueta="Costo total"
                                    valor={cop(totales.costo_total)}
                                    className="col-span-2"
                                />
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
                                onClick={() =>
                                    esSuperAdmin && seleccionar(h.id)
                                }
                                onKeyDown={(e) =>
                                    e.key === 'Enter' &&
                                    esSuperAdmin &&
                                    seleccionar(h.id)
                                }
                                data-active={activo}
                                className={`sicoq-hcard relative gap-0 py-0 ${esSuperAdmin ? 'cursor-pointer' : ''}`}
                            >
                                {activo && (
                                    <span className="absolute top-[18px] right-[18px] flex items-center gap-1.5 text-[9.5px] font-semibold tracking-[1.6px] text-[#5B687C] uppercase">
                                        <span className="size-1.5 rounded-full bg-[#5B687C]" />
                                        Activo
                                    </span>
                                )}
                                <CardHeader className="gap-0 px-[22px] pt-[22px] pb-0">
                                    <CardTitle className="pr-[70px] text-[19px] leading-[1.2] font-semibold">
                                        {h.nombre}
                                    </CardTitle>
                                    <CardDescription className="mt-1 mb-3 text-xs">
                                        {[h.municipio, h.departamento]
                                            .filter(Boolean)
                                            .join(', ')}
                                    </CardDescription>
                                    <Badge
                                        variant="outline"
                                        className="mb-[18px] px-2.5 py-1 tracking-[1px] uppercase"
                                    >
                                        Nivel {h.nivel_complejidad}
                                    </Badge>
                                </CardHeader>
                                <CardContent className="mx-[22px] grid grid-cols-3 gap-3 border-t border-[#5B687C]/15 px-0 pt-4 pb-5">
                                    <Indicador
                                        icono={Syringe}
                                        etiqueta="Cirugías"
                                        valor={String(h.cirugias)}
                                    />
                                    <Indicador
                                        icono={Users}
                                        etiqueta="Pacientes"
                                        valor={String(h.pacientes)}
                                    />
                                    <Indicador
                                        icono={Stethoscope}
                                        etiqueta="Costo"
                                        valor={cop(h.costo_total)}
                                        compact
                                    />
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
    compact = false,
    className = '',
}: {
    icono: typeof Syringe;
    etiqueta: string;
    valor: string;
    compact?: boolean;
    className?: string;
}) {
    return (
        <div className={className}>
            <span className="mb-1.5 flex items-center gap-1 text-[9px] font-semibold tracking-[1px] text-[#5B687C] uppercase">
                <Icono className="size-[13px]" />
                {etiqueta}
            </span>
            <span
                className={`block font-serif leading-[1.35] text-[#161B2F] tabular-nums dark:text-[#EDE7E5] ${compact ? 'text-[15px]' : 'text-[21px]'}`}
            >
                {valor}
            </span>
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
