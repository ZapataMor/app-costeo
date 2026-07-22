import { Head } from '@inertiajs/react';
import {
    Building2,
    KeyRound,
    LogOut,
    Pencil,
    Plus,
    Repeat,
    Trash2,
} from 'lucide-react';
import type { ComponentType } from 'react';
import Heading from '@/components/heading';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import type { Paginado } from '@/types/parametros';

interface RegistroHistorial {
    id: number;
    usuario: string;
    email: string | null;
    accion: string;
    descripcion: string;
    hospital: string | null;
    ip: string | null;
    fecha: string;
    hora: string;
}

const iconosAccion: Record<string, ComponentType<{ className?: string }>> = {
    'inició sesión': KeyRound,
    'cerró sesión': LogOut,
    'cambió de hospital': Repeat,
    creó: Plus,
    actualizó: Pencil,
    eliminó: Trash2,
};

export default function HistorialIndex({
    registros,
}: {
    registros: Paginado<RegistroHistorial>;
}) {
    return (
        <>
            <Head title="Historial" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Historial de actividad"
                    description="Bitácora de auditoría del aplicativo: quién hizo qué y a qué hora, desde los inicios de sesión hasta cada dato registrado."
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Usuario</th>
                                <th className="p-3 font-medium">Acción</th>
                                <th className="p-3 font-medium">Descripción</th>
                                <th className="p-3 font-medium">Hospital</th>
                                <th className="p-3 text-right font-medium">
                                    Fecha y hora
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {registros.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        Aún no hay actividad registrada.
                                    </td>
                                </tr>
                            )}
                            {registros.data.map((registro) => {
                                const Icono =
                                    iconosAccion[registro.accion] ?? Pencil;

                                return (
                                    <tr
                                        key={registro.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3">
                                            <p className="font-medium">
                                                {registro.usuario}
                                            </p>
                                            {registro.email && (
                                                <p className="text-xs text-muted-foreground">
                                                    {registro.email}
                                                </p>
                                            )}
                                        </td>
                                        <td className="p-3 whitespace-nowrap">
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 capitalize"
                                            >
                                                <Icono className="size-3.5 text-muted-foreground" />
                                                {registro.accion}
                                            </Badge>
                                        </td>
                                        <td className="max-w-96 p-3">
                                            <span title={registro.descripcion}>
                                                {registro.descripcion}
                                            </span>
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {registro.hospital ? (
                                                <span className="flex items-center gap-1.5">
                                                    <Building2 className="size-3.5 shrink-0" />
                                                    <span
                                                        className="max-w-48 truncate"
                                                        title={
                                                            registro.hospital
                                                        }
                                                    >
                                                        {registro.hospital}
                                                    </span>
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="p-3 text-right whitespace-nowrap">
                                            <p className="tabular-nums">
                                                {registro.fecha}
                                            </p>
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                {registro.hora}
                                            </p>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <Paginacion
                    links={registros.links}
                    total={registros.total}
                    from={registros.from}
                    to={registros.to}
                />
            </div>
        </>
    );
}

HistorialIndex.layout = {
    breadcrumbs: [{ title: 'Historial', href: '/historial' }],
};
