import { Head } from '@inertiajs/react';
import InsumoController from '@/actions/App/Http/Controllers/Parametros/InsumoController';
import Heading from '@/components/heading';
import { InsumoForm } from '@/components/parametros/forms/insumo-form';

export default function InsumosCreate({
    categorias,
    nivelesConfiabilidad,
}: {
    categorias: string[];
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title="Nuevo insumo" />
            <div className="flex flex-col gap-4 p-4">
                <Heading title="Nuevo insumo" description="Registra un medicamento, material o dispositivo con su costo unitario." />
                <InsumoForm
                    action={InsumoController.store.form()}
                    categorias={categorias}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={InsumoController.index.url()}
                />
            </div>
        </>
    );
}

InsumosCreate.layout = {
    breadcrumbs: [
        { title: 'Insumos', href: '/parametros/insumos' },
        { title: 'Nuevo', href: '/parametros/insumos/create' },
    ],
};
