import { Form, Head, Link } from '@inertiajs/react';
import DigitadorController from '@/actions/App/Http/Controllers/DigitadorController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function DigitadoresCreate() {
    return (
        <>
            <Head title="Nuevo digitador" />
            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Nuevo digitador"
                    description="Crea una cuenta para el personal que registrará los procedimientos de tu hospital."
                />

                <Form
                    {...DigitadorController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['password', 'password_confirmation']}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nombre completo</Label>
                                    <Input id="name" name="name" required autoComplete="name" placeholder="p. ej. Laura Gómez" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Correo electrónico</Label>
                                    <Input id="email" name="email" type="email" required autoComplete="off" placeholder="p. ej. laura.gomez@hospital.test" />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Contraseña</Label>
                                    <Input id="password" name="password" type="password" required autoComplete="new-password" />
                                    <InputError message={errors.password} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                                    <Input id="password_confirmation" name="password_confirmation" type="password" required autoComplete="new-password" />
                                    <InputError message={errors.password_confirmation} />
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button disabled={processing}>Crear digitador</Button>
                                <Button asChild variant="outline">
                                    <Link href={DigitadorController.index.url()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

DigitadoresCreate.layout = {
    breadcrumbs: [
        { title: 'Digitadores', href: '/digitadores' },
        { title: 'Nuevo', href: '/digitadores/create' },
    ],
};
