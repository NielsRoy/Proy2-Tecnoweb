<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import BotonesReporte from '@/components/BotonesReporte.vue';
import { create, destroy, index, reporte, show } from '@/routes/compras';

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

type Filtros = {
    q: string | null;
    proveedor_id: number | null;
    estado: string | null;
    desde: string | null;
    hasta: string | null;
};

const props = defineProps<{
    compras: Paginado;
    filtros: Filtros;
    esProveedor: boolean;
    proveedores: { id: number; name: string }[];
    puedeCrear: boolean;
    puedeEliminar: boolean;
    puedeReportar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Compras', href: index() }],
    },
});

const filtros = reactive({
    proveedor_id:
        props.filtros.proveedor_id != null
            ? String(props.filtros.proveedor_id)
            : '',
    estado: props.filtros.estado ?? '',
    desde: props.filtros.desde ?? '',
    hasta: props.filtros.hasta ?? '',
});

function queryFiltros(): Record<string, string> {
    const query: Record<string, string> = {};
    Object.entries(filtros).forEach(([clave, valor]) => {
        if (valor !== '' && valor != null) {
            query[clave] = String(valor);
        }
    });
    // Preserva el término del buscador global (filtro `q`) al filtrar o generar reportes.
    if (props.filtros.q) {
        query.q = props.filtros.q;
    }
    return query;
}

function aplicar(): void {
    router.get(index().url, queryFiltros(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function limpiar(): void {
    filtros.proveedor_id = '';
    filtros.estado = '';
    filtros.desde = '';
    filtros.hasta = '';
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

function irA(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    }
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';

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
    <Head :title="esProveedor ? 'Mis ventas' : 'Compras'" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">
                    {{ esProveedor ? 'Mis ventas' : 'Compras' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    <template v-if="esProveedor">
                        Tus ventas a la tienda: las compras que la tienda te ha
                        registrado.
                    </template>
                    <template v-else>
                        Compras a proveedores. Cada compra ingresa stock al
                        inventario. Anular revierte el stock y conserva el registro.
                    </template>
                </p>
            </div>
            <Button v-if="puedeCrear" as-child>
                <Link :href="create()">Nueva compra</Link>
            </Button>
        </header>

        <!-- Filtros -->
        <div
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border"
        >
            <div v-if="!esProveedor" class="grid gap-1.5">
                <Label for="f-proveedor">Proveedor</Label>
                <select
                    id="f-proveedor"
                    v-model="filtros.proveedor_id"
                    :class="selectClass"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="p in proveedores"
                        :key="p.id"
                        :value="String(p.id)"
                    >
                        {{ p.name }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-estado">Estado</Label>
                <select id="f-estado" v-model="filtros.estado" :class="selectClass">
                    <option value="">Todos</option>
                    <option value="registrada">Registrada</option>
                    <option value="anulada">Anulada</option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-desde">Desde</Label>
                <Input id="f-desde" type="date" v-model="filtros.desde" />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-hasta">Hasta</Label>
                <Input id="f-hasta" type="date" v-model="filtros.hasta" />
            </div>
            <div
                class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4"
            >
                <Button @click="aplicar">Filtrar</Button>
                <Button variant="outline" @click="limpiar">Limpiar</Button>
                <BotonesReporte
                    v-if="puedeReportar"
                    class="ml-auto"
                    :url="reporte().url"
                    :query="queryFiltros()"
                />
            </div>
        </div>

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
                            No hay compras que coincidan con los filtros.
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
