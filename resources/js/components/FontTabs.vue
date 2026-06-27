<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';
import type { FontFamily } from '@/types';

const { font, setFont } = useTheme();

// 'preview' = la familia con la que se muestra la etiqueta, para previsualizarla.
const tabs = [
    { value: 'fredoka', label: 'Redonda', preview: "'Fredoka', sans-serif" },
    { value: 'poppins', label: 'Moderna', preview: "'Poppins', sans-serif" },
    {
        value: 'instrument',
        label: 'Clásica',
        preview: "'Instrument Sans', sans-serif",
    },
] as const satisfies ReadonlyArray<{
    value: FontFamily;
    label: string;
    preview: string;
}>;
</script>

<template>
    <div
        data-slot="toggle-tabs"
        class="inline-flex flex-wrap gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
    >
        <button
            v-for="{ value, label, preview } in tabs"
            :key="value"
            @click="setFont(value)"
            :data-active="font === value"
            :style="{ fontFamily: preview }"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                font === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <span class="text-sm">{{ label }}</span>
        </button>
    </div>
</template>
