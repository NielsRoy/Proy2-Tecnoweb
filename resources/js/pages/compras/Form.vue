<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store } from '@/routes/compras';

type ProveedorOpcion = { id: number; name: string };
type ProductoOpcion = { id: number; nombre: string; precio: string; stock: number };

type Linea = {
    producto_id: string;
    cantidad: string;
    precio_unitario: string;
};

const props = defineProps<{
    proveedores: ProveedorOpcion[];
    productos: ProductoOpcion[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Compras', href: index() }],
    },
});

// Fecha de HOY en la zona horaria del dispositivo. NO usar toISOString (devuelve UTC: en
// Bolivia, UTC-4, por la tarde/noche ya marca el dia siguiente).
const ahora = new Date();
const hoy = `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`;

function lineaVacia(): Linea {
    return { producto_id: '', cantidad: '1', precio_unitario: '' };
}

const form = useForm<{
    proveedor_id: string;
    fecha_compra: string;
    lineas: Linea[];
}>({
    proveedor_id: '',
    fecha_compra: hoy,
    lineas: [lineaVacia()],
});

function agregarLinea(): void {
    form.lineas.push(lineaVacia());
}

function quitarLinea(i: number): void {
    form.lineas.splice(i, 1);
    if (form.lineas.length === 0) {
        agregarLinea();
    }
}

// Al elegir un producto, autocompletar el precio con el del catálogo (editable).
function onProducto(linea: Linea): void {
    const p = props.productos.find((p) => String(p.id) === linea.producto_id);
    if (p && linea.precio_unitario === '') {
        linea.precio_unitario = p.precio;
    }
}

function subtotal(linea: Linea): number {
    const cant = Number(linea.cantidad) || 0;
    const precio = Number(linea.precio_unitario) || 0;
    return Math.round(cant * precio * 100) / 100;
}

const total = computed(() =>
    form.lineas.reduce((acc, l) => acc + subtotal(l), 0),
);

function errorLinea(i: number, campo: string): string | undefined {
    return (form.errors as Record<string, string>)[`lineas.${i}.${campo}`];
}

function enviar(): void {
    form.post(store().url, { preserveScroll: true });
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Head title="Nueva compra" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Nueva compra</h1>
            <p class="text-sm text-muted-foreground">
                Registra una compra a un proveedor. Cada producto ingresa stock al
                inventario.
            </p>
        </header>

        <form class="max-w-3xl space-y-6" @submit.prevent="enviar">
            <!-- Cabecera -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="proveedor_id">Proveedor</Label>
                    <select
                        id="proveedor_id"
                        v-model="form.proveedor_id"
                        :class="selectClass"
                        required
                    >
                        <option value="" disabled>Selecciona un proveedor…</option>
                        <option
                            v-for="p in proveedores"
                            :key="p.id"
                            :value="String(p.id)"
                        >
                            {{ p.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.proveedor_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="fecha_compra">Fecha de compra</Label>
                    <Input
                        id="fecha_compra"
                        type="date"
                        v-model="form.fecha_compra"
                        required
                    />
                    <InputError :message="form.errors.fecha_compra" />
                </div>
            </div>

            <!-- Líneas -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium">Productos</h2>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="agregarLinea"
                    >
                        Agregar producto
                    </Button>
                </div>

                <InputError :message="form.errors.lineas" />

                <div
                    class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <th class="p-3 text-left font-medium">Producto</th>
                                <th class="p-3 text-left font-medium">Cantidad</th>
                                <th class="p-3 text-left font-medium">
                                    Precio unit. (Bs)
                                </th>
                                <th class="p-3 text-right font-medium">Subtotal</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(linea, i) in form.lineas"
                                :key="i"
                                class="border-b border-sidebar-border/70 align-top last:border-0 dark:border-sidebar-border"
                            >
                                <td class="p-3">
                                    <select
                                        v-model="linea.producto_id"
                                        :class="selectClass"
                                        required
                                        @change="onProducto(linea)"
                                    >
                                        <option value="" disabled>
                                            Selecciona…
                                        </option>
                                        <option
                                            v-for="p in productos"
                                            :key="p.id"
                                            :value="String(p.id)"
                                        >
                                            {{ p.nombre }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="errorLinea(i, 'producto_id')"
                                    />
                                </td>
                                <td class="p-3">
                                    <Input
                                        type="number"
                                        min="1"
                                        step="1"
                                        v-model="linea.cantidad"
                                        required
                                        class="w-24"
                                    />
                                    <InputError
                                        :message="errorLinea(i, 'cantidad')"
                                    />
                                </td>
                                <td class="p-3">
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        v-model="linea.precio_unitario"
                                        required
                                        class="w-32"
                                        placeholder="0.00"
                                    />
                                    <InputError
                                        :message="errorLinea(i, 'precio_unitario')"
                                    />
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    Bs {{ subtotal(linea).toFixed(2) }}
                                </td>
                                <td class="p-3 text-right">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        @click="quitarLinea(i)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="font-medium">
                                <td class="p-3" colspan="3">Total</td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    Bs {{ total.toFixed(2) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    Registrar compra
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
