<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff, ShoppingCart } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { inicio } from '@/routes';
import { agregar, index as carritoIndex } from '@/routes/carrito';

type ProductoTienda = {
    id: number;
    nombre: string;
    descripcion: string | null;
    foto_url: string | null;
    precio: number;
    precio_final: number;
    promocion_nombre: string | null;
    promocion_tipo: 'porcentaje' | 'monto' | null;
    promocion_valor: number | null;
    stock: number;
};

defineProps<{
    productos: ProductoTienda[];
    carritoCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tienda', href: inicio() }],
    },
});

function agregarAlCarrito(p: ProductoTienda): void {
    router.post(
        agregar().url,
        { producto_id: p.id, cantidad: 1 },
        { preserveScroll: true },
    );
}

// Etiqueta del descuento de la promo vigente: "-N %" o "-N Bs".
function descuentoLabel(p: ProductoTienda): string | null {
    if (!p.promocion_nombre || p.promocion_valor == null) {
        return null;
    }
    const v = p.promocion_valor;
    const num = Number.isInteger(v) ? String(v) : v.toFixed(2);
    return p.promocion_tipo === 'porcentaje' ? `-${num} %` : `-${num} Bs`;
}
</script>

<template>
    <Head title="Tienda" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Tienda</h1>
                <p class="text-sm text-muted-foreground">
                    Explora el catálogo y arma tu compra. Las promociones vigentes ya
                    están aplicadas.
                </p>
            </div>
            <Button as-child>
                <Link :href="carritoIndex()" class="relative">
                    <ShoppingCart class="h-4 w-4" />
                    Carrito
                    <Badge
                        v-if="carritoCount > 0"
                        variant="secondary"
                        class="ml-1"
                    >
                        {{ carritoCount }}
                    </Badge>
                </Link>
            </Button>
        </header>

        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <div
                v-for="p in productos"
                :key="p.id"
                class="flex flex-col overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div
                    class="relative flex aspect-square items-center justify-center bg-muted"
                >
                    <img
                        v-if="p.foto_url"
                        :src="p.foto_url"
                        :alt="p.nombre"
                        class="h-full w-full object-cover"
                    />
                    <ImageOff v-else class="h-10 w-10 text-muted-foreground" />
                    <span
                        v-if="descuentoLabel(p)"
                        class="absolute top-2 left-2 rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-semibold text-white shadow-sm"
                    >
                        {{ descuentoLabel(p) }}
                    </span>
                </div>

                <div class="flex flex-1 flex-col gap-2 p-3">
                    <div class="flex-1 space-y-1">
                        <h2 class="font-medium leading-tight">{{ p.nombre }}</h2>
                        <p
                            v-if="p.descripcion"
                            class="line-clamp-2 text-xs text-muted-foreground"
                        >
                            {{ p.descripcion }}
                        </p>
                    </div>

                    <div>
                        <div
                            v-if="p.promocion_nombre"
                            class="flex items-baseline gap-2"
                        >
                            <span class="text-muted-foreground line-through">
                                Bs {{ p.precio.toFixed(2) }}
                            </span>
                            <span
                                class="font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                Bs {{ p.precio_final.toFixed(2) }}
                            </span>
                        </div>
                        <div v-else class="font-semibold">
                            Bs {{ p.precio.toFixed(2) }}
                        </div>
                        <p
                            v-if="p.promocion_nombre"
                            class="text-xs text-emerald-600 dark:text-emerald-400"
                        >
                            {{ p.promocion_nombre }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-muted-foreground">
                            {{ p.stock > 0 ? `Stock: ${p.stock}` : 'Sin stock' }}
                        </span>
                        <Button
                            size="sm"
                            :disabled="p.stock < 1"
                            @click="agregarAlCarrito(p)"
                        >
                            Agregar
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-if="productos.length === 0"
                class="col-span-full rounded-xl border border-sidebar-border/70 p-6 text-center text-muted-foreground dark:border-sidebar-border"
            >
                No hay productos disponibles por ahora.
            </div>
        </div>
    </div>
</template>
