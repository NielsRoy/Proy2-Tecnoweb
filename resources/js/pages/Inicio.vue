<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { inicio } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Inicio',
                href: inicio(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Inicio" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div
            class="flex min-h-[60vh] flex-1 flex-col items-center justify-center rounded-xl border border-sidebar-border/70 p-6 text-center dark:border-sidebar-border"
        >
            <h1 class="text-2xl font-semibold">Inicio</h1>
            <p class="mt-2 text-muted-foreground">Bienvenido a Tienda D &amp; D.</p>
        </div>
    </div>
</template>
