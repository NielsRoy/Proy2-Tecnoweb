import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

// Horario nocturno del modo 'schedule': de 19:00 a 06:59 se usa el tema oscuro.
const NIGHT_START_HOUR = 19;
const NIGHT_END_HOUR = 7;

export function isNightTime(date = new Date()): boolean {
    const hour = date.getHours();

    return hour >= NIGHT_START_HOUR || hour < NIGHT_END_HOUR;
}

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    let isDark: boolean;

    if (value === 'system') {
        isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    } else if (value === 'schedule') {
        isDark = isNightTime();
    } else {
        isDark = value === 'dark';
    }

    document.documentElement.classList.toggle('dark', isDark);
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

// Reaplica el tema en modo 'schedule' (al cruzar la hora límite o al volver a la pestaña).
const handleScheduleThemeChange = () => {
    if (getStoredAppearance() === 'schedule') {
        updateTheme('schedule');
    }
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);

    // En modo 'schedule', reevalúa la hora cada minuto y al volver a la pestaña...
    window.setInterval(handleScheduleThemeChange, 60 * 1000);
    document.addEventListener('visibilitychange', handleScheduleThemeChange);
}

const appearance = ref<Appearance>('system');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        if (appearance.value === 'schedule') {
            return isNightTime() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
