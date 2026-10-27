import { useCallback, useMemo, useState } from 'react';
import type { CatalogosCirugia } from '@/components/cirugias/formulario-cirugia';
import { FormularioCirugia } from '@/components/cirugias/formulario-cirugia';
import { ModalSuperpuesto } from '@/components/modal-superpuesto';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { DatosCirugia } from '@/types/cirugias';

export type DatosEdicionCirugia = CatalogosCirugia & {
    cirugia: DatosCirugia & { id: number };
};

/**
 * Corrección de una cirugía, apilada sobre su detalle. Al guardar, el
 * servidor vuelve al listado con el detalle abierto y ya recalculado.
 *
 * Cerrar (X, Escape, clic fuera o Cancelar) no pregunta si no se tocó nada;
 * con cambios sin guardar pide confirmación antes de descartarlos.
 */
export function ModalCorregirCirugia({
    abierto,
    edicion,
    onCerrar,
}: {
    abierto: boolean;
    edicion: DatosEdicionCirugia | null;
    onCerrar: () => void;
}) {
    const [modificado, setModificado] = useState(false);
    const [confirmando, setConfirmando] = useState(false);

    const cerrarDefinitivo = useCallback(() => {
        setConfirmando(false);
        setModificado(false);
        onCerrar();
    }, [onCerrar]);

    const intentarCerrar = useCallback(() => {
        if (modificado) {
            setConfirmando(true);

            return;
        }

        cerrarDefinitivo();
    }, [modificado, cerrarDefinitivo]);

    const id = edicion?.cirugia.id;
    // Sin el id: el formulario solo lleva los campos editables.
    const valoresIniciales = useMemo(() => {
        if (edicion === null) {
            return undefined;
        }

        const valores: Partial<DatosEdicionCirugia['cirugia']> = {
            ...edicion.cirugia,
        };
        delete valores.id;

        return valores as DatosCirugia;
    }, [edicion]);

    return (
        <>
            <ModalSuperpuesto
                abierto={abierto}
                cargando={edicion === null}
                textoCargando="Cargando el formulario…"
                titulo={id ? `Corregir cirugía #${id}` : 'Corregir cirugía'}
                descripcion="Al guardar se recalcula el costo TDABC. Las tarifas congeladas al registrar se conservan; solo lo que agregue ahora toma la tarifa vigente."
                ancho="max-w-6xl"
                onCerrar={intentarCerrar}
            >
                {edicion && (
                    <div
                        // Una cirugía distinta empieza con su propio formulario.
                        key={edicion.cirugia.id}
                        onInputCapture={() => setModificado(true)}
                        onChangeCapture={() => setModificado(true)}
                    >
                        <FormularioCirugia
                            catalogos={edicion}
                            valoresIniciales={valoresIniciales}
                            urlEnvio={`/cirugias/${edicion.cirugia.id}`}
                            metodo="put"
                            textoEnviar="Guardar cambios"
                            onCancelar={intentarCerrar}
                        />
                    </div>
                )}
            </ModalSuperpuesto>

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>¿Cerrar sin guardar?</DialogTitle>
                        <DialogDescription>
                            Se perderán las correcciones que no haya guardado.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmando(false)}
                        >
                            Seguir corrigiendo
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={cerrarDefinitivo}
                        >
                            Cerrar y descartar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
