import { Head } from '@inertiajs/react';
import SalaOperatoriaController from '@/actions/App/Http/Controllers/Parametros/SalaOperatoriaController';
import Heading from '@/components/heading';
import { SalaOperatoriaForm } from '@/components/parametros/forms/sala-operatoria-form';
import type { SalaOperatoriaParam } from '@/types/parametros';

export default function SalasOperatoriasEdit({
    sala,
    nivelesConfiabilidad,
}: {
    sala: SalaOperatoriaParam;
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title={`Editar sala · ${sala.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Editar sala operatoria"
                    description={sala.nombre}
                />
                <SalaOperatoriaForm
                    action={SalaOperatoriaController.update.form(sala.id)}
                    sala={sala}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={SalaOperatoriaController.index.url()}
                />
            </div>
        </>
    );
}

SalasOperatoriasEdit.layout = {
    breadcrumbs: [
        { title: 'Salas operatorias', href: '/parametros/salas-operatorias' },
        { title: 'Editar', href: '#' },
    ],
};
