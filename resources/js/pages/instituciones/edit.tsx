import { Head } from '@inertiajs/react';
import InstitucionController from '@/actions/App/Http/Controllers/Instituciones/InstitucionController';
import Heading from '@/components/heading';
import { InstitucionForm } from '@/components/instituciones/institucion-form';
import type { Institucion } from '@/types/instituciones';

export default function InstitucionesEdit({
    institucion,
    nivelesComplejidad,
}: {
    institucion: Institucion;
    nivelesComplejidad: string[];
}) {
    return (
        <>
            <Head title={`Editar · ${institucion.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Editar institución"
                    description={institucion.nombre}
                />
                <InstitucionForm
                    action={InstitucionController.update.form(institucion.id)}
                    institucion={institucion}
                    nivelesComplejidad={nivelesComplejidad}
                    hrefCancelar={InstitucionController.show.url(
                        institucion.id,
                    )}
                />
            </div>
        </>
    );
}

InstitucionesEdit.layout = {
    breadcrumbs: [
        { title: 'Instituciones', href: '/instituciones' },
        { title: 'Editar', href: '#' },
    ],
};
