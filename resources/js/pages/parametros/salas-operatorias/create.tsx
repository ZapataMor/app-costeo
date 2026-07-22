import { Head } from '@inertiajs/react';
import SalaOperatoriaController from '@/actions/App/Http/Controllers/Parametros/SalaOperatoriaController';
import Heading from '@/components/heading';
import { SalaOperatoriaForm } from '@/components/parametros/forms/sala-operatoria-form';

export default function SalasOperatoriasCreate({
    nivelesConfiabilidad,
}: {
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title="Nueva sala operatoria" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Nueva sala operatoria"
                    description="Registra una sala con su costo por hora de funcionamiento."
                />
                <SalaOperatoriaForm
                    action={SalaOperatoriaController.store.form()}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={SalaOperatoriaController.index.url()}
                />
            </div>
        </>
    );
}

SalasOperatoriasCreate.layout = {
    breadcrumbs: [
        { title: 'Salas operatorias', href: '/parametros/salas-operatorias' },
        { title: 'Nueva', href: '/parametros/salas-operatorias/create' },
    ],
};
