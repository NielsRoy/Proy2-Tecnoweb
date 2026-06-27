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
        class="inline-flex flex-wrap gap-1 rounded-lg bg-muted p-1"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="setTheme(value)"
            :data-active="theme === value"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                theme === value
                    ? 'bg-accent text-accent-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
