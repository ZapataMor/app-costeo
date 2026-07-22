import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * Botón de eliminación con diálogo de confirmación; hace DELETE a la URL dada.
 */
export function ConfirmarEliminacion({
    url,
    descripcion,
}: {
    url: string;
    descripcion: string;
}) {
    const [abierto, setAbierto] = useState(false);
    const [procesando, setProcesando] = useState(false);

    const eliminar = () => {
        router.delete(url, {
            preserveScroll: true,
            onStart: () => setProcesando(true),
            onFinish: () => {
                setProcesando(false);
                setAbierto(false);
            },
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={setAbierto}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Eliminar">
                    <Trash2 className="size-4 text-destructive" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Eliminar este registro?</DialogTitle>
                    <DialogDescription>{descripcion}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setAbierto(false)}>
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={eliminar}
                        disabled={procesando}
                    >
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
