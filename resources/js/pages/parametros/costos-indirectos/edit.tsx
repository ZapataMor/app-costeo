import { Head } from '@inertiajs/react';
import ConceptoCostoIndirectoController from '@/actions/App/Http/Controllers/Parametros/ConceptoCostoIndirectoController';
import Heading from '@/components/heading';
import { ConceptoCostoIndirectoForm } from '@/components/parametros/forms/concepto-costo-indirecto-form';
import type {
    BaseAsignacionOpcion,
    CategoriaCifOpcion,
    ConceptoCostoIndirectoParam,
} from '@/types/parametros';

export default function CostosIndirectosEdit({
    concepto,
    categorias,
    basesAsignacion,
    nivelesConfiabilidad,
}: {
    concepto: ConceptoCostoIndirectoParam;
    categorias: CategoriaCifOpcion[];
    basesAsignacion: BaseAsignacionOpcion[];
    nivelesConfiabilidad: string[];
}) {
    return (
        <>
            <Head title={`Editar concepto · ${concepto.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Editar concepto de costo indirecto"
                    description={concepto.nombre}
                />
                <ConceptoCostoIndirectoForm
                    action={ConceptoCostoIndirectoController.update.form(
                        concepto.id,
                    )}
                    concepto={concepto}
                    categorias={categorias}
                    basesAsignacion={basesAsignacion}
                    nivelesConfiabilidad={nivelesConfiabilidad}
                    hrefCancelar={ConceptoCostoIndirectoController.index.url()}
                />
            </div>
        </>
    );
}

CostosIndirectosEdit.layout = {
    breadcrumbs: [
        { title: 'Costos indirectos', href: '/parametros/costos-indirectos' },
        { title: 'Editar', href: '#' },
    ],
};
