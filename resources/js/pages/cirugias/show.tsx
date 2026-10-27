import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CirugiaController from '@/actions/App/Http/Controllers/Cirugias/CirugiaController';
import type { DatosDetalleCirugia } from '@/components/cirugias/detalle-cirugia';
import {
    AccionesCirugia,
    DetalleCirugia,
    descripcionCirugia,
    tituloCirugia,
} from '@/components/cirugias/detalle-cirugia';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

/**
 * Página propia del detalle: el listado lo abre en un modal, pero esta URL
 * sigue sirviendo para enlaces externos (alertas, comunidades) y nueva pestaña.
 */
export default function CirugiasShow(props: DatosDetalleCirugia) {
    const { cirugia, costo } = props;

    return (
        <>
            <Head title={`Cirugía #${cirugia.id}`} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={tituloCirugia(cirugia)}
                        description={descripcionCirugia(cirugia)}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <Link href={CirugiaController.index.url()}>
                                <ArrowLeft className="size-4" />
                                Volver
                            </Link>
                        </Button>
                        <AccionesCirugia cirugia={cirugia} costo={costo} />
                    </div>
                </div>

                <DetalleCirugia {...props} />
            </div>
        </>
    );
}

CirugiasShow.layout = {
    breadcrumbs: [
        { title: 'Cirugías realizadas', href: '/cirugias' },
        { title: 'Detalle de la cirugía', href: '#' },
    ],
};
