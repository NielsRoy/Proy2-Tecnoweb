<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import { reactive, ref } from 'vue';
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
import { create, destroy, edit, index, reporte } from '@/routes/productos';

type ProductoItem = {
    id: number;
    nombre: string;
    descripcion: string | null;
    categoria: string | null;
    precio: string;
    stock: number;
    foto_url: string | null;
};

type Filtros = {
    categoria_id: number | null;
    precio_min: number | null;
    precio_max: number | null;
    stock_min: number | null;
    stock_max: number | null;
};

const props = defineProps<{
    productos: ProductoItem[];
    filtros: Filtros;
    categorias: { id: number; nombre: string }[];
    puedeCrear: boolean;
    puedeEditar: boolean;
    puedeEliminar: boolean;
    puedeReportar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Productos', href: index() }],
    },
});

const filtros = reactive({
    categoria_id:
        props.filtros.categoria_id != null
            ? String(props.filtros.categoria_id)
            : '',
    precio_min:
        props.filtros.precio_min != null ? String(props.filtros.precio_min) : '',
    precio_max:
        props.filtros.precio_max != null ? String(props.filtros.precio_max) : '',
    stock_min:
        props.filtros.stock_min != null ? String(props.filtros.stock_min) : '',
    stock_max:
        props.filtros.stock_max != null ? String(props.filtros.stock_max) : '',
});

function queryFiltros(): Record<string, string> {
    const query: Record<string, string> = {};
    Object.entries(filtros).forEach(([clave, valor]) => {
        if (valor !== '' && valor != null) {
            query[clave] = String(valor);
        }
    });
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
    filtros.categoria_id = '';
    filtros.precio_min = '';
    filtros.precio_max = '';
    filtros.stock_min = '';
    filtros.stock_max = '';
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';

const productoAEliminar = ref<ProductoItem | null>(null);
const eliminando = ref(false);

function confirmarEliminar(): void {
    if (!productoAEliminar.value) {
        return;
    }
    router.delete(destroy(productoAEliminar.value.id).url, {
        preserveScroll: true,
        onStart: () => (eliminando.value = true),
        onFinish: () => {
            eliminando.value = false;
            productoAEliminar.value = null;
        },
    });
}
</script>

<template>
    <Head title="Productos" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Productos</h1>
                <p class="text-sm text-muted-foreground">
                    Catálogo de la tienda. El stock se ajusta con compras, ventas
                    e inventario (no se edita aquí).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <BotonesReporte
                    v-if="puedeReportar"
                    :url="reporte().url"
                    :query="queryFiltros()"
                />
                <Button v-if="puedeCrear" as-child>
                    <Link :href="create()">Nuevo producto</Link>
                </Button>
            </div>
        </header>

        <!-- Filtros -->
        <div
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-2 lg:grid-cols-6 dark:border-sidebar-border"
        >
            <div class="grid gap-1.5">
                <Label for="f-categoria">Categoría</Label>
                <select
                    id="f-categoria"
                    v-model="filtros.categoria_id"
                    :class="selectClass"
                >
                    <option value="">Todas</option>
                    <option
                        v-for="c in categorias"
                        :key="c.id"
                        :value="String(c.id)"
                    >
                        {{ c.nombre }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-precio-min">Precio mín (Bs)</Label>
                <Input
                    id="f-precio-min"
                    type="number"
                    min="0"
                    step="0.01"
                    v-model="filtros.precio_min"
                    placeholder="0"
                    @keyup.enter="aplicar"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-precio-max">Precio máx (Bs)</Label>
                <Input
                    id="f-precio-max"
                    type="number"
                    min="0"
                    step="0.01"
                    v-model="filtros.precio_max"
                    placeholder="—"
                    @keyup.enter="aplicar"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-stock-min">Stock mín</Label>
                <Input
                    id="f-stock-min"
                    type="number"
                    min="0"
                    step="1"
                    v-model="filtros.stock_min"
                    placeholder="0"
                    @keyup.enter="aplicar"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-stock-max">Stock máx</Label>
                <Input
                    id="f-stock-max"
                    type="number"
                    min="0"
                    step="1"
                    v-model="filtros.stock_max"
                    placeholder="—"
                    @keyup.enter="aplicar"
                />
            </div>
            <div class="flex items-end gap-2">
                <Button @click="aplicar">Filtrar</Button>
                <Button variant="outline" @click="limpiar">Limpiar</Button>
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
                        <th class="p-3 text-left font-medium">Foto</th>
                        <th class="p-3 text-left font-medium">Nombre</th>
                        <th class="p-3 text-left font-medium">Categoría</th>
                        <th class="p-3 text-right font-medium">Precio</th>
                        <th class="p-3 text-right font-medium">Stock</th>
                        <th
                            v-if="puedeEditar || puedeEliminar"
                            class="p-3 text-right font-medium"
                        >
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="producto in productos"
                        :key="producto.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3">
                            <img
                                v-if="producto.foto_url"
                                :src="producto.foto_url"
                                :alt="producto.nombre"
                                class="h-12 w-12 rounded-md border border-sidebar-border/70 object-cover dark:border-sidebar-border"
                            />
                            <div
                                v-else
                                class="flex h-12 w-12 items-center justify-center rounded-md border border-dashed border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border"
                            >
                                <ImageOff class="h-5 w-5" />
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium">{{ producto.nombre }}</div>
                            <div
                                v-if="producto.descripcion"
                                class="max-w-md truncate text-xs text-muted-foreground"
                            >
                                {{ producto.descripcion }}
                            </div>
                        </td>
                        <td class="p-3">
                            <span v-if="producto.categoria">
                                {{ producto.categoria }}
                            </span>
                            <span v-else class="text-muted-foreground">
                                Sin categoría
                            </span>
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ producto.precio }}
                        </td>
                        <td class="p-3 text-right">{{ producto.stock }}</td>
                        <td
                            v-if="puedeEditar || puedeEliminar"
                            class="p-3 text-right"
                        >
                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="puedeEditar"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(producto.id)">Editar</Link>
                                </Button>
                                <Button
                                    v-if="puedeEliminar"
                                    variant="destructive"
                                    size="sm"
                                    @click="productoAEliminar = producto"
                                >
                                    Eliminar
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="productos.length === 0">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay productos que coincidan con los filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog
        :open="productoAEliminar !== null"
        @update:open="(v) => !v && (productoAEliminar = null)"
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>¿Eliminar producto?</DialogTitle>
                <DialogDescription>
                    Se dará de baja
                    <strong>{{ productoAEliminar?.nombre }}</strong> del
                    catálogo. Su historial de ventas y compras se conserva.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="eliminando"
                    @click="confirmarEliminar"
                >
                    Eliminar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
