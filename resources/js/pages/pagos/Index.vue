<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import BotonesReporte from '@/components/BotonesReporte.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, pagar, reporte } from '@/routes/pagos';

type PagoItem = {
    id: number;
    venta_id: number;
    cliente: string | null;
    numero_cuota: number;
    total_cuotas: number | null;
    monto: string;
    metodo: string | null;
    fecha_vencimiento: string | null;
    fecha_pago: string | null;
    estado: string;
    es_proxima: boolean;
    qr_url: string;
};

type Paginado = {
    data: PagoItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filtros = {
    cliente_id: number | null;
    metodo: string | null;
    estado: string | null;
    venc_desde: string | null;
    venc_hasta: string | null;
    pago_desde: string | null;
    pago_hasta: string | null;
};

const props = defineProps<{
    pagos: Paginado;
    clientes: { id: number; name: string }[];
    filtros: Filtros;
    metodos: string[];
    puedeRegistrar: boolean;
    puedeReportar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pagos', href: index() }],
    },
});

const filtros = reactive({
    cliente_id:
        props.filtros.cliente_id != null ? String(props.filtros.cliente_id) : '',
    metodo: props.filtros.metodo ?? '',
    estado: props.filtros.estado ?? '',
    venc_desde: props.filtros.venc_desde ?? '',
    venc_hasta: props.filtros.venc_hasta ?? '',
    pago_desde: props.filtros.pago_desde ?? '',
    pago_hasta: props.filtros.pago_hasta ?? '',
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
    filtros.cliente_id = '';
    filtros.metodo = '';
    filtros.estado = '';
    filtros.venc_desde = '';
    filtros.venc_hasta = '';
    filtros.pago_desde = '';
    filtros.pago_hasta = '';
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

function irA(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    }
}

// Cobro de una cuota. El QR es una opción más del select (no un botón aparte): si se elige, se navega
// a la página del QR; con los demás métodos se cobra al instante y se muestra el comprobante.
const metodosConQr = computed(() => [...props.metodos, 'qr']);
const cuotaACobrar = ref<PagoItem | null>(null);
const metodo = ref(props.metodos[0] ?? 'efectivo');
const cobrando = ref(false);

function etiquetaMetodo(m: string): string {
    return m === 'qr' ? 'QR (PagoFacil)' : m.charAt(0).toUpperCase() + m.slice(1);
}

function abrirCobro(c: PagoItem): void {
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
                Todos los pagos del sistema. Puedes cobrar la próxima cuota pendiente
                de cada venta a crédito.
            </p>
        </header>

        <!-- Filtros -->
        <div
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border"
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
            <div class="grid gap-1.5">
                <Label for="f-metodo">Método</Label>
                <select id="f-metodo" v-model="filtros.metodo" :class="selectClass">
                    <option value="">Todos</option>
                    <option v-for="m in metodosConQr" :key="m" :value="m">
                        {{ etiquetaMetodo(m) }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-estado">Estado</Label>
                <select id="f-estado" v-model="filtros.estado" :class="selectClass">
                    <option value="">Todos</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="pagado">Pagado</option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-venc-desde">Vence desde</Label>
                <Input id="f-venc-desde" type="date" v-model="filtros.venc_desde" />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-venc-hasta">Vence hasta</Label>
                <Input id="f-venc-hasta" type="date" v-model="filtros.venc_hasta" />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-pago-desde">Pagado desde</Label>
                <Input id="f-pago-desde" type="date" v-model="filtros.pago_desde" />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-pago-hasta">Pagado hasta</Label>
                <Input id="f-pago-hasta" type="date" v-model="filtros.pago_hasta" />
            </div>
            <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
                <Button @click="aplicar">Filtrar</Button>
                <Button variant="outline" @click="limpiar">Limpiar</Button>
                <BotonesReporte
                    v-if="puedeReportar"
                    class="ml-auto"
                    :url="reporte().url"
                    :query="queryFiltros()"
                />
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
                        <th class="p-3 text-left font-medium">Método</th>
                        <th class="p-3 text-left font-medium">Vence</th>
                        <th class="p-3 text-left font-medium">Fecha de pago</th>
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
                        v-for="p in pagos.data"
                        :key="p.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 font-medium">{{ p.cliente ?? '—' }}</td>
                        <td class="p-3 text-muted-foreground">#{{ p.venta_id }}</td>
                        <td class="p-3">
                            {{ p.numero_cuota }}/{{ p.total_cuotas ?? '—' }}
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ p.monto }}
                        </td>
                        <td class="p-3 capitalize">{{ p.metodo ?? '—' }}</td>
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ p.fecha_vencimiento }}
                        </td>
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ p.fecha_pago ?? '—' }}
                        </td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    p.estado === 'pagado' ? 'default' : 'secondary'
                                "
                            >
                                {{ p.estado === 'pagado' ? 'Pagado' : 'Pendiente' }}
                            </Badge>
                        </td>
                        <td v-if="puedeRegistrar" class="p-3 text-right">
                            <Button
                                v-if="p.es_proxima"
                                size="sm"
                                @click="abrirCobro(p)"
                            >
                                Registrar pago
                            </Button>
                            <span v-else class="text-xs text-muted-foreground">—</span>
                        </td>
                    </tr>
                    <tr v-if="pagos.data.length === 0">
                        <td
                            colspan="9"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay pagos que coincidan con los filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div
            v-if="pagos.total > 0"
            class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        >
            <span>Mostrando {{ pagos.from }}–{{ pagos.to }} de {{ pagos.total }}</span>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!pagos.prev_page_url"
                    @click="irA(pagos.prev_page_url)"
                >
                    Anterior
                </Button>
                <span>Página {{ pagos.current_page }} de {{ pagos.last_page }}</span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!pagos.next_page_url"
                    @click="irA(pagos.next_page_url)"
                >
                    Siguiente
                </Button>
            </div>
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
