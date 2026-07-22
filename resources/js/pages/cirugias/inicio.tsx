import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Syringe } from 'lucide-react';
import { CerrarCirugiaModal } from '@/components/cirugias/cerrar-cirugia-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type RegistroPropio = {
    id: number;
    fecha: string;
    paciente: { nombres: string; apellidos: string } | null;
    procedimiento_principal: { codigo_cups: string; nombre: string } | null;
    estado: string;
    duracion_minutos: number | null;
    puede_cerrarse: boolean;
    /** Marca que falta para avanzar el cierre; null si el ciclo ya cerró. */
    paso_cierre: 'sala' | 'ciclo' | null;
    hora_inicio: string;
    hora_fin: string | null;
};

/**
 * Pantalla única del digitador: el botón de registrar y lo que él mismo
 * capturó, para poder corregirlo en caliente.
 *
 * No ve el histórico del hospital —ni procedimientos de otros, ni costos—:
 * su trabajo es la captura, no el análisis.
 */
export default function CirugiasInicio({ mios }: { mios: RegistroPropio[] }) {
    const pendientes = mios.filter(
        (registro) => registro.puede_cerrarse,
    ).length;

    return (
        <>
            <Head title="Registrar procedimiento" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <div className="flex flex-col items-center gap-5 pt-6 text-center">
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
                </div>

                {mios.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Mis registros
                            </CardTitle>
                            <CardDescription>
                                Lo que capturó hoy y lo suyo que sigue sin
                                cerrar. Puede corregirlo mientras lo tenga
                                fresco.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {pendientes > 0 && (
                                <p className="mb-3 rounded-lg border border-amber-300/70 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                    {pendientes === 1
                                        ? 'Tiene 1 procedimiento sin cerrar. Use el botón ✓ para registrar la hora de finalización.'
                                        : `Tiene ${pendientes} procedimientos sin cerrar. Use el botón ✓ para registrar la hora de finalización.`}
                                </p>
                            )}

                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 font-medium">
                                                Fecha
                                            </th>
                                            <th className="py-2 font-medium">
                                                Paciente
                                            </th>
                                            <th className="py-2 font-medium">
                                                Procedimiento
                                            </th>
                                            <th className="py-2 font-medium">
                                                Estado
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {mios.map((registro) => (
                                            <tr
                                                key={registro.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-2 tabular-nums">
                                                    {registro.fecha}
                                                </td>
                                                <td className="py-2">
                                                    {registro.paciente
                                                        ? `${registro.paciente.nombres} ${registro.paciente.apellidos}`
                                                        : '—'}
                                                </td>
                                                <td className="py-2">
                                                    {registro
                                                        .procedimiento_principal
                                                        ?.nombre ?? '—'}
                                                </td>
                                                <td className="py-2">
                                                    <span className="capitalize">
                                                        {registro.estado.replace(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </span>
                                                    {registro.puede_cerrarse && (
                                                        <Badge
                                                            variant="outline"
                                                            className="ml-2 border-amber-300/70 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400"
                                                        >
                                                            Sin cerrar
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="py-2 text-right whitespace-nowrap">
                                                    {registro.puede_cerrarse && (
                                                        <CerrarCirugiaModal
                                                            cirugiaId={
                                                                registro.id
                                                            }
                                                            paso={
                                                                registro.paso_cierre ??
                                                                'sala'
                                                            }
                                                            horaInicio={
                                                                registro.hora_inicio
                                                            }
                                                            horaFin={
                                                                registro.hora_fin
                                                            }
                                                        />
                                                    )}
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Corregir"
                                                        title="Corregir"
                                                    >
                                                        <Link
                                                            href={`/cirugias/${registro.id}/edit`}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
