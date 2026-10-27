import type { DatosDetalleCirugia } from '@/components/cirugias/detalle-cirugia';
import {
    AccionesCirugia,
    DetalleCirugia,
    descripcionCirugia,
    tituloCirugia,
} from '@/components/cirugias/detalle-cirugia';
import { ModalSuperpuesto } from '@/components/modal-superpuesto';

/**
 * Detalle de una cirugía sobre el listado. Se abre al instante con un
 * indicador de carga mientras llegan los datos. «Corregir» no navega: pide
 * el modal de corrección, que se apila encima.
 */
export function ModalDetalleCirugia({
    abierto,
    detalle,
    onCerrar,
    onCorregir,
}: {
    abierto: boolean;
    detalle: DatosDetalleCirugia | null;
    onCerrar: () => void;
    onCorregir: () => void;
}) {
    return (
        <ModalSuperpuesto
            abierto={abierto}
            cargando={detalle === null}
            textoCargando="Cargando la cirugía…"
            titulo={detalle ? tituloCirugia(detalle.cirugia) : 'Cirugía'}
            descripcion={
                detalle ? descripcionCirugia(detalle.cirugia) : undefined
            }
            acciones={
                detalle && (
                    <AccionesCirugia
                        cirugia={detalle.cirugia}
                        costo={detalle.costo}
                        onCorregir={onCorregir}
                    />
                )
            }
            onCerrar={onCerrar}
        >
            {detalle && (
                // La key reinicia lo plegado al cambiar de cirugía.
                <div key={detalle.cirugia.id} className="flex flex-col gap-4">
                    <DetalleCirugia {...detalle} />
                </div>
            )}
        </ModalSuperpuesto>
    );
}
