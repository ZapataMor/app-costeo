import { Head } from '@inertiajs/react';
import RecursoHumanoController from '@/actions/App/Http/Controllers/Parametros/RecursoHumanoController';
import Heading from '@/components/heading';
import { RecursoHumanoForm } from '@/components/parametros/forms/recurso-humano-form';
import type { RecursoHumanoParam } from '@/types/parametros';

export default function RecursosHumanosEdit({
    recurso,
    roles,
    nivelesConfiabilidad,
}: {
    recurso: RecursoHumanoParam;
    roles: string[];
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title={`Editar recurso · ${recurso.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Editar recurso humano"
                    description={recurso.nombre}
                />
                <RecursoHumanoForm
                    action={RecursoHumanoController.update.form(recurso.id)}
                    recurso={recurso}
                    roles={roles}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={RecursoHumanoController.index.url()}
                />
            </div>
        </>
    );
}

RecursosHumanosEdit.layout = {
    breadcrumbs: [
        { title: 'Recursos humanos', href: '/parametros/recursos-humanos' },
        { title: 'Editar', href: '#' },
    ],
};
