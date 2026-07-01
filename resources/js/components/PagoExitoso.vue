<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';

// Datos de un pago confirmado. Los campos del pagador (banco/cuenta/titular) solo llegan en pagos por
// QR (PagoFacil); para efectivo/transferencia/tarjeta van vacios y no se muestran.
export type PagoDetalle = {
    metodo: string;
    monto: string | null;
    banco: string | null;
    cuenta: string | null;
    titular: string | null;
    fecha: string | null;
    hora: string | null;
};

const props = defineProps<{ pago: PagoDetalle; retornoUrl: string }>();

function volver(): void {
    router.visit(props.retornoUrl);
}
</script>

<template>
    <div class="flex w-full flex-col items-center gap-4 py-4 text-center">
        <CheckCircle2 class="size-12 text-green-600" />
        <p class="text-lg font-semibold">¡Pago confirmado!</p>

        <dl
            class="w-full space-y-1.5 rounded-lg border border-sidebar-border/70 p-4 text-left text-sm dark:border-sidebar-border"
        >
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Monto</dt>
                <dd class="font-medium">Bs {{ pago.monto ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Método</dt>
                <dd class="font-medium">{{ pago.metodo }}</dd>
            </div>
            <div v-if="pago.titular" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Pagador</dt>
                <dd class="font-medium">{{ pago.titular }}</dd>
            </div>
            <div v-if="pago.banco" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Banco</dt>
                <dd class="font-medium">{{ pago.banco }}</dd>
            </div>
            <div v-if="pago.cuenta" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Cuenta</dt>
                <dd class="font-medium">{{ pago.cuenta }}</dd>
            </div>
            <div v-if="pago.fecha" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Fecha y hora</dt>
                <dd class="font-medium">{{ pago.fecha }} {{ pago.hora }}</dd>
            </div>
        </dl>

        <Button class="w-full" @click="volver">Regresar</Button>
    </div>
</template>
