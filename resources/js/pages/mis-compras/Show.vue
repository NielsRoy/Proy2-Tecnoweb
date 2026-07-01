<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/mis-compras';
import { index as misPagos } from '@/routes/mis-pagos';

type DetalleItem = {
    producto: string | null;
    promocion: string | null;
    cantidad: number;
    precio_base: string | null;
    precio_unitario: string;
    subtotal: string;
};

type CuotaItem = {
    numero_cuota: number;
    monto: string;
    fecha_vencimiento: string | null;
    fecha_pago: string | null;
    metodo: string | null;
    estado: string;
};

type VentaDetalle = {
    id: number;
    fecha: string | null;
    direccion_envio: string;
    tipo_pago: string;
    numero_cuotas: number;
    monto_total: string;
    estado_pago: string;
    estado: string;
    detalles: DetalleItem[];
    cuotas: CuotaItem[];
};

const props = defineProps<{
    venta: VentaDetalle;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mis compras', href: index() }],
    },
});

const tienePendientes = computed(() =>
    props.venta.cuotas.some((c) => c.estado !== 'pagado'),
);
</script>

<template>
    <Head :title="`Compra #${venta.id}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Compra #{{ venta.id }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ venta.fecha }} · {{ venta.direccion_envio }}
                </p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <Badge :variant="venta.estado === 'anulada' ? 'destructive' : 'outline'">
                    {{ venta.estado === 'anulada' ? 'Anulada' : 'Registrada' }}
                </Badge>
                <Badge :variant="venta.estado_pago === 'pagada' ? 'default' : 'secondary'">
                    {{ venta.tipo_pago }} ·
                    {{ venta.estado_pago === 'pagada' ? 'Pagada' : 'Pendiente' }}
                </Badge>
            </div>
        </header>

        <!-- Productos -->
        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr
                        class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <th class="p-3 text-left font-medium">Producto</th>
                        <th class="p-3 text-left font-medium">Promoción</th>
                        <th class="p-3 text-right font-medium">Cantidad</th>
                        <th class="p-3 text-right font-medium">Precio base</th>
                        <th class="p-3 text-right font-medium">Precio aplicado</th>
                        <th class="p-3 text-right font-medium">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(d, i) in venta.detalles"
                        :key="i"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 font-medium">{{ d.producto ?? '—' }}</td>
                        <td class="p-3 text-muted-foreground">
                            {{ d.promocion ?? '—' }}
                        </td>
                        <td class="p-3 text-right">{{ d.cantidad }}</td>
                        <td
                            class="p-3 text-right whitespace-nowrap"
                            :class="
                                d.promocion
                                    ? 'text-muted-foreground line-through'
                                    : ''
                            "
                        >
                            Bs {{ d.precio_base ?? d.precio_unitario }}
                        </td>
                        <td
                            class="p-3 text-right whitespace-nowrap"
                            :class="
                                d.promocion
                                    ? 'font-medium text-emerald-600 dark:text-emerald-400'
                                    : ''
                            "
                        >
                            Bs {{ d.precio_unitario }}
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ d.subtotal }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="font-medium">
                        <td class="p-3" colspan="5">Total</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ venta.monto_total }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Cuotas -->
        <div v-if="venta.cuotas.length" class="space-y-2">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-medium">Cuotas</h2>
                <Button
                    v-if="tienePendientes"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="misPagos()">Ir a Mis pagos</Link>
                </Button>
            </div>
            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr
                            class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <th class="p-3 text-left font-medium">#</th>
                            <th class="p-3 text-right font-medium">Monto</th>
                            <th class="p-3 text-left font-medium">Vence</th>
                            <th class="p-3 text-left font-medium">Pagada</th>
                            <th class="p-3 text-left font-medium">Método</th>
                            <th class="p-3 text-left font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in venta.cuotas"
                            :key="c.numero_cuota"
                            class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                        >
                            <td class="p-3">{{ c.numero_cuota }}</td>
                            <td class="p-3 text-right whitespace-nowrap">
                                Bs {{ c.monto }}
                            </td>
                            <td class="p-3 text-muted-foreground">
                                {{ c.fecha_vencimiento }}
                            </td>
                            <td class="p-3 text-muted-foreground">
                                {{ c.fecha_pago ?? '—' }}
                            </td>
                            <td class="p-3 text-muted-foreground capitalize">
                                {{ c.metodo ?? '—' }}
                            </td>
                            <td class="p-3">
                                <Badge
                                    :variant="
                                        c.estado === 'pagado'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{ c.estado === 'pagado' ? 'Pagado' : 'Pendiente' }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <Button variant="outline" as-child>
                <Link :href="index()">Volver a Mis compras</Link>
            </Button>
        </div>
    </div>
</template>
