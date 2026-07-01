<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store } from '@/routes/ventas';

type ClienteOpcion = { id: number; name: string };
type ProductoOpcion = {
    id: number;
    nombre: string;
    precio: number;
    stock: number;
    promocion_id: number | null;
    promocion_nombre: string | null;
    precio_final: number;
};
type Credito = {
    montoMinimo: number;
    tramosCuotas: { hasta: number | null; cuotas: number }[];
    tramosInteres: { hastaCuotas: number; interes: number }[];
};
type Linea = { producto_id: string; cantidad: string };

const props = defineProps<{
    clientes: ClienteOpcion[];
    productos: ProductoOpcion[];
    metodos: string[];
    credito: Credito;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ventas', href: index() }],
    },
});

// Fecha local del dispositivo (no toISOString, que da UTC y salta de día en Bolivia).
const ahora = new Date();
const hoy = `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`;

function lineaVacia(): Linea {
    return { producto_id: '', cantidad: '1' };
}

const form = useForm<{
    cliente_id: string;
    fecha_venta: string;
    direccion_envio: string;
    tipo_pago: string;
    metodo: string;
    numero_cuotas: string;
    lineas: Linea[];
}>({
    cliente_id: '',
    fecha_venta: hoy,
    direccion_envio: '',
    tipo_pago: 'contado',
    metodo: 'efectivo',
    numero_cuotas: '2',
    lineas: [lineaVacia()],
});

const esCredito = computed(() => form.tipo_pago === 'credito');

function productoDe(id: string): ProductoOpcion | undefined {
    return props.productos.find((p) => String(p.id) === id);
}

function agregarLinea(): void {
    form.lineas.push(lineaVacia());
}

function quitarLinea(i: number): void {
    form.lineas.splice(i, 1);
    if (form.lineas.length === 0) {
        agregarLinea();
    }
}

function subtotal(linea: Linea): number {
    const p = productoDe(linea.producto_id);
    const cant = Number(linea.cantidad) || 0;
    return p ? Math.round(p.precio_final * cant * 100) / 100 : 0;
}

// Monto base = Σ subtotales (con promo aplicada).
const base = computed(
    () => Math.round(form.lineas.reduce((acc, l) => acc + subtotal(l), 0) * 100) / 100,
);

// Espejo de App\Support\PlanPago para el preview (el server recalcula al guardar).
const cuotasMaximas = computed<number>(() => {
    if (base.value < props.credito.montoMinimo) {
        return 0;
    }
    const tramo = props.credito.tramosCuotas.find(
        (t) => t.hasta === null || base.value <= t.hasta,
    );
    return tramo ? tramo.cuotas : 0;
});

function interesDe(n: number): number {
    const tramo = props.credito.tramosInteres.find((t) => n <= t.hastaCuotas);
    return tramo ? tramo.interes : 0;
}

const cuotasDisponibles = computed<number[]>(() => {
    const arr: number[] = [];
    for (let n = 2; n <= cuotasMaximas.value; n++) {
        arr.push(n);
    }
    return arr;
});

const totalConInteres = computed<number>(() => {
    const n = Number(form.numero_cuotas) || 0;
    if (!esCredito.value || n < 2) {
        return base.value;
    }
    return Math.round(base.value * (1 + interesDe(n)) * 100) / 100;
});

// Interés (%) aplicable al nº de cuotas elegido, para mostrarlo en el mensaje.
const interesPct = computed<number>(() => {
    const n = Number(form.numero_cuotas) || 0;
    return esCredito.value && n >= 2 ? Math.round(interesDe(n) * 100) : 0;
});

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
    <Head title="Nueva venta" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Nueva venta</h1>
            <p class="text-sm text-muted-foreground">
                Registra una venta a un cliente. Las promociones vigentes se aplican
                automáticamente por producto.
            </p>
        </header>

        <form class="max-w-3xl space-y-6" @submit.prevent="enviar">
            <!-- Cabecera -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="cliente_id">Cliente</Label>
                    <select
                        id="cliente_id"
                        v-model="form.cliente_id"
                        :class="selectClass"
                        required
                    >
                        <option value="" disabled>Selecciona un cliente…</option>
                        <option
                            v-for="c in clientes"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.cliente_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="fecha_venta">Fecha de venta</Label>
                    <Input
                        id="fecha_venta"
                        type="date"
                        v-model="form.fecha_venta"
                        required
                    />
                    <InputError :message="form.errors.fecha_venta" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="direccion_envio">
                        Dirección de envío
                        <span class="font-normal text-muted-foreground">(opcional)</span>
                    </Label>
                    <Input
                        id="direccion_envio"
                        v-model="form.direccion_envio"
                        placeholder="Ej. Av. Siempre Viva 742"
                    />
                    <InputError :message="form.errors.direccion_envio" />
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
                                <th class="p-3 text-right font-medium">
                                    Precio unit.
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
                                    >
                                        <option value="" disabled>
                                            Selecciona…
                                        </option>
                                        <option
                                            v-for="p in productos"
                                            :key="p.id"
                                            :value="String(p.id)"
                                        >
                                            {{ p.nombre }} (stock {{ p.stock }})
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
                                <td class="p-3 text-right whitespace-nowrap">
                                    <template v-if="productoDe(linea.producto_id)">
                                        <template
                                            v-if="
                                                productoDe(linea.producto_id)!
                                                    .promocion_id
                                            "
                                        >
                                            <div
                                                class="text-muted-foreground line-through"
                                            >
                                                Bs
                                                {{
                                                    productoDe(
                                                        linea.producto_id,
                                                    )!.precio.toFixed(2)
                                                }}
                                            </div>
                                            <div
                                                class="font-medium text-emerald-600 dark:text-emerald-400"
                                            >
                                                Bs
                                                {{
                                                    productoDe(
                                                        linea.producto_id,
                                                    )!.precio_final.toFixed(2)
                                                }}
                                            </div>
                                            <div
                                                class="text-xs text-emerald-600 dark:text-emerald-400"
                                            >
                                                {{
                                                    productoDe(linea.producto_id)!
                                                        .promocion_nombre
                                                }}
                                            </div>
                                        </template>
                                        <template v-else>
                                            Bs
                                            {{
                                                productoDe(
                                                    linea.producto_id,
                                                )!.precio.toFixed(2)
                                            }}
                                        </template>
                                    </template>
                                    <span v-else class="text-muted-foreground">—</span>
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
                                <td class="p-3" colspan="3">Monto base</td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    Bs {{ base.toFixed(2) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Pago -->
            <div class="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="grid gap-2">
                    <Label for="tipo_pago">Tipo de pago</Label>
                    <select
                        id="tipo_pago"
                        v-model="form.tipo_pago"
                        :class="selectClass"
                    >
                        <option value="contado">Contado (se paga ahora)</option>
                        <option value="credito">Crédito (en cuotas)</option>
                    </select>
                    <InputError :message="form.errors.tipo_pago" />
                    <p v-if="esCredito" class="text-sm text-muted-foreground">
                        Interés fijo según el número de cuotas: 2–3 cuotas 5%, 4–6
                        cuotas 10%, 7–9 cuotas 15%, 10–12 cuotas 20%.
                    </p>
                </div>

                <!-- Contado: método de pago -->
                <div v-if="!esCredito" class="grid gap-2">
                    <Label for="metodo">Método de pago</Label>
                    <select id="metodo" v-model="form.metodo" :class="selectClass">
                        <option v-for="m in metodos" :key="m" :value="m">
                            {{ m === 'qr' ? 'QR' : m.charAt(0).toUpperCase() + m.slice(1) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.metodo" />
                    <p class="text-sm text-muted-foreground">
                        Total a cobrar: <strong>Bs {{ base.toFixed(2) }}</strong>
                    </p>
                </div>

                <!-- Crédito: cuotas -->
                <div v-else class="grid gap-2">
                    <Label for="numero_cuotas">Número de cuotas (mensuales)</Label>
                    <select
                        id="numero_cuotas"
                        v-model="form.numero_cuotas"
                        :class="selectClass"
                        :disabled="cuotasDisponibles.length === 0"
                    >
                        <option
                            v-for="n in cuotasDisponibles"
                            :key="n"
                            :value="String(n)"
                        >
                            {{ n }} cuotas
                        </option>
                    </select>
                    <InputError :message="form.errors.numero_cuotas" />
                    <p
                        v-if="base < credito.montoMinimo"
                        class="text-sm text-destructive"
                    >
                        El monto base debe ser al menos Bs {{ credito.montoMinimo }}
                        para vender a crédito.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Máximo {{ cuotasMaximas }} cuotas para este monto. Total con
                        {{ interesPct }}% de interés:
                        <strong>Bs {{ totalConInteres.toFixed(2) }}</strong>
                        (≈ Bs
                        {{
                            (
                                totalConInteres / (Number(form.numero_cuotas) || 1)
                            ).toFixed(2)
                        }}
                        por cuota mensual).
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    Registrar venta
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
