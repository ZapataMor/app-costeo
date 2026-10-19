import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RolUsuario } from '@/types';
import { etiquetasRol } from '@/types/instituciones';
import type { UsuarioInstitucion } from '@/types/instituciones';

type FormAction = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

/**
 * Alta/edición de un usuario de la institución. Al editar, la contraseña
 * en blanco conserva la actual.
 */
export function UsuarioInstitucionForm({
    action,
    usuario,
    rolesAsignables,
    onSuccess,
    onCancelar,
}: {
    action: FormAction;
    usuario?: UsuarioInstitucion;
    rolesAsignables: string[];
    onSuccess?: () => void;
    onCancelar?: () => void;
}) {
    const editando = usuario !== undefined;

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            onSuccess={onSuccess}
            resetOnSuccess={['password', 'password_confirmation']}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre completo</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                autoComplete="off"
                                defaultValue={usuario?.name ?? ''}
                                placeholder="p. ej. Laura Gómez"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                autoComplete="off"
                                defaultValue={usuario?.email ?? ''}
                                placeholder="p. ej. laura.gomez@hospital.test"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="role">Rol</Label>
                            <Select
                                name="role"
                                defaultValue={usuario?.role ?? 'admin_hospital'}
                                required
                            >
                                <SelectTrigger id="role">
                                    <SelectValue placeholder="Seleccione" />
                                </SelectTrigger>
                                <SelectContent>
                                    {rolesAsignables.map((rol) => (
                                        <SelectItem key={rol} value={rol}>
                                            {etiquetasRol[rol as RolUsuario] ??
                                                rol}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                El administrador configura parámetros, gestiona
                                digitadores y analiza costos; el digitador solo
                                registra procedimientos.
                            </p>
                            <InputError message={errors.role} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {editando
                                    ? 'Nueva contraseña (opcional)'
                                    : 'Contraseña'}
                            </Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                required={!editando}
                                autoComplete="new-password"
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmar contraseña
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                required={!editando}
                                autoComplete="new-password"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button disabled={processing}>
                            {editando ? 'Guardar cambios' : 'Crear usuario'}
                        </Button>
                        {onCancelar && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onCancelar}
                            >
                                Cancelar
                            </Button>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}
