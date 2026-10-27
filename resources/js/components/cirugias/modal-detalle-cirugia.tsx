import { LoaderCircle, X } from 'lucide-react';
import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import type { DatosDetalleCirugia } from '@/components/cirugias/detalle-cirugia';
import {
    AccionesCirugia,
    DetalleCirugia,
    descripcionCirugia,
    tituloCirugia,
} from '@/components/cirugias/detalle-cirugia';
import { Button } from '@/components/ui/button';

/**
 * Detalle de una cirugía sobre el listado, con la misma presentación que
 * los formularios de Parámetros (ModalFormulario): fondo desenfocado,
 * tarjeta centrada y cierre con X, Escape o clic fuera. Mientras llegan los
 * datos muestra un indicador de carga.
 */
export function ModalDetalleCirugia({
    abierto,
    detalle,
    onCerrar,
}: {
    abierto: boolean;
    detalle: DatosDetalleCirugia | null;
    onCerrar: () => void;
}) {
    useEffect(() => {
        if (!abierto) {
            return;
        }

        const cerrarConEscape = (event: KeyboardEvent) => {
            // Un diálogo encima (confirmar eliminación) atiende su propio
            // Escape: el detalle no debe cerrarse con él.
            const hayDialogoEncima = document.querySelector(
                '[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"]',
            );

            if (event.key === 'Escape' && !hayDialogoEncima) {
                onCerrar();
            }
        };

        document.addEventListener('keydown', cerrarConEscape);

        return () => document.removeEventListener('keydown', cerrarConEscape);
    }, [abierto, onCerrar]);

    const destinoPortal =
        typeof document !== 'undefined'
            ? document.querySelector<HTMLElement>('.sicoq-app')
            : null;

    if (!abierto || !destinoPortal) {
        return null;
    }

    return createPortal(
        <div
            role="dialog"
            aria-modal="true"
            aria-label={detalle ? tituloCirugia(detalle.cirugia) : 'Cirugía'}
            // z-40: los diálogos de Radix (z-50, en el body) quedan encima.
            className="sicoq-modal fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-transparent p-4 backdrop-blur-[12px] sm:p-8"
            onClick={onCerrar}
        >
            <div
                className="max-h-[calc(100svh-2rem)] w-full max-w-5xl overflow-y-auto rounded-2xl border border-[#5B687C]/20 bg-[#FBFAF9] shadow-[0_28px_70px_-24px_rgba(23,24,31,.52)] dark:bg-[#23242E]"
                onClick={(e) => e.stopPropagation()}
            >
                {detalle ? (
                    <>
                        <div className="sticky top-0 z-10 flex flex-wrap items-start justify-between gap-3 border-b bg-inherit p-4">
                            <div className="min-w-0">
                                <h2 className="text-lg font-semibold">
                                    {tituloCirugia(detalle.cirugia)}
                                </h2>
                                {descripcionCirugia(detalle.cirugia) && (
                                    <p className="text-sm text-muted-foreground">
                                        {descripcionCirugia(detalle.cirugia)}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <AccionesCirugia
                                    cirugia={detalle.cirugia}
                                    costo={detalle.costo}
                                />
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Cerrar"
                                    onClick={onCerrar}
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        </div>
                        {/* La key reinicia lo plegado al cambiar de cirugía. */}
                        <div
                            key={detalle.cirugia.id}
                            className="flex flex-col gap-4 p-4"
                        >
                            <DetalleCirugia {...detalle} />
                        </div>
                    </>
                ) : (
                    <div className="flex items-center justify-between gap-4 p-4">
                        <span className="flex items-center gap-2 text-sm text-muted-foreground">
                            <LoaderCircle className="size-4 animate-spin" />
                            Cargando la cirugía…
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Cerrar"
                            onClick={onCerrar}
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                )}
            </div>
        </div>,
        destinoPortal,
    );
}
