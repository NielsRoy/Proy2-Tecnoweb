<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store } from '@/routes/inventarios';

type ProductoOpcion = { id: number; nombre: string; stock: number };

const props = defineProps<{
    productos: ProductoOpcion[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inventarios', href: index() }],
    },
});

// Fecha de HOY en la zona horaria del dispositivo. NO usar toISOString (devuelve UTC: en
// Bolivia, UTC-4, por la tarde/noche ya marca el dia siguiente).
const ahora = new Date();
const hoy = `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`;

const form = useForm<{
    producto_id: string;
    tipo_movimiento: string;
    cantidad: string;
    fecha_movimiento: string;
}>({
    producto_id: '',
    tipo_movimiento: 'ingreso',
    cantidad: '',
    fecha_movimiento: hoy,
});

// Stock actual del producto elegido, para mostrarlo como ayuda.
const stockActual = computed<number | null>(() => {
    const p = props.productos.find((p) => String(p.id) === form.producto_id);
    return p ? p.stock : null;
});

function enviar(): void {
    form.post(store().url, { preserveScroll: true });
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Head title="Nuevo ajuste de inventario" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Nuevo ajuste de inventario</h1>
            <p class="text-sm text-muted-foreground">
                Registra un ingreso o salida manual de stock (motivo: ajuste). Una
                salida no puede dejar el stock en negativo.
            </p>
        </header>

        <form class="max-w-xl space-y-6" @submit.prevent="enviar">
            <div class="grid gap-2">
                <Label for="producto_id">Producto</Label>
                <select
                    id="producto_id"
                    v-model="form.producto_id"
                    :class="selectClass"
                    required
                >
                    <option value="" disabled>Selecciona un producto…</option>
                    <option v-for="p in productos" :key="p.id" :value="String(p.id)">
                        {{ p.nombre }}
                    </option>
                </select>
                <p
                    v-if="stockActual !== null"
                    class="text-xs text-muted-foreground"
                >
                    Stock actual: {{ stockActual }} unidades.
                </p>
                <InputError :message="form.errors.producto_id" />
            </div>

            <div class="grid gap-2">
                <Label for="tipo_movimiento">Tipo de movimiento</Label>
                <select
                    id="tipo_movimiento"
                    v-model="form.tipo_movimiento"
                    :class="selectClass"
                    required
                >
                    <option value="ingreso">Ingreso (suma stock)</option>
                    <option value="salida">Salida (resta stock)</option>
                </select>
                <InputError :message="form.errors.tipo_movimiento" />
            </div>

            <div class="grid gap-2">
                <Label for="cantidad">Cantidad</Label>
                <Input
                    id="cantidad"
                    type="number"
                    min="1"
                    step="1"
                    v-model="form.cantidad"
                    required
                    placeholder="0"
                />
                <InputError :message="form.errors.cantidad" />
            </div>

            <div class="grid gap-2">
                <Label for="fecha_movimiento">Fecha</Label>
                <Input
                    id="fecha_movimiento"
                    type="date"
                    v-model="form.fecha_movimiento"
                    required
                />
                <InputError :message="form.errors.fecha_movimiento" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    Registrar movimiento
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
