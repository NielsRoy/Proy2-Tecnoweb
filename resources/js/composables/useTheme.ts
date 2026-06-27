import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { useFontSize } from '@/composables/useFontSize';
import type { FontFamily, FontSize, Palette, Theme } from '@/types';

export type { FontFamily, Palette, Theme };

export type UseThemeReturn = {
    theme: ComputedRef<Theme>;
    palette: Ref<Palette>;
    font: Ref<FontFamily>;
    setTheme: (value: Theme) => void;
    setPalette: (value: Palette) => void;
    setFont: (value: FontFamily) => void;
};

const DEFAULT_PALETTE: Palette = 'adultos';
const DEFAULT_FONT: FontFamily = 'instrument';

const VALID_PALETTES: readonly Palette[] = ['ninos', 'jovenes', 'adultos'];
const VALID_FONTS: readonly FontFamily[] = ['fredoka', 'poppins', 'instrument'];

// Presets de edad: cada uno fija paleta + fuente y un tamaño de letra recomendado
// (el tamaño se puede ajustar después en Accesibilidad sin "salir" del tema).
const PRESETS: Record<
    Exclude<Theme, 'custom'>,
    { palette: Palette; font: FontFamily; fontSize: FontSize }
> = {
    ninos: { palette: 'ninos', font: 'fredoka', fontSize: 'lg' },
    jovenes: { palette: 'jovenes', font: 'poppins', fontSize: 'sm' },
    adultos: { palette: 'adultos', font: 'instrument', fontSize: 'base' },
};

// Aplican los atributos en <html>; el CSS (app.css) resuelve colores y --font-sans.
export function applyPalette(value: Palette): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.palette = value;
}

export function applyFont(value: FontFamily): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.font = value;
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredPalette = (): Palette | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = localStorage.getItem('palette') as Palette | null;

    return stored && VALID_PALETTES.includes(stored) ? stored : null;
};

const getStoredFont = (): FontFamily | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = localStorage.getItem('font') as FontFamily | null;

    return stored && VALID_FONTS.includes(stored) ? stored : null;
};

const getStoredCustom = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return localStorage.getItem('themeCustom') === '1';
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    applyPalette(getStoredPalette() ?? DEFAULT_PALETTE);
    applyFont(getStoredFont() ?? DEFAULT_FONT);
}

const palette = ref<Palette>(DEFAULT_PALETTE);
const font = ref<FontFamily>(DEFAULT_FONT);
// Permite entrar a "Personalizado" aunque la combinación coincida con un preset.
const customOverride = ref<boolean>(false);

export function useTheme(): UseThemeReturn {
    const { updateFontSize } = useFontSize();

    onMounted(() => {
        palette.value = getStoredPalette() ?? DEFAULT_PALETTE;
        font.value = getStoredFont() ?? DEFAULT_FONT;
        customOverride.value = getStoredCustom();
    });

    // El tema se deriva de (paleta, fuente); el flag de personalización lo fuerza a 'custom'.
    const theme = computed<Theme>(() => {
        if (customOverride.value) {
            return 'custom';
        }

        if (palette.value === 'ninos' && font.value === 'fredoka') {
            return 'ninos';
        }

        if (palette.value === 'jovenes' && font.value === 'poppins') {
            return 'jovenes';
        }

        if (palette.value === 'adultos' && font.value === 'instrument') {
            return 'adultos';
        }

        return 'custom';
    });

    function setCustom(value: boolean) {
        customOverride.value = value;

        if (value) {
            localStorage.setItem('themeCustom', '1');
        } else {
            localStorage.removeItem('themeCustom');
        }
    }

    function persistPalette(value: Palette) {
        palette.value = value;
        localStorage.setItem('palette', value);
        setCookie('palette', value);
        applyPalette(value);
    }

    function persistFont(value: FontFamily) {
        font.value = value;
        localStorage.setItem('font', value);
        setCookie('font', value);
        applyFont(value);
    }

    function setTheme(value: Theme) {
        if (value === 'custom') {
            // Personalizado: no cambia paleta/fuente; solo revela los selectores.
            setCustom(true);

            return;
        }

        const preset = PRESETS[value];

        setCustom(false);
        persistPalette(preset.palette);
        persistFont(preset.font);
        updateFontSize(preset.fontSize);
    }

    // Usados en modo Personalizado: cambian un aspecto y mantienen el modo custom.
    function setPalette(value: Palette) {
        setCustom(true);
        persistPalette(value);
    }

    function setFont(value: FontFamily) {
        setCustom(true);
        persistFont(value);
    }

    return {
        theme,
        palette,
        font,
        setTheme,
        setPalette,
        setFont,
    };
}
