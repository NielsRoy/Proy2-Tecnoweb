<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';
import type { Palette } from '@/types';

const { palette, setPalette } = useTheme();

// 'swatch' = color primario representativo de cada paleta (solo para la muestra visual).
const tabs = [
    { value: 'ninos', label: 'Cálida', swatch: 'hsl(26 95% 52%)' },
    { value: 'jovenes', label: 'Vibrante', swatch: 'hsl(142 72% 40%)' },
    { value: 'adultos', label: 'Sobria', swatch: 'hsl(150 58% 34%)' },
] as const satisfies ReadonlyArray<{
    value: Palette;
    label: string;
    swatch: string;
}>;
</script>

<template>
    <div
        data-slot="toggle-tabs"
        class="inline-flex flex-wrap gap-1 rounded-lg bg-muted p-1"
    >
        <button
            v-for="{ value, label, swatch } in tabs"
            :key="value"
            @click="setPalette(value)"
            :data-active="palette === value"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                palette === value
                    ? 'bg-accent text-accent-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
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
