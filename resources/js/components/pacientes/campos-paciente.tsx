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

export const tiposDocumento = ['CC', 'TI', 'RC', 'CE', 'PA', 'PT'];

export const etiquetasRegimen: Record<string, string> = {
    contributivo: 'Contributivo',
    subsidiado: 'Subsidiado',
    especial: 'Especial',
    no_asegurado: 'No asegurado',
};

export type DatosPaciente = {
    tipo_documento: string;
    documento: string;
    nombres: string;
    apellidos: string;
    fecha_nacimiento: string;
    sexo: string;
    regimen: string;
    asegurador: string;
    zona: string;
    municipio: string;
};

export const pacienteVacio: DatosPaciente = {
    tipo_documento: 'CC',
    documento: '',
    nombres: '',
    apellidos: '',
    fecha_nacimiento: '',
    sexo: '',
    regimen: 'subsidiado',
    asegurador: '',
    zona: 'urbana',
    municipio: '',
};

/**
 * Campos del paciente, compartidos por el alta rápida desde el registro del
 * procedimiento y por el módulo de pacientes: una sola definición para que
 * ambos caminos capturen exactamente lo mismo.
 *
 * El prefijo de los `id` evita colisiones cuando el formulario se monta
 * dentro de otro (el modal vive sobre el formulario del procedimiento).
 */
export function CamposPaciente({
    datos,
    errores,
    onCambio,
    regimenes,
    prefijo = 'paciente',
}: {
    datos: DatosPaciente;
    errores: Record<string, string>;
    onCambio: (clave: keyof DatosPaciente, valor: string) => void;
    regimenes: string[];
    prefijo?: string;
}) {
    const id = (clave: string) => `${prefijo}_${clave}`;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={id('tipo_documento')}>Tipo de documento</Label>
                <Select
                    value={datos.tipo_documento}
                    onValueChange={(v) => onCambio('tipo_documento', v)}
                >
                    <SelectTrigger id={id('tipo_documento')}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {tiposDocumento.map((t) => (
                            <SelectItem key={t} value={t}>
                                {t}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errores.tipo_documento} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('documento')}>Documento</Label>
                <Input
                    id={id('documento')}
                    value={datos.documento}
                    onChange={(e) => onCambio('documento', e.target.value)}
                    maxLength={20}
                    required
                />
                <InputError message={errores.documento} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('nombres')}>Nombres</Label>
                <Input
                    id={id('nombres')}
                    value={datos.nombres}
                    onChange={(e) => onCambio('nombres', e.target.value)}
                    maxLength={120}
                    required
                />
                <InputError message={errores.nombres} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('apellidos')}>Apellidos</Label>
                <Input
                    id={id('apellidos')}
                    value={datos.apellidos}
                    onChange={(e) => onCambio('apellidos', e.target.value)}
                    maxLength={120}
                    required
                />
                <InputError message={errores.apellidos} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('fecha_nacimiento')}>
                    Fecha de nacimiento (opcional)
                </Label>
                <Input
                    id={id('fecha_nacimiento')}
                    type="date"
                    value={datos.fecha_nacimiento}
                    onChange={(e) =>
                        onCambio('fecha_nacimiento', e.target.value)
                    }
                />
                <InputError message={errores.fecha_nacimiento} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('sexo')}>Sexo (opcional)</Label>
                <Select
                    value={datos.sexo}
                    onValueChange={(v) => onCambio('sexo', v)}
                >
                    <SelectTrigger id={id('sexo')}>
                        <SelectValue placeholder="Sin especificar" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="M">Masculino</SelectItem>
                        <SelectItem value="F">Femenino</SelectItem>
                        <SelectItem value="O">Otro</SelectItem>
                    </SelectContent>
                </Select>
                <InputError message={errores.sexo} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('regimen')}>Régimen</Label>
                <Select
                    value={datos.regimen}
                    onValueChange={(v) => onCambio('regimen', v)}
                >
                    <SelectTrigger id={id('regimen')}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {regimenes.map((r) => (
                            <SelectItem key={r} value={r}>
                                {etiquetasRegimen[r] ?? r}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errores.regimen} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('asegurador')}>
                    Asegurador / EPS (opcional)
                </Label>
                <Input
                    id={id('asegurador')}
                    value={datos.asegurador}
                    onChange={(e) => onCambio('asegurador', e.target.value)}
                    maxLength={120}
                />
                <InputError message={errores.asegurador} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('zona')}>Zona</Label>
                <Select
                    value={datos.zona}
                    onValueChange={(v) => onCambio('zona', v)}
                >
                    <SelectTrigger id={id('zona')}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="urbana">Urbana</SelectItem>
                        <SelectItem value="rural">Rural</SelectItem>
                    </SelectContent>
                </Select>
                <InputError message={errores.zona} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id('municipio')}>Municipio (opcional)</Label>
                <Input
                    id={id('municipio')}
                    value={datos.municipio}
                    onChange={(e) => onCambio('municipio', e.target.value)}
                    maxLength={120}
                />
                <InputError message={errores.municipio} />
            </div>
        </div>
    );
}
