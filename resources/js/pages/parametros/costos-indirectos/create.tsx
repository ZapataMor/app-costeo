import { Head } from '@inertiajs/react';
import ConceptoCostoIndirectoController from '@/actions/App/Http/Controllers/Parametros/ConceptoCostoIndirectoController';
import Heading from '@/components/heading';
import { ConceptoCostoIndirectoForm } from '@/components/parametros/forms/concepto-costo-indirecto-form';
import type {
    BaseAsignacionOpcion,
    CapacidadesCif,
    CategoriaCifOpcion,
} from '@/types/parametros';

export default function CostosIndirectosCreate({
    categorias,
    basesAsignacion,
    nivelesConfiabilidad,
    capacidades,
}: {
    categorias: CategoriaCifOpcion[];
    basesAsignacion: BaseAsignacionOpcion[];
    nivelesConfiabilidad: string[];
    capacidades: CapacidadesCif;
}) {
    return (
        <>
            <Head title="Nuevo concepto de costo indirecto" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Nuevo concepto de costo indirecto"
                    description="Bolsa de costo con su monto mensual, inductor y vigencia. Nace inactiva."
                />
                <ConceptoCostoIndirectoForm
                    action={ConceptoCostoIndirectoController.store.form()}
                    categorias={categorias}
                    basesAsignacion={basesAsignacion}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    capacidades={capacidades}
                    hrefCancelar={ConceptoCostoIndirectoController.index.url()}
                />
            </div>
        </>
    );
}

CostosIndirectosCreate.layout = {
    breadcrumbs: [
        { title: 'Costos indirectos', href: '/parametros/costos-indirectos' },
        { title: 'Nuevo', href: '/parametros/costos-indirectos/create' },
    ],
};
