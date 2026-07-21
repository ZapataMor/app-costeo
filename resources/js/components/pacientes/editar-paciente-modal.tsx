import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
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
import type { PacienteFila } from '@/types/pacientes';

/**
 * Alta y edición de paciente en el módulo del padrón. Sin `paciente` crea;
 * con él actualiza, usando los mismos campos que el alta rápida del registro.
 */
export function EditarPacienteModal({
    paciente,
    regimenes,
    disparador,
}: {
    paciente?: PacienteFila;
    regimenes: string[];
    disparador?: ReactNode;
}) {
    const inicial = (): DatosPaciente =>
        paciente
            ? {
                  tipo_documento: paciente.tipo_documento,
                  documento: paciente.documento,
                  nombres: paciente.nombres,
                  apellidos: paciente.apellidos,
                  fecha_nacimiento: paciente.fecha_nacimiento ?? '',
                  sexo: paciente.sexo ?? '',
                  regimen: paciente.regimen,
                  asegurador: paciente.asegurador ?? '',
                  zona: paciente.zona,
                  municipio: paciente.municipio ?? '',
              }
            : pacienteVacio;

    const [abierto, setAbierto] = useState(false);
    const [datos, setDatos] = useState<DatosPaciente>(inicial);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);

    const abrir = () => {
        setDatos(inicial());
        setErrores({});
        setAbierto(true);
    };

    const guardar = () => {
        const opciones = {
            preserveScroll: true,
            onStart: () => setGuardando(true),
            onError: (erroresValidacion: Record<string, string>) =>
                setErrores(erroresValidacion),
            onSuccess: () => {
                setErrores({});
                setAbierto(false);
            },
            onFinish: () => setGuardando(false),
        };

        if (paciente) {
            router.put(`/pacientes/${paciente.id}`, datos, opciones);

            return;
        }

        router.post('/pacientes', datos, opciones);
    };

    return (
        <>
            {disparador ? (
                <span onClick={abrir}>{disparador}</span>
            ) : (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Editar paciente"
                    onClick={abrir}
                >
                    <Pencil className="size-4" />
                </Button>
            )}

            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent className="max-h-[calc(100svh-4rem)] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {paciente ? 'Editar paciente' : 'Nuevo paciente'}
                        </DialogTitle>
                        <DialogDescription>
                            El documento se guarda cifrado y debe ser único en
                            este hospital.
                        </DialogDescription>
                    </DialogHeader>

                    <CamposPaciente
                        datos={datos}
                        errores={errores}
                        regimenes={regimenes}
                        prefijo={`paciente_${paciente?.id ?? 'nuevo'}`}
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
                            Guardar
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
