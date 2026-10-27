import { LoaderCircle, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@/components/ui/button';

/**
 * Modal sobre la página con la presentación de los formularios de
 * Parámetros (ModalFormulario): fondo desenfocado y tarjeta centrada.
 *
 * Desplazamiento: mientras está abierto, ni la ventana ni el contenedor de
 * la página (`.sicoq-page-scroll`) se desplazan; la cabecera queda fija y la
 * única barra es la del cuerpo. Se pueden apilar (detalle → corregir): cada
 * uno tapa al de abajo y Escape solo cierra el de arriba.
 */
export function ModalSuperpuesto({
    abierto,
    titulo,
    descripcion,
    acciones,
    cargando = false,
    textoCargando = 'Cargando…',
    ancho = 'max-w-5xl',
    onCerrar,
    children,
}: {
    abierto: boolean;
    titulo: string;
    descripcion?: string;
    /** Botones a la izquierda de la X. */
    acciones?: ReactNode;
    /** Sin datos todavía: muestra un indicador en vez del cuerpo. */
    cargando?: boolean;
    textoCargando?: string;
    ancho?: string;
    onCerrar: () => void;
    children?: ReactNode;
}) {
    const fondo = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!abierto) {
            return;
        }

        const cerrarConEscape = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') {
                return;
            }

            // Un diálogo de Radix encima (confirmar, buscador, nuevo
            // paciente) atiende su propio Escape.
            const hayDialogoRadix = document.querySelector(
                '[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"]',
            );
            const modales = document.querySelectorAll(
                '.sicoq-modal:not(.hidden)',
            );
            const esElDeArriba = modales[modales.length - 1] === fondo.current;

            if (!hayDialogoRadix && esElDeArriba) {
                onCerrar();
            }
        };

        document.addEventListener('keydown', cerrarConEscape);

        const bloqueados = [
            document.documentElement,
            document.body,
            ...document.querySelectorAll<HTMLElement>('.sicoq-page-scroll'),
        ];
        const anteriores = bloqueados.map((el) => el.style.overflow);
        bloqueados.forEach((el) => (el.style.overflow = 'hidden'));

        return () => {
            document.removeEventListener('keydown', cerrarConEscape);
            // Al apilar, el de arriba se cierra primero y restaura el
            // «hidden» que dejó el de abajo.
            bloqueados.forEach((el, i) => (el.style.overflow = anteriores[i]));
        };
    }, [abierto, onCerrar]);

    const destinoPortal =
        typeof document !== 'undefined'
            ? document.querySelector<HTMLElement>('.sicoq-app')
            : null;

    if (!abierto || !destinoPortal) {
        return null;
    }

    const botonCerrar = (
        <Button
            variant="ghost"
            size="icon"
            aria-label="Cerrar"
            onClick={onCerrar}
        >
            <X className="size-4" />
        </Button>
    );

    return createPortal(
        <div
            ref={fondo}
            role="dialog"
            aria-modal="true"
            aria-label={titulo}
            // z-40: los diálogos y menús de Radix (z-50 o más, en el body)
            // quedan encima. Sin overflow propio: la tarjeta cabe siempre
            // (max-h descuenta el padding).
            className="sicoq-modal fixed inset-0 z-40 flex items-center justify-center overflow-hidden bg-transparent p-4 backdrop-blur-[12px] sm:p-8"
            onClick={onCerrar}
        >
            <div
                className={`flex max-h-[calc(100svh-2rem)] w-full flex-col overflow-hidden rounded-2xl border border-[#5B687C]/20 bg-[#FBFAF9] shadow-[0_28px_70px_-24px_rgba(23,24,31,.52)] sm:max-h-[calc(100svh-4rem)] dark:bg-[#23242E] ${ancho}`}
                onClick={(e) => e.stopPropagation()}
            >
                {cargando ? (
                    <div className="flex items-center justify-between gap-4 p-4">
                        <span className="flex items-center gap-2 text-sm text-muted-foreground">
                            <LoaderCircle className="size-4 animate-spin" />
                            {textoCargando}
                        </span>
                        {botonCerrar}
                    </div>
                ) : (
                    <>
                        <div className="flex shrink-0 flex-wrap items-start justify-between gap-3 border-b p-4">
                            <div className="min-w-0">
                                <h2 className="text-lg font-semibold">
                                    {titulo}
                                </h2>
                                {descripcion && (
                                    <p className="text-sm text-muted-foreground">
                                        {descripcion}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {acciones}
                                {botonCerrar}
                            </div>
                        </div>
                        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4">
                            {children}
                        </div>
                    </>
                )}
            </div>
        </div>,
        destinoPortal,
    );
}
