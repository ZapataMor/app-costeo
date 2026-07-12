import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden p-6 md:p-10">
            <div
                aria-hidden
                className="pointer-events-none absolute -top-32 -right-24 h-96 w-96 rounded-full bg-[#5B687C]/25 blur-3xl"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-[#161B2F]/20 blur-3xl dark:bg-[#5B687C]/15"
            />
            <div className="relative w-full max-w-sm">
                <div className="flex flex-col gap-8 rounded-2xl border border-border/70 bg-card p-8 shadow-2xl shadow-[#161B2F]/10 backdrop-blur-2xl">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                                <AppLogoIcon className="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
