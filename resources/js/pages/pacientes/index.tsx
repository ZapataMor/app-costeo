import { Head, router } from '@inertiajs/react';
import { Search, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { etiquetasRegimen } from '@/components/pacientes/campos-paciente';
import { EditarPacienteModal } from '@/components/pacientes/editar-paciente-modal';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PacienteFila, PaginadoPacientes } from '@/types/pacientes';

export default function PacientesIndex({
    pacientes,
    filtros,
    regimenes,
}: {
    pacientes: PaginadoPacientes;
    filtros: { q: string };
    regimenes: string[];
}) {
    const [busqueda, setBusqueda] = useState(filtros.q);

    const buscar = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/pacientes', { q: busqueda }, { preserveState: true });
    };

    return (
        <>
            <Head title="Pacientes" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    titulo="Pacientes"
                    descripcion="Padrón del hospital activo. El documento se guarda cifrado; se puede buscar por documento exacto o por nombre."
                    accion={
                        <EditarPacienteModal
                            regimenes={regimenes}
                            disparador={
                                <Button type="button">
                                    <UserPlus className="size-4" />
                                    Nuevo paciente
                                </Button>
                            }
                        />
                    }
                />

                <form onSubmit={buscar} className="flex max-w-md gap-2">
                    <Input
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        placeholder="Nombre, apellido o documento completo"
                        aria-label="Buscar paciente"
                    />
                    <Button type="submit" variant="outline">
                        <Search className="size-4" />
                        Buscar
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Documento</th>
                                <th className="p-3 font-medium">Paciente</th>
                                <th className="p-3 font-medium">Régimen</th>
                                <th className="p-3 font-medium">Asegurador</th>
                                <th className="p-3 font-medium">Procedencia</th>
                                <th className="p-3 text-right font-medium">
                                    Procedimientos
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {pacientes.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        {filtros.q !== ''
                                            ? 'Ningún paciente coincide con la búsqueda.'
                                            : 'No hay pacientes registrados todavía.'}
                                    </td>
                                </tr>
                            )}
                            {pacientes.data.map((paciente: PacienteFila) => (
                                <tr
                                    key={paciente.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3 font-mono text-xs">
                                        {paciente.tipo_documento}{' '}
                                        {paciente.documento}
                                    </td>
                                    <td className="p-3">
                                        {paciente.apellidos}, {paciente.nombres}
                                    </td>
                                    <td className="p-3">
                                        {etiquetasRegimen[paciente.regimen] ??
                                            paciente.regimen}
                                    </td>
                                    <td className="p-3">
                                        {paciente.asegurador ?? '—'}
                                    </td>
                                    <td className="p-3 capitalize">
                                        {[paciente.municipio, paciente.zona]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {paciente.cirugias_count}
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <EditarPacienteModal
                                            paciente={paciente}
                                            regimenes={regimenes}
                                        />
                                        {paciente.cirugias_count === 0 ? (
                                            <ConfirmarEliminacion
                                                url={`/pacientes/${paciente.id}`}
                                                descripcion={`Se eliminará a ${paciente.nombres} ${paciente.apellidos} del padrón. Esta acción no se puede deshacer.`}
                                            />
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                                title="Tiene procedimientos registrados"
                                            >
                                                Con historial
                                            </Badge>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion
                    links={pacientes.links}
                    total={pacientes.total}
                    from={pacientes.from}
                    to={pacientes.to}
                />
            </div>
        </>
    );
}

PacientesIndex.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: '/pacientes' }],
};
