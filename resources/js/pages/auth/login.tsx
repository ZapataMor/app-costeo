import { Form, Head } from '@inertiajs/react';
import { LockKeyhole, Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

const fieldLabelClass =
    'text-sm font-semibold tracking-[1.8px] text-[#5B687C] uppercase';

const underlineInputClass =
    'h-auto rounded-none border-0 border-b border-[#5B687C]/45 bg-transparent! px-0.5 py-[13px] text-xl text-[#161B2F] shadow-none placeholder:font-light placeholder:text-[#161B2F]/30 focus-visible:border-[#5B687C] focus-visible:ring-0 md:text-xl';

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Iniciar sesión" />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <>
                        <div>
                            <div className="mb-[25px]">
                                <Label
                                    htmlFor="email"
                                    className={`${fieldLabelClass} mb-1 block`}
                                >
                                    Correo electrónico
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        placeholder="nombre@clinica.co"
                                        className={`${underlineInputClass} pr-10`}
                                    />
                                    <Mail className="pointer-events-none absolute top-1/2 right-0.5 size-[23px] -translate-y-1/2 text-[#8D8F8E]" />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            <div className="mb-[23px]">
                                <Label
                                    htmlFor="password"
                                    className={`${fieldLabelClass} mb-1 block`}
                                >
                                    Contraseña
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="••••••••"
                                        className={`${underlineInputClass} pr-10 tracking-[3px]`}
                                    />
                                    <LockKeyhole className="pointer-events-none absolute top-1/2 right-0.5 size-[23px] -translate-y-1/2 text-[#8D8F8E]" />
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="mb-8 flex items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                        className="size-[18px] rounded-[2px] lg:size-[21px]"
                                    />
                                    <Label
                                        htmlFor="remember"
                                        className="text-sm text-[#5B687C] lg:text-base"
                                    >
                                        Recordarme
                                    </Label>
                                </div>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-right text-sm font-medium text-[#5B687C] decoration-transparent hover:text-[#161B2F] lg:text-base"
                                        tabIndex={5}
                                    >
                                        ¿Olvidó su contraseña?
                                    </TextLink>
                                )}
                            </div>

                            <Button
                                type="submit"
                                className="h-auto w-full rounded-[9px] py-4 text-sm font-semibold tracking-[2px] uppercase lg:py-[18px] lg:text-base"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Ingresar
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Bienvenido de nuevo',
    description: 'Acceda a su panel de costeo hospitalario.',
};
