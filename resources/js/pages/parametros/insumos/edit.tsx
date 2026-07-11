import { Head } from '@inertiajs/react';
import InsumoController from '@/actions/App/Http/Controllers/Parametros/InsumoController';
import Heading from '@/components/heading';
import { InsumoForm } from '@/components/parametros/forms/insumo-form';
import type { InsumoParam } from '@/types/parametros';

export default function InsumosEdit({
    insumo,
    categorias,
    nivelesConfiabilidad,
}: {
    insumo: InsumoParam;
    categorias: string[];
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title={`Editar insumo · ${insumo.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <Heading title={`Editar insumo`} description={insumo.nombre} />
                <InsumoForm
                    action={InsumoController.update.form(insumo.id)}
                    insumo={insumo}
                    categorias={categorias}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={InsumoController.index.url()}
                />
            </div>
        </>
    );
}

InsumosEdit.layout = {
    breadcrumbs: [
        { title: 'Insumos', href: '/parametros/insumos' },
        { title: 'Editar', href: '#' },
    ],
};
