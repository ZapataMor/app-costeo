import { Head } from '@inertiajs/react';
import RecursoHumanoController from '@/actions/App/Http/Controllers/Parametros/RecursoHumanoController';
import Heading from '@/components/heading';
import { RecursoHumanoForm } from '@/components/parametros/forms/recurso-humano-form';

export default function RecursosHumanosCreate({
    roles,
    nivelesConfiabilidad,
}: {
    roles: string[];
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title="Nuevo recurso humano" />
            <div className="flex flex-col gap-4 p-4">
                <Heading title="Nuevo recurso humano" description="Registra el personal quirúrgico y su estructura salarial." />
                <RecursoHumanoForm
                    action={RecursoHumanoController.store.form()}
                    roles={roles}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={RecursoHumanoController.index.url()}
                />
            </div>
        </>
    );
}

RecursosHumanosCreate.layout = {
    breadcrumbs: [
        { title: 'Recursos humanos', href: '/parametros/recursos-humanos' },
        { title: 'Nuevo', href: '/parametros/recursos-humanos/create' },
    ],
};
