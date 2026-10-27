import { Head } from '@inertiajs/react';
import type { CatalogosCirugia } from '@/components/cirugias/formulario-cirugia';
import { FormularioCirugia } from '@/components/cirugias/formulario-cirugia';
import Heading from '@/components/heading';

export default function CirugiasCreate(catalogos: CatalogosCirugia) {
    return (
        <>
            <Head title="Registrar cirugía" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Registrar cirugía"
                    description="La cirugía toma los datos configurados en Parámetros: procedimientos, personal, insumos, equipos y sala."
                />

                <FormularioCirugia
                    catalogos={catalogos}
                    urlEnvio="/cirugias"
                    metodo="post"
                    textoEnviar="Registrar cirugía"
                    hrefCancelar="/cirugias"
                />
            </div>
        </>
    );
}

CirugiasCreate.layout = {
    breadcrumbs: [
        { title: 'Cirugías realizadas', href: '/cirugias' },
        { title: 'Registrar', href: '/cirugias/create' },
    ],
};
