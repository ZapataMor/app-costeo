import type { ReactNode } from 'react';

/**
 * Encabezado de las vistas de análisis.
 *
 * Cuatro de los seis paneles entraban directamente con el selector de fechas,
 * sin título: el usuario llegaba desde una tarjeta y no tenía confirmación de
 * dónde había caído. Ahora todas abren igual que `/costeo`.
 */
export function EncabezadoCosteo({
    titulo,
    descripcion,
    accion,
}: {
    titulo: string;
    descripcion?: string;
    accion?: ReactNode;
}) {
    return (
        <div className="mb-1 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 className="font-serif text-[32px] leading-tight font-semibold text-[#161B2F] dark:text-[#F3F0ED]">
                    {titulo}
                </h1>
                {descripcion && (
                    <p className="mt-1 max-w-[70ch] text-[13.5px] text-[#74787E] dark:text-[#A6AAB2]">
                        {descripcion}
                    </p>
                )}
            </div>
            {accion}
        </div>
    );
}
