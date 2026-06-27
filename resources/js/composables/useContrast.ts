import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import type { Contrast } from '@/types';

export type { Contrast };

export type UseContrastReturn = {
    highContrast: Ref<boolean>;
    updateHighContrast: (value: boolean) => void;
};

// El switch maneja un booleano; en cookie/atributo se guarda como 'high'/'normal'.
const toContrast = (high: boolean): Contrast => (high ? 'high' : 'normal');

// Aplica el contraste como atributo en <html>; el CSS (app.css) sobreescribe las
// variables de color. Convive con el tema día/noche (variantes claro y .dark).
export function applyContrast(value: Contrast): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.contrast = value;
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredContrast = (): Contrast | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = localStorage.getItem('contrast') as Contrast | null;

    return stored === 'high' || stored === 'normal' ? stored : null;
};

export function initializeContrast(): void {
    if (typeof window === 'undefined') {
        return;
    }

    applyContrast(getStoredContrast() ?? 'normal');
}

const highContrast = ref<boolean>(false);

export function useContrast(): UseContrastReturn {
    onMounted(() => {
        highContrast.value = getStoredContrast() === 'high';
    });

    function updateHighContrast(value: boolean) {
        highContrast.value = value;

        const contrast = toContrast(value);

        // Persistencia en el cliente...
        localStorage.setItem('contrast', contrast);

        // Cookie para que el servidor la aplique en el primer render (anti-parpadeo)...
        setCookie('contrast', contrast);

        applyContrast(contrast);
    }

    return {
        highContrast,
        updateHighContrast,
    };
}
