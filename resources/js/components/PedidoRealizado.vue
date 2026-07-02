<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { PackageCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';

// Datos de un pedido (checkout al contado + efectivo). Aun NO se cobro: se paga en efectivo al recibir.
export type PedidoDetalle = {
    monto: string | null;
    direccion: string | null;
};

const props = defineProps<{ pedido: PedidoDetalle; retornoUrl: string }>();

function volver(): void {
    router.visit(props.retornoUrl);
}
</script>

<template>
    <div class="flex w-full flex-col items-center gap-4 py-4 text-center">
        <PackageCheck class="size-12 text-green-600" />
        <p class="text-lg font-semibold">¡Pedido realizado correctamente!</p>
        <p class="text-sm text-muted-foreground">
            Tu pedido llegará a la dirección indicada. Paga en efectivo cuando lo
            recibas.
        </p>

        <dl
            class="w-full space-y-1.5 rounded-lg border border-sidebar-border/70 p-4 text-left text-sm dark:border-sidebar-border"
        >
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Total a pagar</dt>
                <dd class="font-medium">Bs {{ pedido.monto ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Método</dt>
                <dd class="font-medium">Efectivo (contra entrega)</dd>
            </div>
            <div v-if="pedido.direccion" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Dirección</dt>
                <dd class="max-w-[60%] text-right font-medium">
                    {{ pedido.direccion }}
                </dd>
            </div>
        </dl>

        <Button class="w-full" @click="volver">Regresar a la tienda</Button>
    </div>
</template>
