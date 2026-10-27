import { separarMarcaSemilla } from '@/lib/semilla';

/**
 * Nombre de un registro con la marca de dato de prueba como insignia
 * discreta al final, en vez de «Cesárea segmentaria [SEMILLA]» en el texto.
 */
export function NombreDato({ texto }: { texto: string | null | undefined }) {
    if (texto == null) {
        return null;
    }

    const { limpio, esSemilla } = separarMarcaSemilla(texto);

    return (
        <>
            {limpio}
            {esSemilla && (
                <span
                    title="Dato de prueba sembrado, no es un dato real del hospital"
                    className="ml-1.5 inline-block rounded-full border border-dashed border-current/30 px-1.5 align-middle text-[11px] leading-4 font-normal text-muted-foreground"
                >
                    prueba
                </span>
            )}
        </>
    );
}
