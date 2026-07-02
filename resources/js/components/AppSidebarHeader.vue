<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BuscadorGlobal from '@/components/BuscadorGlobal.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();

// El buscador global aparece en todos los módulos del sidebar y en /inicio, pero NO en Configuración
// (perfil/apariencia/seguridad, bajo /settings).
const mostrarBuscador = computed(() => !page.url.includes('/settings'));

// El servidor puede proveer breadcrumbs (p. ej. Compras se llama "Mis ventas" para el proveedor);
// si los envía, tienen prioridad sobre los del layout (defineOptions, estáticos).
const breadcrumbsMostrar = computed<BreadcrumbItem[]>(() => {
    const delServidor = (page.props as Record<string, unknown>)
        .breadcrumbs as BreadcrumbItem[] | undefined;

    return delServidor && delServidor.length > 0 ? delServidor : props.breadcrumbs;
});
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbsMostrar.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbsMostrar" />
            </template>
        </div>

        <div v-if="mostrarBuscador" class="ml-auto flex items-center">
            <BuscadorGlobal />
        </div>
    </header>
</template>
