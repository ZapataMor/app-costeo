import type { Auth, HospitalCompartido } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            hospital: HospitalCompartido;
            /** Sobrecostos detectados y todavía sin causa en el hospital activo. */
            alertasPendientes: number;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
