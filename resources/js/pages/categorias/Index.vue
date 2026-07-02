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
import { create, destroy, edit, index } from '@/routes/categorias';

type CategoriaItem = {
    id: number;
    nombre: string;
    descripcion: string | null;
    orden: number;
    foto_url: string | null;
};

const props = defineProps<{
    categorias: CategoriaItem[];
    filtros: { q: string | null };
    puedeCrear: boolean;
    puedeEditar: boolean;
    puedeEliminar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categorías', href: index() }],
    },
});

// Limpia la búsqueda del buscador global (única "filtro" de esta vista).
function limpiarBusqueda(): void {
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

const categoriaAEliminar = ref<CategoriaItem | null>(null);
const eliminando = ref(false);

function confirmarEliminar(): void {
    if (!categoriaAEliminar.value) {
        return;
    }
    router.delete(destroy(categoriaAEliminar.value.id).url, {
        preserveScroll: true,
        onStart: () => (eliminando.value = true),
        onFinish: () => {
            eliminando.value = false;
            categoriaAEliminar.value = null;
        },
    });
}
</script>

<template>
    <Head title="Categorías" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Categorías</h1>
                <p class="text-sm text-muted-foreground">
                    Agrupan los productos y sirven de banner en la tienda. El
                    «orden» controla en qué secuencia aparecen.
                </p>
            </div>
            <Button v-if="puedeCrear" as-child>
                <Link :href="create()">Nueva categoría</Link>
            </Button>
        </header>

        <!-- Búsqueda activa (desde el buscador global): permite limpiarla. -->
        <div
            v-if="props.filtros.q"
            class="flex items-center gap-2 text-sm text-muted-foreground"
        >
            <span>Resultados para «{{ props.filtros.q }}»</span>
            <Button variant="outline" size="sm" @click="limpiarBusqueda">
                Limpiar
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
                        <th class="p-3 text-left font-medium">Banner</th>
                        <th class="p-3 text-left font-medium">Nombre</th>
                        <th class="p-3 text-right font-medium">Orden</th>
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
                        v-for="categoria in categorias"
                        :key="categoria.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3">
                            <img
                                v-if="categoria.foto_url"
                                :src="categoria.foto_url"
                                :alt="categoria.nombre"
                                class="h-12 w-20 rounded-md border border-sidebar-border/70 object-cover dark:border-sidebar-border"
                            />
                            <div
                                v-else
                                class="flex h-12 w-20 items-center justify-center rounded-md border border-dashed border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border"
                            >
                                <ImageOff class="h-5 w-5" />
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium">{{ categoria.nombre }}</div>
                            <div
                                v-if="categoria.descripcion"
                                class="max-w-md truncate text-xs text-muted-foreground"
                            >
                                {{ categoria.descripcion }}
                            </div>
                        </td>
                        <td class="p-3 text-right">{{ categoria.orden }}</td>
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
                                    <Link :href="edit(categoria.id)">Editar</Link>
                                </Button>
                                <Button
                                    v-if="puedeEliminar"
                                    variant="destructive"
                                    size="sm"
                                    @click="categoriaAEliminar = categoria"
                                >
                                    Eliminar
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="categorias.length === 0">
                        <td
                            colspan="4"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay categorías registradas.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog
        :open="categoriaAEliminar !== null"
        @update:open="(v) => !v && (categoriaAEliminar = null)"
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>¿Eliminar categoría?</DialogTitle>
                <DialogDescription>
                    Se dará de baja
                    <strong>{{ categoriaAEliminar?.nombre }}</strong>. Los
                    productos que la tenían quedarán sin categoría.
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
