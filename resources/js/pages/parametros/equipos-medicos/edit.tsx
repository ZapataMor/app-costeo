import { Head } from '@inertiajs/react';
import EquipoMedicoController from '@/actions/App/Http/Controllers/Parametros/EquipoMedicoController';
import Heading from '@/components/heading';
import { EquipoMedicoForm } from '@/components/parametros/forms/equipo-medico-form';
import type { EquipoMedicoParam } from '@/types/parametros';

export default function EquiposMedicosEdit({
    equipo,
    nivelesConfiabilidad,
}: {
    equipo: EquipoMedicoParam;
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title={`Editar equipo · ${equipo.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Editar equipo médico"
                    description={equipo.nombre}
                />
                <EquipoMedicoForm
                    action={EquipoMedicoController.update.form(equipo.id)}
                    equipo={equipo}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={EquipoMedicoController.index.url()}
                />
            </div>
        </>
    );
}

EquiposMedicosEdit.layout = {
    breadcrumbs: [
        { title: 'Equipos médicos', href: '/parametros/equipos-medicos' },
        { title: 'Editar', href: '#' },
    ],
};
