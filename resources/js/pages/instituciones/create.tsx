import { Head } from '@inertiajs/react';
import InstitucionController from '@/actions/App/Http/Controllers/Instituciones/InstitucionController';
import Heading from '@/components/heading';
import { InstitucionForm } from '@/components/instituciones/institucion-form';

export default function InstitucionesCreate({
    nivelesComplejidad,
}: {
    nivelesComplejidad: string[];
}) {
    return (
        <>
            <Head title="Nueva institución" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Nueva institución"
                    description="Registra un hospital o centro médico. Al crearlo podrás agregarle su administrador y sus digitadores."
                />
                <InstitucionForm
                    action={InstitucionController.store.form()}
                    nivelesComplejidad={nivelesComplejidad}
                    hrefCancelar={InstitucionController.index.url()}
                />
            </div>
        </>
    );
}

InstitucionesCreate.layout = {
    breadcrumbs: [
        { title: 'Instituciones', href: '/instituciones' },
        { title: 'Nueva', href: '/instituciones/create' },
    ],
};
