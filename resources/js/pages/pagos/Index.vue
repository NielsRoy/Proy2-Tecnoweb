<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
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
import { Label } from '@/components/ui/label';
import { index, pagar } from '@/routes/pagos';

type CuotaItem = {
    id: number;
    venta_id: number;
    cliente: string | null;
    numero_cuota: number;
    total_cuotas: number | null;
    monto: string;
    fecha_vencimiento: string | null;
    es_proxima: boolean;
    qr_url: string;
};

const props = defineProps<{
    cuotas: CuotaItem[];
    clientes: { id: number; name: string }[];
    filtros: { cliente_id: number | null };
    metodos: string[];
    puedeRegistrar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pagos', href: index() }],
    },
});

const filtros = reactive({
    cliente_id:
        props.filtros.cliente_id != null ? String(props.filtros.cliente_id) : '',
});

function aplicar(): void {
    const query: Record<string, string> = {};
    if (filtros.cliente_id) {
        query.cliente_id = filtros.cliente_id;
    }
    router.get(index().url, query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function limpiar(): void {
    filtros.cliente_id = '';
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

// Cobro de una cuota. El QR es una opción más del select (no un botón aparte): si se elige, se navega
// a la página del QR; con los demás métodos se cobra al instante y se muestra el comprobante.
const metodosConQr = computed(() => [...props.metodos, 'qr']);
const cuotaACobrar = ref<CuotaItem | null>(null);
const metodo = ref(props.metodos[0] ?? 'efectivo');
const cobrando = ref(false);

function etiquetaMetodo(m: string): string {
    return m === 'qr' ? 'QR (PagoFacil)' : m.charAt(0).toUpperCase() + m.slice(1);
}

function abrirCobro(c: CuotaItem): void {
    cuotaACobrar.value = c;
    metodo.value = props.metodos[0] ?? 'efectivo';
}

function confirmarCobro(): void {
    const c = cuotaACobrar.value;
    if (!c) {
        return;
    }
    if (metodo.value === 'qr') {
        router.visit(c.qr_url);
        return;
    }
    router.put(
        pagar(c.id).url,
        { metodo: metodo.value },
        {
            preserveScroll: true,
            onStart: () => (cobrando.value = true),
            onFinish: () => {
                cobrando.value = false;
                cuotaACobrar.value = null;
            },
        },
    );
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Head title="Pagos" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Pagos</h1>
            <p class="text-sm text-muted-foreground">
                Cuotas pendientes de las ventas a crédito. Solo se puede cobrar la
                próxima cuota de cada venta.
            </p>
        </header>

        <!-- Filtro -->
        <div
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-3 dark:border-sidebar-border"
        >
            <div class="grid gap-1.5">
                <Label for="f-cliente">Cliente</Label>
                <select id="f-cliente" v-model="filtros.cliente_id" :class="selectClass">
                    <option value="">Todos</option>
                    <option v-for="c in clientes" :key="c.id" :value="String(c.id)">
                        {{ c.name }}
                    </option>
                </select>
            </div>
            <div class="flex items-end gap-2 sm:col-span-2">
                <Button @click="aplicar">Filtrar</Button>
                <Button variant="outline" @click="limpiar">Limpiar</Button>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr
                        class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <th class="p-3 text-left font-medium">Cliente</th>
                        <th class="p-3 text-left font-medium">Venta</th>
                        <th class="p-3 text-left font-medium">Cuota</th>
                        <th class="p-3 text-right font-medium">Monto</th>
                        <th class="p-3 text-left font-medium">Vence</th>
                        <th class="p-3 text-left font-medium">Estado</th>
                        <th
                            v-if="puedeRegistrar"
                            class="p-3 text-right font-medium"
                        >
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in cuotas"
                        :key="c.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 font-medium">{{ c.cliente ?? '—' }}</td>
                        <td class="p-3 text-muted-foreground">#{{ c.venta_id }}</td>
                        <td class="p-3">
                            {{ c.numero_cuota }}/{{ c.total_cuotas ?? '—' }}
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ c.monto }}
                        </td>
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ c.fecha_vencimiento }}
                        </td>
                        <td class="p-3">
                            <Badge :variant="c.es_proxima ? 'default' : 'secondary'">
                                {{ c.es_proxima ? 'Próxima' : 'En espera' }}
                            </Badge>
                        </td>
                        <td v-if="puedeRegistrar" class="p-3 text-right">
                            <Button
                                v-if="c.es_proxima"
                                size="sm"
                                @click="abrirCobro(c)"
                            >
                                Registrar pago
                            </Button>
                            <span v-else class="text-xs text-muted-foreground">—</span>
                        </td>
                    </tr>
                    <tr v-if="cuotas.length === 0">
                        <td
                            colspan="7"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay cuotas pendientes.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog
        :open="cuotaACobrar !== null"
        @update:open="(v) => !v && (cuotaACobrar = null)"
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Registrar pago de cuota</DialogTitle>
                <DialogDescription>
                    Cuota {{ cuotaACobrar?.numero_cuota }} de la venta #{{
                        cuotaACobrar?.venta_id
                    }}
                    · <strong>Bs {{ cuotaACobrar?.monto }}</strong>. El monto es fijo
                    (sin pago parcial).
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-2 py-2">
                <Label for="metodo">Método de pago</Label>
                <select id="metodo" v-model="metodo" :class="selectClass">
                    <option v-for="m in metodosConQr" :key="m" :value="m">
                        {{ etiquetaMetodo(m) }}
                    </option>
                </select>
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button :disabled="cobrando" @click="confirmarCobro">
                    {{ metodo === 'qr' ? 'Continuar al QR' : 'Confirmar pago' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
