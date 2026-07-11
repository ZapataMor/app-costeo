import { Head } from '@inertiajs/react';
import EquipoMedicoController from '@/actions/App/Http/Controllers/Parametros/EquipoMedicoController';
import Heading from '@/components/heading';
import { EquipoMedicoForm } from '@/components/parametros/forms/equipo-medico-form';

export default function EquiposMedicosCreate({ nivelesConfiabilidad }: { nivelesConfiabilidad: string[] }) {
    return (
        <>
            <Head title="Nuevo equipo médico" />
            <div className="flex flex-col gap-4 p-4">
                <Heading title="Nuevo equipo médico" description="Registra un equipo con su costo por hora de uso." />
                <EquipoMedicoForm
                    action={EquipoMedicoController.store.form()}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={EquipoMedicoController.index.url()}
                />
            </div>
        </>
    );
}

EquiposMedicosCreate.layout = {
    breadcrumbs: [
        { title: 'Equipos médicos', href: '/parametros/equipos-medicos' },
        { title: 'Nuevo', href: '/parametros/equipos-medicos/create' },
    ],
};
