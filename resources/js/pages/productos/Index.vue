<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import { ref } from 'vue';
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
import BotonesReporte from '@/components/BotonesReporte.vue';
import { create, destroy, edit, index, reporte } from '@/routes/productos';

type ProductoItem = {
    id: number;
    nombre: string;
    descripcion: string | null;
    precio: string;
    stock: number;
    foto_url: string | null;
};

defineProps<{
    productos: ProductoItem[];
    puedeCrear: boolean;
    puedeEditar: boolean;
    puedeEliminar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Productos', href: index() }],
    },
});

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
                <BotonesReporte :url="reporte().url" />
                <Button v-if="puedeCrear" as-child>
                    <Link :href="create()">Nuevo producto</Link>
                </Button>
            </div>
        </header>

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
                            colspan="5"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay productos registrados.
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
