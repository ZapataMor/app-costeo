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
 * Cierre de un procedimiento abierto, en los dos pasos que tiene el ciclo:
 *
 *   sala  → se registra la salida de quirófano; queda «en recuperación».
 *   ciclo → se registra el egreso de recuperación; queda «realizada» y se costea.
 *
 * El paso lo decide el servidor a partir de lo que el registro ya tiene; aquí
 * solo se pide la marca que corresponde. Separarlos evita el dato falso de dar
 * por terminado el ciclo mientras el paciente sigue en el hospital.
 */
const PASOS = {
    sala: {
        titulo: 'Registrar salida de sala',
        etiqueta: 'Hora de salida de sala',
        campo: 'hora_fin',
        boton: 'Registrar salida',
        nota: 'Quedará «en recuperación». Se costeará cuando registres el egreso del paciente.',
    },
    ciclo: {
        titulo: 'Cerrar el ciclo',
        etiqueta: 'Hora de egreso de recuperación',
        campo: 'hora_salida_recuperacion',
        boton: 'Cerrar y costear',
        nota: 'Quedará «realizada» y se costeará automáticamente.',
    },
} as const;

export function CerrarCirugiaModal({
    cirugiaId,
    paso,
    horaInicio,
    horaFin,
}: {
    cirugiaId: number;
    paso: 'sala' | 'ciclo';
    horaInicio: string;
    horaFin: string | null;
}) {
    const [abierto, setAbierto] = useState(false);
    const [valor, setValor] = useState('');
    const [error, setError] = useState<string>();
    const [guardando, setGuardando] = useState(false);

    const config = PASOS[paso];
    // La marca nueva no puede ser anterior a la que la precede en el ciclo.
    const minimo = paso === 'sala' ? horaInicio : (horaFin ?? horaInicio);

    const enviar = () => {
        router.patch(
            `/cirugias/${cirugiaId}/cerrar`,
            { [config.campo]: valor },
            {
                preserveScroll: true,
                onStart: () => setGuardando(true),
                onError: (errores) => setError(errores[config.campo]),
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
                aria-label={config.titulo}
                title={config.titulo}
                onClick={() => {
                    setValor('');
                    setError(undefined);
                    setAbierto(true);
                }}
            >
                <CircleCheckBig className="size-4" />
            </Button>

            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{config.titulo}</DialogTitle>
                        <DialogDescription>
                            {paso === 'sala'
                                ? `Entró a sala el ${horaInicio.replace('T', ' a las ')}.`
                                : `Salió de sala el ${(horaFin ?? horaInicio).replace('T', ' a las ')}.`}{' '}
                            {config.nota}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="marca_cierre">{config.etiqueta}</Label>
                        <Input
                            id="marca_cierre"
                            type="datetime-local"
                            value={valor}
                            min={minimo}
                            onChange={(e) => setValor(e.target.value)}
                        />
                        <InputError message={error} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="button"
                            onClick={enviar}
                            disabled={guardando || valor === ''}
                        >
                            {config.boton}
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
