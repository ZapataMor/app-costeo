import { Head } from '@inertiajs/react';
import ProcedimientoQuirurgicoController from '@/actions/App/Http/Controllers/Parametros/ProcedimientoQuirurgicoController';
import Heading from '@/components/heading';
import { ProcedimientoForm } from '@/components/parametros/forms/procedimiento-form';
import type { ProcedimientoParam } from '@/types/parametros';

export default function ProcedimientosEdit({
    procedimiento,
    complejidades,
    nivelesConfiabilidad,
}: {
    procedimiento: ProcedimientoParam;
    complejidades: string[];
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title={`Editar procedimiento · ${procedimiento.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Editar procedimiento quirúrgico"
                    description={procedimiento.nombre}
                />
                <ProcedimientoForm
                    action={ProcedimientoQuirurgicoController.update.form(
                        procedimiento.id,
                    )}
                    procedimiento={procedimiento}
                    complejidades={complejidades}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={ProcedimientoQuirurgicoController.index.url()}
                />
            </div>
        </>
    );
}

ProcedimientosEdit.layout = {
    breadcrumbs: [
        { title: 'Procedimientos', href: '/parametros/procedimientos' },
        { title: 'Editar', href: '#' },
    ],
};
