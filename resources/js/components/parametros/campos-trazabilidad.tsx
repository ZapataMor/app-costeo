import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { NivelConfiabilidad } from '@/types/parametros';

/**
 * Campos de trazabilidad académica del parámetro: de dónde salió el dato
 * (fuente) y qué tan confiable es (medido | estimado | supuesto).
 */
export function CamposTrazabilidad({
    niveles,
    fuente,
    nivel,
    errors,
}: {
    niveles: string[];
    fuente?: string | null;
    nivel?: NivelConfiabilidad;
    errors: Record<string, string | undefined>;
}) {
    return (
        <div className="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="fuente">Fuente del dato</Label>
                <Input
                    id="fuente"
                    name="fuente"
                    defaultValue={fuente ?? ''}
                    placeholder="p. ej. Entrevista jefe de enfermería 2026-07"
                />
                <p className="text-xs text-muted-foreground">
                    De dónde salió el dato (entrevista, contrato, factura,
                    observación directa…).
                </p>
                <InputError message={errors.fuente} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="nivel_confiabilidad">
                    Nivel de confiabilidad
                </Label>
                <Select
                    name="nivel_confiabilidad"
                    defaultValue={nivel ?? 'estimado'}
                >
                    <SelectTrigger id="nivel_confiabilidad">
                        <SelectValue placeholder="Seleccione" />
                    </SelectTrigger>
                    <SelectContent>
                        {niveles.map((n) => (
                            <SelectItem
                                key={n}
                                value={n}
                                className="capitalize"
                            >
                                {n}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                    Medido: observado directamente. Estimado: informado por el
                    personal. Supuesto: valor de trabajo.
                </p>
                <InputError message={errors.nivel_confiabilidad} />
            </div>
        </div>
    );
}
