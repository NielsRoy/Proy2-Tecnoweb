<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';
import type { Palette } from '@/types';

const { palette, setPalette } = useTheme();

// 'swatch' = color primario representativo de cada paleta (solo para la muestra visual).
const tabs = [
    { value: 'ninos', label: 'Cálida', swatch: 'hsl(204 88% 48%)' },
    { value: 'jovenes', label: 'Vibrante', swatch: 'hsl(255 83% 58%)' },
    { value: 'adultos', label: 'Sobria', swatch: 'hsl(0 0% 9%)' },
] as const satisfies ReadonlyArray<{
    value: Palette;
    label: string;
    swatch: string;
}>;
</script>

<template>
    <div
        data-slot="toggle-tabs"
        class="inline-flex flex-wrap gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
    >
        <button
            v-for="{ value, label, swatch } in tabs"
            :key="value"
            @click="setPalette(value)"
            :data-active="palette === value"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                palette === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <span
                class="-ml-1 h-4 w-4 rounded-full border border-black/10"
                :style="{ backgroundColor: swatch }"
            />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
