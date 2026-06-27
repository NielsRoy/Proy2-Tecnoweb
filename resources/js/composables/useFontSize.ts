import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import type { FontSize } from '@/types';

export type { FontSize };

export type UseFontSizeReturn = {
    fontSize: Ref<FontSize>;
    updateFontSize: (value: FontSize) => void;
};

const DEFAULT_FONT_SIZE: FontSize = 'base';
const VALID_FONT_SIZES: readonly FontSize[] = ['sm', 'base', 'lg', 'xl'];

// Aplica el tamaño de letra como atributo en <html>; el CSS (app.css) ajusta el
// font-size raíz y, como Tailwind usa rem, todo el UI escala proporcionalmente.
export function applyFontSize(value: FontSize): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.fontSize = value;
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredFontSize = (): FontSize | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = localStorage.getItem('fontSize') as FontSize | null;

    return stored && VALID_FONT_SIZES.includes(stored) ? stored : null;
};

export function initializeFontSize(): void {
    if (typeof window === 'undefined') {
        return;
    }

    applyFontSize(getStoredFontSize() ?? DEFAULT_FONT_SIZE);
}

const fontSize = ref<FontSize>(DEFAULT_FONT_SIZE);

export function useFontSize(): UseFontSizeReturn {
    onMounted(() => {
        fontSize.value = getStoredFontSize() ?? DEFAULT_FONT_SIZE;
    });

    function updateFontSize(value: FontSize) {
        fontSize.value = value;

        // Persistencia en el cliente...
        localStorage.setItem('fontSize', value);

        // Cookie para que el servidor la aplique en el primer render (SSR/anti-parpadeo)...
        setCookie('fontSize', value);

        applyFontSize(value);
    }

    return {
        fontSize,
        updateFontSize,
    };
}
