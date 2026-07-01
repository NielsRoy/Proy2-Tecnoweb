<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChartColumn } from '@lucide/vue';
import BarChart from '@/components/charts/BarChart.vue';
import PieChart from '@/components/charts/PieChart.vue';
import { dashboard } from '@/routes';

type Grafico = {
    clave: string;
    titulo: string;
    tipo: 'bar' | 'pie';
    labels: string[];
    data: number[];
    ejeY: string | null;
};

const props = defineProps<{
    graficos: Grafico[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

function tieneDatos(g: Grafico): boolean {
    return g.labels.length > 0 && g.data.some((v) => v > 0);
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Dashboard</h1>
            <p class="text-sm text-muted-foreground">
                Estadísticas del negocio. Solo se muestran los gráficos para los que
                tienes permiso.
            </p>
        </header>

        <!-- Sin gráficos habilitados -->
        <div
            v-if="props.graficos.length === 0"
            class="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-sidebar-border/70 p-10 text-center text-muted-foreground dark:border-sidebar-border"
        >
            <ChartColumn class="size-10" />
            <p class="font-medium">No tienes estadísticas habilitadas</p>
            <p class="max-w-md text-sm">
                Pide a un administrador que te habilite gráficos del Dashboard en la
                matriz de acceso.
            </p>
        </div>

        <!-- Grilla de gráficos -->
        <div v-else class="grid gap-4 lg:grid-cols-2">
            <div
                v-for="g in props.graficos"
                :key="g.clave"
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-medium">{{ g.titulo }}</h2>
                <template v-if="tieneDatos(g)">
                    <BarChart
                        v-if="g.tipo === 'bar'"
                        :labels="g.labels"
                        :data="g.data"
                        :eje-y="g.ejeY"
                    />
                    <PieChart v-else :labels="g.labels" :data="g.data" />
                </template>
                <div
                    v-else
                    class="flex h-64 items-center justify-center text-sm text-muted-foreground"
                >
                    Sin datos todavía.
                </div>
            </div>
        </div>
    </div>
</template>
