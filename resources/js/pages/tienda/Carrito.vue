<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImageOff, Minus, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { inicio } from '@/routes';
import { actualizar, checkout, index, quitar } from '@/routes/carrito';

type LineaCarrito = {
    producto_id: number;
    nombre: string;
    foto_url: string | null;
    precio: number;
    precio_final: number;
    promocion_nombre: string | null;
    stock: number;
    cantidad: number;
    subtotal: number;
};
type Credito = {
    montoMinimo: number;
    tramosCuotas: { hasta: number | null; cuotas: number }[];
    tramosInteres: { hastaCuotas: number; interes: number }[];
};

const props = defineProps<{
    lineas: LineaCarrito[];
    base: number;
    metodos: string[];
    credito: Credito;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Carrito', href: index() }],
    },
});

// --- Operaciones del carrito ---
// La cantidad se ajusta solo con los botones +/- (no es un campo editable por teclado), acotada
// entre 1 y el stock disponible. Si no cambia nada no se envia nada al server.
function setCantidad(linea: LineaCarrito, valor: number): void {
    const cantidad = Math.min(Math.max(valor, 1), linea.stock);
    if (cantidad === linea.cantidad) {
        return;
    }
    router.put(
        actualizar(linea.producto_id).url,
        { cantidad },
        { preserveScroll: true },
    );
}

function quitarLinea(linea: LineaCarrito): void {
    router.delete(quitar(linea.producto_id).url, { preserveScroll: true });
}

// --- Checkout ---
const form = useForm<{
    direccion_envio: string;
    tipo_pago: string;
    metodo: string;
    numero_cuotas: string;
}>({
    direccion_envio: '',
    tipo_pago: 'contado',
    metodo: 'efectivo',
    numero_cuotas: '2',
});

const esCredito = computed(() => form.tipo_pago === 'credito');

// Espejo de App\Support\PlanPago para el preview (el server recalcula al confirmar).
const cuotasMaximas = computed<number>(() => {
    if (props.base < props.credito.montoMinimo) {
        return 0;
    }
    const tramo = props.credito.tramosCuotas.find(
        (t) => t.hasta === null || props.base <= t.hasta,
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

const interesPct = computed<number>(() => {
    const n = Number(form.numero_cuotas) || 0;
    return esCredito.value && n >= 2 ? Math.round(interesDe(n) * 100) : 0;
});

const totalConInteres = computed<number>(() => {
    const n = Number(form.numero_cuotas) || 0;
    if (!esCredito.value || n < 2) {
        return props.base;
    }
    return Math.round(props.base * (1 + interesDe(n)) * 100) / 100;
});

// Errores de negocio del server que no son de un campo nombrado (ej. stock por línea).
const erroresGenerales = computed<string[]>(() =>
    Object.entries(form.errors)
        .filter(([clave]) => clave.startsWith('lineas'))
        .map(([, mensaje]) => mensaje as string),
);

function confirmarCompra(): void {
    form.post(checkout().url, { preserveScroll: true });
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Head title="Carrito" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Tu carrito</h1>
                <p class="text-sm text-muted-foreground">
                    Revisa los productos, ajusta cantidades y finaliza tu compra.
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link :href="inicio()">
                    <ArrowLeft class="h-4 w-4" />
                    Seguir comprando
                </Link>
            </Button>
        </header>

        <!-- Carrito vacío -->
        <div
            v-if="lineas.length === 0"
            class="flex flex-col items-center gap-4 rounded-xl border border-sidebar-border/70 p-10 text-center dark:border-sidebar-border"
        >
            <p class="text-muted-foreground">Tu carrito está vacío.</p>
            <Button as-child>
                <Link :href="inicio()">Ir a la tienda</Link>
            </Button>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-3">
            <!-- Líneas -->
            <div class="space-y-3 lg:col-span-2">
                <div
                    v-for="linea in lineas"
                    :key="linea.producto_id"
                    class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted"
                    >
                        <img
                            v-if="linea.foto_url"
                            :src="linea.foto_url"
                            :alt="linea.nombre"
                            class="h-full w-full object-cover"
                        />
                        <ImageOff v-else class="h-6 w-6 text-muted-foreground" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ linea.nombre }}</div>
                        <div class="text-sm text-muted-foreground">
                            <span
                                v-if="linea.promocion_nombre"
                                class="mr-1 line-through"
                            >
                                Bs {{ linea.precio.toFixed(2) }}
                            </span>
                            Bs {{ linea.precio_final.toFixed(2) }} c/u
                        </div>
                        <div
                            v-if="linea.promocion_nombre"
                            class="text-xs text-emerald-600 dark:text-emerald-400"
                        >
                            {{ linea.promocion_nombre }}
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <div
                            class="flex items-center gap-1 rounded-md border border-input p-0.5"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                :disabled="linea.cantidad <= 1"
                                aria-label="Quitar una unidad"
                                @click="setCantidad(linea, linea.cantidad - 1)"
                            >
                                <Minus class="h-4 w-4" />
                            </Button>
                            <span
                                class="min-w-8 text-center text-sm font-medium tabular-nums"
                            >
                                {{ linea.cantidad }}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                :disabled="linea.cantidad >= linea.stock"
                                aria-label="Agregar una unidad"
                                @click="setCantidad(linea, linea.cantidad + 1)"
                            >
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                        <div class="text-sm font-medium whitespace-nowrap">
                            Bs {{ linea.subtotal.toFixed(2) }}
                        </div>
                    </div>

                    <Button
                        variant="ghost"
                        size="icon"
                        @click="quitarLinea(linea)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Resumen + checkout -->
            <form
                class="h-fit space-y-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                @submit.prevent="confirmarCompra"
            >
                <h2 class="font-medium">Finalizar compra</h2>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">Monto base</span>
                    <span class="font-medium">Bs {{ base.toFixed(2) }}</span>
                </div>

                <div
                    v-if="erroresGenerales.length > 0"
                    class="rounded-md border border-destructive/50 bg-destructive/10 p-2 text-sm text-destructive"
                >
                    <p v-for="(msg, i) in erroresGenerales" :key="i">{{ msg }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="direccion_envio">Dirección de envío</Label>
                    <Input
                        id="direccion_envio"
                        v-model="form.direccion_envio"
                        required
                        placeholder="¿A dónde enviamos tu pedido?"
                    />
                    <InputError :message="form.errors.direccion_envio" />
                </div>

                <div class="grid gap-2">
                    <Label for="tipo_pago">Tipo de pago</Label>
                    <select
                        id="tipo_pago"
                        v-model="form.tipo_pago"
                        :class="selectClass"
                    >
                        <option value="contado">Contado (pagar ahora)</option>
                        <option value="credito">Crédito (en cuotas)</option>
                    </select>
                    <InputError :message="form.errors.tipo_pago" />
                    <p v-if="esCredito" class="text-sm text-muted-foreground">
                        Interés fijo según el número de cuotas: 2–3 cuotas 5%, 4–6
                        cuotas 10%, 7–9 cuotas 15%, 10–12 cuotas 20%.
                    </p>
                </div>

                <!-- Contado -->
                <div v-if="!esCredito" class="grid gap-2">
                    <Label for="metodo">Método de pago</Label>
                    <select id="metodo" v-model="form.metodo" :class="selectClass">
                        <option v-for="m in metodos" :key="m" :value="m">
                            {{ m === 'qr' ? 'QR' : m.charAt(0).toUpperCase() + m.slice(1) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.metodo" />
                    <p class="text-sm text-muted-foreground">
                        Total a pagar: <strong>Bs {{ base.toFixed(2) }}</strong>
                    </p>
                </div>

                <!-- Crédito -->
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
                        para pagar a crédito.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Total con {{ interesPct }}% de interés:
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

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="
                        form.processing ||
                        (esCredito && cuotasDisponibles.length === 0)
                    "
                >
                    Confirmar compra
                </Button>
            </form>
        </div>
    </div>
</template>
