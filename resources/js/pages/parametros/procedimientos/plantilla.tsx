import { Head, router } from '@inertiajs/react';
import { Wand2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import type { CatalogosPlantilla } from '@/components/parametros/forms/plantilla-procedimiento-form';
import { PlantillaProcedimientoForm } from '@/components/parametros/forms/plantilla-procedimiento-form';
import { Button } from '@/components/ui/button';
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
    cirugiasHistoricas,
    minimoHistorico,
    ...catalogos
}: {
    procedimiento: ProcedimientoResumen;
    plantilla: PlantillaProcedimiento;
    cirugiasHistoricas: number;
    minimoHistorico: number;
} & CatalogosPlantilla) {
    const [deduciendo, setDeduciendo] = useState(false);

    const vacia =
        plantilla.insumos.length === 0 &&
        plantilla.personal.length === 0 &&
        plantilla.equipos.length === 0;

    const puedeDeducir = cirugiasHistoricas >= minimoHistorico;

    const deducir = () => {
        if (
            !vacia &&
            !confirm(
                'Se reemplazará la plantilla actual por la que se deduzca del histórico. ¿Continuar?',
            )
        ) {
            return;
        }

        router.post(
            `/parametros/procedimientos/${procedimiento.id}/plantilla/sugerir`,
            {},
            {
                preserveScroll: true,
                onStart: () => setDeduciendo(true),
                onFinish: () => setDeduciendo(false),
            },
        );
    };

    return (
        <>
            <Head title={`Plantilla · ${procedimiento.nombre}`} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`Plantilla de ${procedimiento.nombre}`}
                        description={`${procedimiento.codigo_cups} · ${procedimiento.especialidad}. Lo que se registre aquí vendrá puesto en cada registro de este procedimiento; el digitador solo ajusta lo que se usó de más o de menos.`}
                    />
                    {/* Escribir el protocolo de cero es justo el trabajo que
                        la aplicación existe para ahorrar: si ya hay cirugías
                        registradas, la plantilla se deduce de ellas. */}
                    <Button
                        type="button"
                        variant={vacia ? 'default' : 'outline'}
                        onClick={deducir}
                        disabled={!puedeDeducir || deduciendo}
                        title={
                            puedeDeducir
                                ? `Se analizarán ${cirugiasHistoricas} cirugías ya registradas`
                                : `Hacen falta al menos ${minimoHistorico} cirugías registradas de este procedimiento (hay ${cirugiasHistoricas})`
                        }
                    >
                        <Wand2 className="size-4" />
                        Deducir del histórico
                    </Button>
                </div>

                {vacia && (
                    <div className="rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
                        {puedeDeducir ? (
                            <>
                                Este procedimiento aún no tiene plantilla, pero
                                ya hay{' '}
                                <strong className="text-foreground">
                                    {cirugiasHistoricas} cirugías registradas
                                </strong>
                                . Use «Deducir del histórico» para partir de lo
                                que de verdad se ha usado, y corrija sobre eso.
                            </>
                        ) : (
                            <>
                                Este procedimiento aún no tiene plantilla. Puede
                                escribirla a mano ahora, o registrar al menos{' '}
                                {minimoHistorico} cirugías y deducirla después
                                del histórico.
                            </>
                        )}
                    </div>
                )}

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
        {
            title: 'Catálogo de procedimientos',
            href: '/parametros/procedimientos',
        },
        { title: 'Plantilla', href: '#' },
    ],
};
