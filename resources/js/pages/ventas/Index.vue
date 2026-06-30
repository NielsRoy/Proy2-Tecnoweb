<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { create, destroy, index, show } from '@/routes/ventas';

type VentaItem = {
    id: number;
    fecha: string | null;
    cliente: string | null;
    items: number;
    monto_total: string;
    tipo_pago: string;
    estado_pago: string;
    estado: string;
};

type Paginado = {
    data: VentaItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

defineProps<{
    ventas: Paginado;
    puedeCrear: boolean;
    puedeEliminar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ventas', href: index() }],
    },
});

function irA(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    }
}

const ventaAAnular = ref<VentaItem | null>(null);
const anulando = ref(false);

function confirmarAnular(): void {
    if (!ventaAAnular.value) {
        return;
    }
    router.delete(destroy(ventaAAnular.value.id).url, {
        preserveScroll: true,
        onStart: () => (anulando.value = true),
        onFinish: () => {
            anulando.value = false;
            ventaAAnular.value = null;
        },
    });
}
</script>

<template>
    <Head title="Ventas" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Ventas</h1>
                <p class="text-sm text-muted-foreground">
                    Registro de ventas a clientes. Al contado se paga al registrar;
                    a crédito se generan las cuotas que se cobran en Pagos.
                </p>
            </div>
            <Button v-if="puedeCrear" as-child>
                <Link :href="create()">Nueva venta</Link>
            </Button>
        </header>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr
                        class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <th class="p-3 text-left font-medium">Fecha</th>
                        <th class="p-3 text-left font-medium">Cliente</th>
                        <th class="p-3 text-right font-medium">Ítems</th>
                        <th class="p-3 text-right font-medium">Total</th>
                        <th class="p-3 text-left font-medium">Pago</th>
                        <th class="p-3 text-left font-medium">Estado</th>
                        <th class="p-3 text-right font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="v in ventas.data"
                        :key="v.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ v.fecha }}
                        </td>
                        <td class="p-3 font-medium">{{ v.cliente ?? '—' }}</td>
                        <td class="p-3 text-right">{{ v.items }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ v.monto_total }}
                        </td>
                        <td class="p-3">
                            <div class="flex flex-col gap-1">
                                <span class="capitalize">{{ v.tipo_pago }}</span>
                                <Badge
                                    :variant="
                                        v.estado_pago === 'pagada'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    class="w-fit"
                                >
                                    {{
                                        v.estado_pago === 'pagada'
                                            ? 'Pagada'
                                            : 'Pendiente'
                                    }}
                                </Badge>
                            </div>
                        </td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    v.estado === 'anulada'
                                        ? 'destructive'
                                        : 'outline'
                                "
                            >
                                {{ v.estado === 'anulada' ? 'Anulada' : 'Registrada' }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="show(v.id)">Ver</Link>
                                </Button>
                                <Button
                                    v-if="
                                        puedeEliminar &&
                                        v.estado === 'registrada' &&
                                        v.estado_pago === 'pendiente'
                                    "
                                    variant="destructive"
                                    size="sm"
                                    @click="ventaAAnular = v"
                                >
                                    Anular
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="ventas.data.length === 0">
                        <td
                            colspan="7"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay ventas registradas.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="ventas.total > 0"
            class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        >
            <span>
                Mostrando {{ ventas.from }}–{{ ventas.to }} de {{ ventas.total }}
            </span>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!ventas.prev_page_url"
                    @click="irA(ventas.prev_page_url)"
                >
                    Anterior
                </Button>
                <span>Página {{ ventas.current_page }} de {{ ventas.last_page }}</span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!ventas.next_page_url"
                    @click="irA(ventas.next_page_url)"
                >
                    Siguiente
                </Button>
            </div>
        </div>
    </div>

    <Dialog
        :open="ventaAAnular !== null"
        @update:open="(v) => !v && (ventaAAnular = null)"
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>¿Anular venta?</DialogTitle>
                <DialogDescription>
                    Se devolverá el stock de la venta
                    <strong>#{{ ventaAAnular?.id }}</strong> y se borrarán sus cuotas.
                    Solo es posible si ninguna cuota fue pagada.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="anulando"
                    @click="confirmarAnular"
                >
                    Anular
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
