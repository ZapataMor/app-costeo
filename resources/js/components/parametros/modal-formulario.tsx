import { Plus, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Modal para formularios de creación. Reglas de cierre:
 * - Clic fuera del formulario: se oculta pero lo escrito se conserva
 *   (el formulario permanece montado) y reaparece al reabrir.
 * - Botón X: cierra directamente si no hubo cambios. Si el usuario ya
 *   modificó algún campo, pide confirmación antes de descartarlos.
 * - Al guardar con éxito (callback `cerrar` del render prop) se cierra
 *   y se limpia el formulario.
 */
export function ModalFormulario({
    titulo,
    descripcion,
    textoBoton,
    variante = 'default',
    tamanoBoton = 'default',
    claseBoton,
    children,
}: {
    titulo: string;
    descripcion?: string;
    textoBoton: string;
    variante?: 'default' | 'outline';
    tamanoBoton?: 'default' | 'sm';
    claseBoton?: string;
    children: (cerrar: () => void) => ReactNode;
}) {
    const [abierto, setAbierto] = useState(false);
    const [confirmando, setConfirmando] = useState(false);
    // Cambiar la key remonta el formulario y descarta lo escrito.
    const [version, setVersion] = useState(0);
    const [montado, setMontado] = useState(false);
    const [modificado, setModificado] = useState(false);

    const guardadoConExito = () => {
        setAbierto(false);
        setModificado(false);
        setVersion((v) => v + 1);
    };

    const descartar = () => {
        setConfirmando(false);
        setAbierto(false);
        setModificado(false);
        setVersion((v) => v + 1);
    };

    const cerrarDesdeX = () => {
        if (modificado) {
            setConfirmando(true);

            return;
        }

        setAbierto(false);
    };

    useEffect(() => {
        if (!abierto || confirmando) {
            return;
        }

        const cerrarConEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setAbierto(false);
            }
        };

        document.addEventListener('keydown', cerrarConEscape);

        return () => document.removeEventListener('keydown', cerrarConEscape);
    }, [abierto, confirmando]);

    const destinoPortal =
        typeof document !== 'undefined'
            ? document.querySelector<HTMLElement>('.sicoq-app')
            : null;

    const modal =
        montado && destinoPortal
            ? createPortal(
                  <div
                      role="dialog"
                      aria-modal="true"
                      aria-label={titulo}
                      className={`sicoq-modal fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto bg-transparent p-4 backdrop-blur-[12px] sm:p-8 ${abierto ? '' : 'hidden'}`}
                      onClick={() => setAbierto(false)}
                  >
                      <div
                          className="max-h-[calc(100svh-2rem)] w-full max-w-3xl overflow-y-auto rounded-2xl border border-[#5B687C]/20 bg-[#FBFAF9] shadow-[0_28px_70px_-24px_rgba(23,24,31,.52)] dark:bg-[#23242E]"
                          onClick={(e) => e.stopPropagation()}
                      >
                          <div className="flex items-start justify-between gap-4 border-b p-4">
                              <div>
                                  <h2 className="text-lg font-semibold">
                                      {titulo}
                                  </h2>
                                  {descripcion && (
                                      <p className="text-sm text-muted-foreground">
                                          {descripcion}
                                      </p>
                                  )}
                              </div>
                              <Button
                                  variant="ghost"
                                  size="icon"
                                  aria-label="Cerrar y descartar"
                                  onClick={cerrarDesdeX}
                              >
                                  <X className="size-4" />
                              </Button>
                          </div>
                          <div
                              key={version}
                              className="p-4"
                              onInputCapture={() => setModificado(true)}
                              onChangeCapture={() => setModificado(true)}
                              onClickCapture={(event) => {
                                  const target = event.target as HTMLElement;

                                  if (
                                      target.closest(
                                          '[role="checkbox"], [role="switch"], [role="radio"]',
                                      )
                                  ) {
                                      setModificado(true);
                                  }
                              }}
                          >
                              {children(guardadoConExito)}
                          </div>
                      </div>
                  </div>,
                  destinoPortal,
              )
            : null;

    return (
        <>
            <Button
                variant={variante}
                size={tamanoBoton}
                className={claseBoton}
                onClick={() => {
                    setMontado(true);
                    setAbierto(true);
                }}
            >
                <Plus className="size-4" />
                {textoBoton}
            </Button>

            {modal}

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>¿Cerrar sin guardar?</DialogTitle>
                        <DialogDescription>
                            Se eliminará la información escrita que no haya sido
                            guardada. Si prefiere conservarla, cierre haciendo
                            clic fuera del formulario y podrá retomarlo después.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmando(false)}
                        >
                            Seguir editando
                        </Button>
                        <Button variant="destructive" onClick={descartar}>
                            Cerrar y descartar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
