import { router } from '@inertiajs/react';
import { CircleCheckBig } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Cierre rápido de un procedimiento abierto: pide solo la hora de
 * finalización, lo marca como realizado y dispara el costeo.
 *
 * Es la salida al flujo real de quirófano —se registra mientras la cirugía
 * ocurre y se completa al terminar—, que antes dejaba el registro varado
 * fuera de los indicadores.
 */
export function CerrarCirugiaModal({
    cirugiaId,
    horaInicio,
}: {
    cirugiaId: number;
    horaInicio: string;
}) {
    const [abierto, setAbierto] = useState(false);
    const [horaFin, setHoraFin] = useState('');
    const [error, setError] = useState<string>();
    const [guardando, setGuardando] = useState(false);

    const cerrar = () => {
        router.patch(
            `/cirugias/${cirugiaId}/cerrar`,
            { hora_fin: horaFin },
            {
                preserveScroll: true,
                onStart: () => setGuardando(true),
                onError: (errores) => setError(errores.hora_fin),
                onSuccess: () => {
                    setError(undefined);
                    setAbierto(false);
                },
                onFinish: () => setGuardando(false),
            },
        );
    };

    return (
        <>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Completar y cerrar"
                title="Completar y cerrar"
                onClick={() => {
                    setHoraFin('');
                    setAbierto(true);
                }}
            >
                <CircleCheckBig className="size-4" />
            </Button>

            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Completar y cerrar</DialogTitle>
                        <DialogDescription>
                            Quedará en estado «realizada» y se costeará
                            automáticamente. Empezó el{' '}
                            {horaInicio.replace('T', ' a las ')}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="hora_fin_cierre">
                            Hora de finalización
                        </Label>
                        <Input
                            id="hora_fin_cierre"
                            type="datetime-local"
                            value={horaFin}
                            min={horaInicio}
                            onChange={(e) => setHoraFin(e.target.value)}
                        />
                        <InputError message={error} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="button"
                            onClick={cerrar}
                            disabled={guardando || horaFin === ''}
                        >
                            Cerrar y costear
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAbierto(false)}
                        >
                            Cancelar
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
