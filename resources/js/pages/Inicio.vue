<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff, ShoppingCart } from '@lucide/vue';
import { computed } from 'vue';
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

type CategoriaTienda = {
    id: number;
    nombre: string;
    descripcion: string | null;
    foto_url: string | null;
};

const props = defineProps<{
    productos: ProductoTienda[];
    carritoCount: number;
    categorias: CategoriaTienda[];
    categoriaActiva: number | null;
    filtros: { q: string | null };
}>();

// Categoria activa (objeto) para mostrar su banner; null = "Todas".
const bannerCategoria = computed<CategoriaTienda | null>(
    () => props.categorias.find((c) => c.id === props.categoriaActiva) ?? null,
);

// Galeria tipo "bento" (cantidad limitada, ordenada por `orden` desde el server). Solo se ve cuando
// no hay filtro activo (al elegir una categoria se reemplaza por su banner).
const galeria = computed<CategoriaTienda[]>(() => props.categorias.slice(0, 5));

// Tamanos variados (alto/ancho) por posicion: teselan una cuadricula de 4 columnas sin huecos.
const spanGaleria = [
    'col-span-2 row-span-2',
    'col-span-2 row-span-1',
    'col-span-1 row-span-1',
    'col-span-1 row-span-1',
    'col-span-2 md:col-span-4 row-span-1',
];

// Degradados de respaldo cuando una categoria aun no tiene foto subida.
const gradientes = [
    'from-emerald-600 to-emerald-400',
    'from-sky-600 to-sky-400',
    'from-violet-600 to-violet-400',
    'from-amber-600 to-amber-400',
    'from-rose-600 to-rose-400',
];

// Filtra por categoria sin crear URLs nuevas: solo cambia el query param de /inicio. Preserva el
// término del buscador global (`q`) para que categoría y búsqueda se combinen.
function filtrarPorCategoria(id: number | null): void {
    const data: Record<string, string | number> = {};
    if (id) data.categoria = id;
    if (props.filtros.q) data.q = props.filtros.q;
    router.get(inicio().url, data, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

// Limpia solo la búsqueda (mantiene la categoría activa).
function limpiarBusqueda(): void {
    const data: Record<string, string | number> = {};
    if (props.categoriaActiva) data.categoria = props.categoriaActiva;
    router.get(inicio().url, data, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

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

        <!-- Filtro por categoría (no crea URLs nuevas: solo /inicio?categoria=ID). -->
        <div v-if="categorias.length > 0" class="flex flex-wrap gap-2">
            <Button
                size="sm"
                :variant="categoriaActiva === null ? 'default' : 'outline'"
                @click="filtrarPorCategoria(null)"
            >
                Todas
            </Button>
            <Button
                v-for="c in categorias"
                :key="c.id"
                size="sm"
                :variant="categoriaActiva === c.id ? 'default' : 'outline'"
                @click="filtrarPorCategoria(c.id)"
            >
                {{ c.nombre }}
            </Button>
        </div>

        <!-- Búsqueda activa (buscador global): muestra el término y permite limpiarlo. -->
        <div
            v-if="props.filtros.q"
            class="flex items-center gap-2 text-sm text-muted-foreground"
        >
            <span>Resultados para «{{ props.filtros.q }}»</span>
            <Button variant="outline" size="sm" @click="limpiarBusqueda">
                Limpiar búsqueda
            </Button>
        </div>

        <!-- Galería de categorías (bento): solo cuando no hay filtro (ni categoría ni búsqueda). Clic = filtra. -->
        <div
            v-if="!props.filtros.q && categoriaActiva === null && galeria.length > 0"
            class="grid auto-rows-[120px] grid-cols-2 gap-3 md:grid-cols-4"
        >
            <button
                v-for="(c, i) in galeria"
                :key="c.id"
                type="button"
                :class="spanGaleria[i]"
                class="group relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                @click="filtrarPorCategoria(c.id)"
            >
                <img
                    v-if="c.foto_url"
                    :src="c.foto_url"
                    :alt="c.nombre"
                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                />
                <div
                    v-else
                    :class="gradientes[i % gradientes.length]"
                    class="h-full w-full bg-gradient-to-br transition-transform duration-300 group-hover:scale-105"
                />
                <div
                    class="absolute inset-0 flex items-end bg-gradient-to-t from-black/60 to-transparent p-3"
                >
                    <span class="text-lg font-bold text-white drop-shadow">
                        {{ c.nombre }}
                    </span>
                </div>
            </button>
        </div>

        <!-- Banner de la categoría activa (foto + nombre + descripción). -->
        <div
            v-if="bannerCategoria"
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <img
                v-if="bannerCategoria.foto_url"
                :src="bannerCategoria.foto_url"
                :alt="bannerCategoria.nombre"
                class="h-40 w-full object-cover"
            />
            <div
                v-else
                class="h-40 w-full bg-gradient-to-r from-emerald-600 to-emerald-400"
            />
            <div
                class="absolute inset-0 flex flex-col justify-end gap-1 bg-black/40 p-5 text-white"
            >
                <h2 class="text-2xl font-bold">{{ bannerCategoria.nombre }}</h2>
                <p
                    v-if="bannerCategoria.descripcion"
                    class="max-w-2xl text-sm text-white/90"
                >
                    {{ bannerCategoria.descripcion }}
                </p>
            </div>
        </div>

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
                        class="absolute top-2 left-2 rounded-md bg-emerald-600 px-2.5 py-1 text-base font-bold text-white shadow-md"
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
