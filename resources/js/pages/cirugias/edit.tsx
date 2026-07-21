import { Head } from '@inertiajs/react';
import type { CatalogosCirugia } from '@/components/cirugias/formulario-cirugia';
import { FormularioCirugia } from '@/components/cirugias/formulario-cirugia';
import Heading from '@/components/heading';
import type { DatosCirugia } from '@/types/cirugias';

export default function CirugiasEdit({
    cirugia,
    ...catalogos
}: CatalogosCirugia & { cirugia: DatosCirugia & { id: number } }) {
    const { id, ...valoresIniciales } = cirugia;

    return (
        <>
            <Head title={`Corregir procedimiento #${id}`} />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title={`Corregir procedimiento #${id}`}
                    description="Al guardar se recalcula el costo TDABC. Las tarifas congeladas al registrar se conservan; solo lo que agregue ahora toma la tarifa vigente."
                />

                <FormularioCirugia
                    catalogos={catalogos}
                    valoresIniciales={valoresIniciales}
                    urlEnvio={`/cirugias/${id}`}
                    metodo="put"
                    textoEnviar="Guardar cambios"
                    hrefCancelar="/cirugias"
                />
            </div>
        </>
    );
}

CirugiasEdit.layout = {
    breadcrumbs: [
        { title: 'Procedimientos', href: '/cirugias' },
        { title: 'Corregir', href: '/cirugias' },
    ],
};
