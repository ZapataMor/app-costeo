import { Head } from '@inertiajs/react';
import type { CatalogosCirugia } from '@/components/cirugias/formulario-cirugia';
import { FormularioCirugia } from '@/components/cirugias/formulario-cirugia';
import Heading from '@/components/heading';

export default function CirugiasCreate(catalogos: CatalogosCirugia) {
    return (
        <>
            <Head title="Registrar procedimiento" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Registrar procedimiento"
                    description="El procedimiento consume los parámetros de Capa 1: procedimientos, personal, insumos, equipos y sala."
                />

                <FormularioCirugia
                    catalogos={catalogos}
                    urlEnvio="/cirugias"
                    metodo="post"
                    textoEnviar="Registrar procedimiento"
                    hrefCancelar="/cirugias"
                />
            </div>
        </>
    );
}

CirugiasCreate.layout = {
    breadcrumbs: [
        { title: 'Procedimientos', href: '/cirugias' },
        { title: 'Registrar', href: '/cirugias/create' },
    ],
};
