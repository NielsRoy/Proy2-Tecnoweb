<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PagoExitoso, { type PagoDetalle } from '@/components/PagoExitoso.vue';

defineProps<{
    concepto: string;
    pago: PagoDetalle;
    retornoUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Comprobante de pago', href: '' }],
    },
});
</script>

<template>
    <Head title="Comprobante de pago" />

    <div class="flex h-full flex-1 flex-col items-center gap-6 p-6">
        <header class="space-y-1 text-center">
            <h1 class="text-xl font-semibold">Comprobante de pago</h1>
            <p class="text-sm text-muted-foreground">{{ concepto }}</p>
        </header>

        <div
            class="w-full max-w-md rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <PagoExitoso :pago="pago" :retorno-url="retornoUrl" />
        </div>
    </div>
</template>
