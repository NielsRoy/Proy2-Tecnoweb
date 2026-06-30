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
import { create, destroy, index, show } from '@/routes/compras';

type CompraItem = {
    id: number;
    fecha: string | null;
    proveedor: string | null;
    items: number;
    monto_total: string;
    estado: string;
};

type Paginado = {
    data: CompraItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

defineProps<{
    compras: Paginado;
    puedeCrear: boolean;
    puedeEliminar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Compras', href: index() }],
    },
});

function irA(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    }
}

const compraAAnular = ref<CompraItem | null>(null);
const anulando = ref(false);

function confirmarAnular(): void {
    if (!compraAAnular.value) {
        return;
    }
    router.delete(destroy(compraAAnular.value.id).url, {
        preserveScroll: true,
        onStart: () => (anulando.value = true),
        onFinish: () => {
            anulando.value = false;
            compraAAnular.value = null;
        },
    });
}
</script>

<template>
    <Head title="Compras" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Compras</h1>
                <p class="text-sm text-muted-foreground">
                    Compras a proveedores. Cada compra ingresa stock al inventario.
                    Anular revierte el stock y conserva el registro.
                </p>
            </div>
            <Button v-if="puedeCrear" as-child>
                <Link :href="create()">Nueva compra</Link>
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
                        <th class="p-3 text-left font-medium">Proveedor</th>
                        <th class="p-3 text-right font-medium">Ítems</th>
                        <th class="p-3 text-right font-medium">Total</th>
                        <th class="p-3 text-left font-medium">Estado</th>
                        <th class="p-3 text-right font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in compras.data"
                        :key="c.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ c.fecha }}
                        </td>
                        <td class="p-3 font-medium">{{ c.proveedor ?? '—' }}</td>
                        <td class="p-3 text-right">{{ c.items }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ c.monto_total }}
                        </td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    c.estado === 'anulada'
                                        ? 'destructive'
                                        : 'default'
                                "
                            >
                                {{ c.estado === 'anulada' ? 'Anulada' : 'Registrada' }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="show(c.id)">Ver</Link>
                                </Button>
                                <Button
                                    v-if="puedeEliminar && c.estado !== 'anulada'"
                                    variant="destructive"
                                    size="sm"
                                    @click="compraAAnular = c"
                                >
                                    Anular
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="compras.data.length === 0">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay compras registradas.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="compras.total > 0"
            class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        >
            <span>
                Mostrando {{ compras.from }}–{{ compras.to }} de
                {{ compras.total }}
            </span>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!compras.prev_page_url"
                    @click="irA(compras.prev_page_url)"
                >
                    Anterior
                </Button>
                <span>
                    Página {{ compras.current_page }} de {{ compras.last_page }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!compras.next_page_url"
                    @click="irA(compras.next_page_url)"
                >
                    Siguiente
                </Button>
            </div>
        </div>
    </div>

    <Dialog
        :open="compraAAnular !== null"
        @update:open="(v) => !v && (compraAAnular = null)"
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>¿Anular compra?</DialogTitle>
                <DialogDescription>
                    Se revertirá el stock que ingresó la compra
                    <strong>#{{ compraAAnular?.id }}</strong> y quedará marcada como
                    anulada. Solo es posible si el stock actual alcanza para revertir
                    cada producto.
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
