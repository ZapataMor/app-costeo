import { Head } from '@inertiajs/react';
import ProcedimientoQuirurgicoController from '@/actions/App/Http/Controllers/Parametros/ProcedimientoQuirurgicoController';
import Heading from '@/components/heading';
import { ProcedimientoForm } from '@/components/parametros/forms/procedimiento-form';

export default function ProcedimientosCreate({
    complejidades,
    nivelesConfiabilidad,
}: {
    complejidades: string[];
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title="Nuevo procedimiento" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Nuevo procedimiento quirúrgico"
                    description="Registra el protocolo con su código CUPS y duración estimada."
                />
                <ProcedimientoForm
                    action={ProcedimientoQuirurgicoController.store.form()}
                    complejidades={complejidades}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={ProcedimientoQuirurgicoController.index.url()}
                />
            </div>
        </>
    );
}

ProcedimientosCreate.layout = {
    breadcrumbs: [
        {
            title: 'Catálogo de procedimientos',
            href: '/parametros/procedimientos',
        },
        { title: 'Nuevo', href: '/parametros/procedimientos/create' },
    ],
};
