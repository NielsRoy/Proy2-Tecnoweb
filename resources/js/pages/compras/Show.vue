<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/compras';

type DetalleItem = {
    producto: string | null;
    cantidad: number;
    precio_unitario: string;
    subtotal: string;
};

type CompraDetalle = {
    id: number;
    fecha: string | null;
    proveedor: string | null;
    monto_total: string;
    estado: string;
    detalles: DetalleItem[];
};

defineProps<{
    compra: CompraDetalle;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Compras', href: index() }],
    },
});
</script>

<template>
    <Head :title="`Compra #${compra.id}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Compra #{{ compra.id }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ compra.proveedor ?? '—' }} · {{ compra.fecha }}
                </p>
            </div>
            <Badge :variant="compra.estado === 'anulada' ? 'destructive' : 'default'">
                {{ compra.estado === 'anulada' ? 'Anulada' : 'Registrada' }}
            </Badge>
        </header>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr
                        class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <th class="p-3 text-left font-medium">Producto</th>
                        <th class="p-3 text-right font-medium">Cantidad</th>
                        <th class="p-3 text-right font-medium">Precio unit.</th>
                        <th class="p-3 text-right font-medium">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(d, i) in compra.detalles"
                        :key="i"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 font-medium">{{ d.producto ?? '—' }}</td>
                        <td class="p-3 text-right">{{ d.cantidad }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ d.precio_unitario }}
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ d.subtotal }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="font-medium">
                        <td class="p-3" colspan="3">Total</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ compra.monto_total }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div>
            <Button variant="outline" as-child>
                <Link :href="index()">Volver a compras</Link>
            </Button>
        </div>
    </div>
</template>
