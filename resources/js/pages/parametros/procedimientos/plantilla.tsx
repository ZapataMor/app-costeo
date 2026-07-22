import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import type { CatalogosPlantilla } from '@/components/parametros/forms/plantilla-procedimiento-form';
import { PlantillaProcedimientoForm } from '@/components/parametros/forms/plantilla-procedimiento-form';
import type { PlantillaProcedimiento } from '@/types/cirugias';

type ProcedimientoResumen = {
    id: number;
    codigo_cups: string;
    nombre: string;
    especialidad: string;
    duracion_estimada_minutos: number;
};

export default function ProcedimientoPlantilla({
    procedimiento,
    plantilla,
    ...catalogos
}: {
    procedimiento: ProcedimientoResumen;
    plantilla: PlantillaProcedimiento;
} & CatalogosPlantilla) {
    return (
        <>
            <Head title={`Plantilla · ${procedimiento.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title={`Plantilla de ${procedimiento.nombre}`}
                    description={`${procedimiento.codigo_cups} · ${procedimiento.especialidad}. Lo que se registre aquí vendrá puesto en cada registro de este procedimiento; el digitador solo ajusta lo que se usó de más o de menos.`}
                />
                <PlantillaProcedimientoForm
                    procedimientoId={procedimiento.id}
                    plantilla={plantilla}
                    catalogos={catalogos}
                    hrefCancelar="/parametros/procedimientos"
                />
            </div>
        </>
    );
}

ProcedimientoPlantilla.layout = {
    breadcrumbs: [
        { title: 'Procedimientos', href: '/parametros/procedimientos' },
        { title: 'Plantilla', href: '#' },
    ],
};
