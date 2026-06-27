<script setup lang="ts">
import { Baby, Briefcase, Rocket, SlidersHorizontal } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import type { Theme } from '@/types';

const { theme, setTheme } = useTheme();

const tabs = [
    { value: 'ninos', Icon: Baby, label: 'Niños' },
    { value: 'jovenes', Icon: Rocket, label: 'Jóvenes' },
    { value: 'adultos', Icon: Briefcase, label: 'Adultos' },
    { value: 'custom', Icon: SlidersHorizontal, label: 'Personalizado' },
] as const satisfies ReadonlyArray<{ value: Theme; Icon: unknown; label: string }>;
</script>

<template>
    <div
        data-slot="toggle-tabs"
        class="inline-flex flex-wrap gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="setTheme(value)"
            :data-active="theme === value"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                theme === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
