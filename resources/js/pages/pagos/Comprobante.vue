<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PagoExitoso, { type PagoDetalle } from '@/components/PagoExitoso.vue';
import PedidoRealizado, {
    type PedidoDetalle,
} from '@/components/PedidoRealizado.vue';

const props = defineProps<{
    tipo?: 'pago' | 'pedido';
    concepto: string;
    pago?: PagoDetalle;
    pedido?: PedidoDetalle;
    retornoUrl: string;
}>();

const esPedido = props.tipo === 'pedido';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Comprobante', href: '' }],
    },
});
</script>

<template>
    <Head :title="esPedido ? 'Pedido realizado' : 'Comprobante de pago'" />

    <div class="flex h-full flex-1 flex-col items-center gap-6 p-6">
        <header class="space-y-1 text-center">
            <h1 class="text-xl font-semibold">
                {{ esPedido ? 'Pedido realizado' : 'Comprobante de pago' }}
            </h1>
            <p class="text-sm text-muted-foreground">{{ concepto }}</p>
        </header>

        <div
            class="w-full max-w-md rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <PedidoRealizado
                v-if="esPedido && pedido"
                :pedido="pedido"
                :retorno-url="retornoUrl"
            />
            <PagoExitoso
                v-else-if="pago"
                :pago="pago"
                :retorno-url="retornoUrl"
            />
        </div>
    </div>
</template>
