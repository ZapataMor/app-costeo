import { Plus, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';
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
 * - Botón X: pide confirmación y, si se acepta, descarta lo escrito
 *   (remonta el formulario).
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

    const guardadoConExito = () => {
        setAbierto(false);
        setVersion((v) => v + 1);
    };

    const descartar = () => {
        setConfirmando(false);
        setAbierto(false);
        setVersion((v) => v + 1);
    };

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

            {montado && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label={titulo}
                    className={`fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8 ${abierto ? '' : 'hidden'}`}
                    onClick={() => setAbierto(false)}
                >
                    <div
                        className="w-full max-w-3xl rounded-xl border bg-background shadow-lg"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-start justify-between gap-4 border-b p-4">
                            <div>
                                <h2 className="text-lg font-semibold">{titulo}</h2>
                                {descripcion && <p className="text-sm text-muted-foreground">{descripcion}</p>}
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Cerrar y descartar"
                                onClick={() => setConfirmando(true)}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                        <div key={version} className="p-4">
                            {children(guardadoConExito)}
                        </div>
                    </div>
                </div>
            )}

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>¿Cerrar sin guardar?</DialogTitle>
                        <DialogDescription>
                            Se eliminará la información escrita que no haya sido guardada. Si prefiere conservarla,
                            cierre haciendo clic fuera del formulario y podrá retomarlo después.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmando(false)}>
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
