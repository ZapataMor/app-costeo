import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

/** Punto de la pantalla desde el que se expande la onda al cambiar de tema. */
export type OrigenOnda = { x: number; y: number };

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance, origen?: OrigenOnda) => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return 'system';
    }

    return (localStorage.getItem('appearance') as Appearance) || 'system';
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

/**
 * La onda solo se anima si el navegador soporta View Transitions y el usuario
 * no pidio reducir el movimiento; en cualquier otro caso el cambio es directo.
 */
const soportaOnda = (): boolean => {
    if (typeof document === 'undefined' || !document.startViewTransition) {
        return false;
    }

    // Con la pestana oculta el navegador aborta la transicion; no vale la pena
    // animar algo que nadie esta viendo.
    if (document.visibilityState !== 'visible') {
        return false;
    }

    return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (!localStorage.getItem('appearance')) {
        localStorage.setItem('appearance', 'system');
        setCookie('appearance', 'system');
    }

    currentAppearance = getStoredAppearance();
    applyTheme(currentAppearance);

    // Set up system theme change listener
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    const updateAppearance = (mode: Appearance, origen?: OrigenOnda): void => {
        const aplicar = (): void => {
            currentAppearance = mode;

            // Store in localStorage for client-side persistence...
            localStorage.setItem('appearance', mode);

            // Store in cookie for SSR...
            setCookie('appearance', mode);

            applyTheme(mode);
            notify();
        };

        if (!origen || !soportaOnda()) {
            aplicar();

            return;
        }

        // El radio necesario para que el circulo cubra la esquina mas lejana.
        const radio = Math.hypot(
            Math.max(origen.x, window.innerWidth - origen.x),
            Math.max(origen.y, window.innerHeight - origen.y),
        );

        const raiz = document.documentElement;
        raiz.style.setProperty('--onda-x', `${origen.x}px`);
        raiz.style.setProperty('--onda-y', `${origen.y}px`);
        raiz.style.setProperty('--onda-r', `${radio}px`);
        raiz.dataset.ondaTema = 'activa';

        const transicion = document.startViewTransition(aplicar);

        // Si la transicion se aborta (pestana oculta, otra transicion en curso)
        // el tema ya se aplico igual: solo hay que limpiar la marca sin dejar
        // una promesa rechazada suelta.
        transicion.finished
            .catch(() => undefined)
            .finally(() => {
                delete raiz.dataset.ondaTema;
            });
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
