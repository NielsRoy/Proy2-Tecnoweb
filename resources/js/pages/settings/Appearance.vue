<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import ContrastToggle from '@/components/ContrastToggle.vue';
import FontSizeTabs from '@/components/FontSizeTabs.vue';
import FontTabs from '@/components/FontTabs.vue';
import Heading from '@/components/Heading.vue';
import PaletteTabs from '@/components/PaletteTabs.vue';
import ThemeTabs from '@/components/ThemeTabs.vue';
import { useTheme } from '@/composables/useTheme';
import { edit } from '@/routes/appearance';

const { theme } = useTheme();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración de apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Configuración de apariencia" />

    <h1 class="sr-only">Configuración de apariencia</h1>

    <div class="space-y-8">
        <div class="space-y-6">
            <Heading
                variant="small"
                title="Tema"
                description="Elige un tema según la edad o personalízalo a tu gusto"
            />
            <ThemeTabs />
        </div>

        <div v-if="theme === 'custom'" class="space-y-6">
            <Heading
                variant="small"
                title="Personalización"
                description="Combina la paleta de colores y la fuente que prefieras"
            />

            <div class="space-y-2">
                <p class="text-sm font-medium">Paleta de colores</p>
                <PaletteTabs />
            </div>

            <div class="space-y-2">
                <p class="text-sm font-medium">Tipo de letra</p>
                <FontTabs />
            </div>
        </div>

        <div class="space-y-6">
            <Heading
                variant="small"
                title="Modo"
                description="Alterna entre claro, oscuro o automático en cualquier tema"
            />
            <AppearanceTabs />
        </div>

        <div class="space-y-6">
            <Heading
                variant="small"
                title="Accesibilidad"
                description="Ajusta la aplicación para una mejor legibilidad"
            />

            <div class="space-y-2">
                <p class="text-sm font-medium">Tamaño de la letra</p>
                <FontSizeTabs />
            </div>

            <ContrastToggle />
        </div>
    </div>
</template>
