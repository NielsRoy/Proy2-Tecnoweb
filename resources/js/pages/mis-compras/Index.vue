<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/mis-compras';

type CompraItem = {
    id: number;
    fecha: string | null;
    items: number;
    monto_total: string;
    tipo_pago: string;
    estado_pago: string;
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
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mis compras', href: index() }],
    },
});

function irA(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    }
}
</script>

<template>
    <Head title="Mis compras" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Mis compras</h1>
            <p class="text-sm text-muted-foreground">
                Historial de tus compras. Abre una para ver el detalle de productos
                y cuotas.
            </p>
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
                        <th class="p-3 text-right font-medium">Ítems</th>
                        <th class="p-3 text-right font-medium">Total</th>
                        <th class="p-3 text-left font-medium">Pago</th>
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
                        <td class="p-3 text-right">{{ c.items }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ c.monto_total }}
                        </td>
                        <td class="p-3">
                            <div class="flex flex-col gap-1">
                                <span class="capitalize">{{ c.tipo_pago }}</span>
                                <Badge
                                    :variant="
                                        c.estado_pago === 'pagada'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    class="w-fit"
                                >
                                    {{
                                        c.estado_pago === 'pagada'
                                            ? 'Pagada'
                                            : 'Pendiente'
                                    }}
                                </Badge>
                            </div>
                        </td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    c.estado === 'anulada'
                                        ? 'destructive'
                                        : 'outline'
                                "
                            >
                                {{ c.estado === 'anulada' ? 'Anulada' : 'Registrada' }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="show(c.id)">Ver</Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="compras.data.length === 0">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            Todavía no tienes compras.
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
</template>
