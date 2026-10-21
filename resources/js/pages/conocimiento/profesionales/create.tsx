import { Head, Link, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
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
import { etiqueta } from '@/lib/formato';

type Recurso = {
    id: number;
    nombre: string;
    rol: string;
    especialidad: string | null;
};

export default function ProfesionalesCreate({
    recursos,
}: {
    recursos: Recurso[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        recurso_humano_id: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <>
            <Head title="Nueva cuenta de profesional" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Nueva cuenta de profesional"
                    description="El personal quirúrgico con cuenta escribe lecciones, participa en las comunidades de su especialidad y ve su propia fila en el benchmarking anónimo. No ve costos de otras personas."
                />

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post('/conocimiento/profesionales');
                    }}
                    className="grid max-w-xl gap-4"
                >
                    <div className="grid gap-2">
                        <Label>Persona del equipo quirúrgico</Label>
                        <Select
                            value={data.recurso_humano_id}
                            onValueChange={(v) => {
                                setData((d) => ({
                                    ...d,
                                    recurso_humano_id: v,
                                    name:
                                        d.name ||
                                        (recursos
                                            .find((r) => String(r.id) === v)
                                            ?.nombre.replace(
                                                /^(Dra?|Inst|Enf|Aux)\.\s*/,
                                                '',
                                            ) ??
                                            ''),
                                }));
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Seleccione de recursos humanos" />
                            </SelectTrigger>
                            <SelectContent>
                                {recursos.map((r) => (
                                    <SelectItem key={r.id} value={String(r.id)}>
                                        {r.nombre} · {etiqueta(r.rol)}
                                        {r.especialidad
                                            ? ` · ${r.especialidad}`
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.recurso_humano_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nombre para la cuenta</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="off"
                            required
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">Contraseña</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            autoComplete="new-password"
                            required
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirmar contraseña
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            autoComplete="new-password"
                            required
                        />
                    </div>
                    <div className="flex items-center gap-3">
                        <Button disabled={processing}>Crear cuenta</Button>
                        <Button asChild variant="outline" type="button">
                            <Link href="/conocimiento/profesionales">
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ProfesionalesCreate.layout = {
    breadcrumbs: [
        { title: 'Profesionales', href: '/conocimiento/profesionales' },
        { title: 'Nueva cuenta', href: '/conocimiento/profesionales/create' },
    ],
};
