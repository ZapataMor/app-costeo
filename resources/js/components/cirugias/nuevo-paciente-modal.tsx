import { router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { DatosPaciente } from '@/components/pacientes/campos-paciente';
import {
    CamposPaciente,
    pacienteVacio,
} from '@/components/pacientes/campos-paciente';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Alta rápida de paciente sin salir del registro del procedimiento: el caso
 * normal es que el paciente llegue por primera vez, y obligar a abandonar el
 * formulario (perdiendo lo escrito) para darlo de alta rompía la captura.
 *
 * Al guardar, el backend recarga el catálogo de pacientes y devuelve el id
 * del recién creado para dejarlo ya seleccionado.
 */
export function NuevoPacienteModal({
    regimenes,
    onCreado,
}: {
    regimenes: string[];
    onCreado: (pacienteId: string) => void;
}) {
    const [abierto, setAbierto] = useState(false);
    const [datos, setDatos] = useState<DatosPaciente>(pacienteVacio);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);

    const guardar = () => {
        // Escucha acotada al envío: el controlador devuelve el id del
        // paciente creado en el flash para poderlo autoseleccionar.
        const dejarDeEscuchar = router.on('flash', (event) => {
            const id = (event as CustomEvent).detail?.flash?.pacienteCreadoId;

            if (id) {
                onCreado(String(id));
            }
        });

        router.post('/cirugias/pacientes', datos, {
            preserveScroll: true,
            preserveState: true,
            // Solo el catálogo de pacientes: no reinicia lo escrito del
            // procedimiento, que vive en el estado del formulario.
            only: ['pacientes'],
            onStart: () => setGuardando(true),
            onError: (erroresValidacion) => setErrores(erroresValidacion),
            onSuccess: () => {
                setErrores({});
                setDatos(pacienteVacio);
                setAbierto(false);
            },
            onFinish: () => {
                setGuardando(false);
                dejarDeEscuchar();
            },
        });
    };

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setAbierto(true)}
            >
                <UserPlus className="size-4" />
                Nuevo paciente
            </Button>

            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent className="max-h-[calc(100svh-4rem)] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Nuevo paciente</DialogTitle>
                        <DialogDescription>
                            Queda registrado en este hospital y seleccionado en
                            el procedimiento. El documento se guarda cifrado.
                        </DialogDescription>
                    </DialogHeader>

                    <CamposPaciente
                        datos={datos}
                        errores={errores}
                        regimenes={regimenes}
                        prefijo="nuevo_paciente"
                        onCambio={(clave, valor) =>
                            setDatos((previos) => ({
                                ...previos,
                                [clave]: valor,
                            }))
                        }
                    />

                    <div className="flex items-center gap-3">
                        <Button
                            type="button"
                            onClick={guardar}
                            disabled={guardando}
                        >
                            Guardar paciente
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
