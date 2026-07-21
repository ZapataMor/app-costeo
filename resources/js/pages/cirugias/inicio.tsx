import { Head, Link } from '@inertiajs/react';
import { Plus, Syringe } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * Pantalla única del digitador. Su trabajo es registrar procedimientos, así
 * que no ve el histórico del hospital: ni los datos de otros pacientes ni los
 * costos. Un solo camino, sin ruido.
 */
export default function CirugiasInicio({
    registradosHoy,
}: {
    registradosHoy: number;
}) {
    return (
        <>
            <Head title="Registrar procedimiento" />
            <div className="flex flex-1 flex-col items-center justify-center gap-6 p-4 text-center">
                <div className="flex size-16 items-center justify-center rounded-2xl border border-[#5B687C]/20 text-[#5B687C]">
                    <Syringe className="size-7" />
                </div>

                <div className="max-w-md">
                    <h1 className="text-[28px] leading-tight font-semibold text-[#161B2F] dark:text-[#EDE7E5]">
                        Registro de procedimientos
                    </h1>
                    <p className="mt-2 text-[13.5px] text-[#737778] dark:text-[#9EA0A5]">
                        Capture aquí cada procedimiento realizado: paciente,
                        equipo quirúrgico, insumos y equipos utilizados.
                    </p>
                </div>

                <Button asChild size="lg">
                    <Link href="/cirugias/create">
                        <Plus className="size-4" />
                        Registrar procedimiento
                    </Link>
                </Button>

                {registradosHoy > 0 && (
                    <p className="text-sm text-muted-foreground">
                        {registradosHoy === 1
                            ? 'Ha registrado 1 procedimiento hoy.'
                            : `Ha registrado ${registradosHoy} procedimientos hoy.`}
                    </p>
                )}
            </div>
        </>
    );
}
