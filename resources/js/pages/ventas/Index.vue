<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
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
import BotonesReporte from '@/components/BotonesReporte.vue';
import { create, destroy, index, reporte, show } from '@/routes/ventas';

type VentaItem = {
    id: number;
    fecha: string | null;
    cliente: string | null;
    items: number;
    monto_total: string;
    tipo_pago: string;
    estado_pago: string;
    estado: string;
};

type Paginado = {
    data: VentaItem[];
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
    tipo_pago: string | null;
    estado_pago: string | null;
    estado: string | null;
    desde: string | null;
    hasta: string | null;
};

const props = defineProps<{
    ventas: Paginado;
    filtros: Filtros;
    clientes: { id: number; name: string }[];
    puedeCrear: boolean;
    puedeEliminar: boolean;
    puedeReportar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ventas', href: index() }],
    },
});

const filtros = reactive({
    cliente_id:
        props.filtros.cliente_id != null ? String(props.filtros.cliente_id) : '',
    tipo_pago: props.filtros.tipo_pago ?? '',
    estado_pago: props.filtros.estado_pago ?? '',
    estado: props.filtros.estado ?? '',
    desde: props.filtros.desde ?? '',
    hasta: props.filtros.hasta ?? '',
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
    filtros.tipo_pago = '';
    filtros.estado_pago = '';
    filtros.estado = '';
    filtros.desde = '';
    filtros.hasta = '';
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

function irA(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    }
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';

const ventaAAnular = ref<VentaItem | null>(null);
const anulando = ref(false);

function confirmarAnular(): void {
    if (!ventaAAnular.value) {
        return;
    }
    router.delete(destroy(ventaAAnular.value.id).url, {
        preserveScroll: true,
        onStart: () => (anulando.value = true),
        onFinish: () => {
            anulando.value = false;
            ventaAAnular.value = null;
        },
    });
}
</script>

<template>
    <Head title="Ventas" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Ventas</h1>
                <p class="text-sm text-muted-foreground">
                    Registro de ventas a clientes. Al contado se paga al registrar;
                    a crédito se generan las cuotas que se cobran en Pagos.
                </p>
            </div>
            <Button v-if="puedeCrear" as-child>
                <Link :href="create()">Nueva venta</Link>
            </Button>
        </header>

        <!-- Filtros -->
        <div
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-2 lg:grid-cols-6 dark:border-sidebar-border"
        >
            <div class="grid gap-1.5">
                <Label for="f-cliente">Cliente</Label>
                <select
                    id="f-cliente"
                    v-model="filtros.cliente_id"
                    :class="selectClass"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="c in clientes"
                        :key="c.id"
                        :value="String(c.id)"
                    >
                        {{ c.name }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-tipo">Tipo de pago</Label>
                <select id="f-tipo" v-model="filtros.tipo_pago" :class="selectClass">
                    <option value="">Todos</option>
                    <option value="contado">Contado</option>
                    <option value="credito">Crédito</option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-epago">Estado de pago</Label>
                <select
                    id="f-epago"
                    v-model="filtros.estado_pago"
                    :class="selectClass"
                >
                    <option value="">Todos</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="pagada">Pagada</option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-estado">Estado</Label>
                <select id="f-estado" v-model="filtros.estado" :class="selectClass">
                    <option value="">Todos</option>
                    <option value="registrada">Registrada</option>
                    <option value="anulada">Anulada</option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-desde">Desde</Label>
                <Input id="f-desde" type="date" v-model="filtros.desde" />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-hasta">Hasta</Label>
                <Input id="f-hasta" type="date" v-model="filtros.hasta" />
            </div>
            <div
                class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-6"
            >
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

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr
                        class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <th class="p-3 text-left font-medium">Fecha</th>
                        <th class="p-3 text-left font-medium">Cliente</th>
                        <th class="p-3 text-right font-medium">Ítems</th>
                        <th class="p-3 text-right font-medium">Total</th>
                        <th class="p-3 text-left font-medium">Pago</th>
                        <th class="p-3 text-left font-medium">Estado</th>
                        <th class="p-3 text-right font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="v in ventas.data"
                        :key="v.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ v.fecha }}
                        </td>
                        <td class="p-3 font-medium">{{ v.cliente ?? '—' }}</td>
                        <td class="p-3 text-right">{{ v.items }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            Bs {{ v.monto_total }}
                        </td>
                        <td class="p-3">
                            <div class="flex flex-col gap-1">
                                <span class="capitalize">{{ v.tipo_pago }}</span>
                                <Badge
                                    :variant="
                                        v.estado_pago === 'pagada'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    class="w-fit"
                                >
                                    {{
                                        v.estado_pago === 'pagada'
                                            ? 'Pagada'
                                            : 'Pendiente'
                                    }}
                                </Badge>
                            </div>
                        </td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    v.estado === 'anulada'
                                        ? 'destructive'
                                        : 'outline'
                                "
                            >
                                {{ v.estado === 'anulada' ? 'Anulada' : 'Registrada' }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="show(v.id)">Ver</Link>
                                </Button>
                                <Button
                                    v-if="
                                        puedeEliminar &&
                                        v.estado === 'registrada' &&
                                        v.estado_pago === 'pendiente'
                                    "
                                    variant="destructive"
                                    size="sm"
                                    @click="ventaAAnular = v"
                                >
                                    Anular
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="ventas.data.length === 0">
                        <td
                            colspan="7"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay ventas que coincidan con los filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="ventas.total > 0"
            class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        >
            <span>
                Mostrando {{ ventas.from }}–{{ ventas.to }} de {{ ventas.total }}
            </span>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!ventas.prev_page_url"
                    @click="irA(ventas.prev_page_url)"
                >
                    Anterior
                </Button>
                <span>Página {{ ventas.current_page }} de {{ ventas.last_page }}</span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!ventas.next_page_url"
                    @click="irA(ventas.next_page_url)"
                >
                    Siguiente
                </Button>
            </div>
        </div>
    </div>

    <Dialog
        :open="ventaAAnular !== null"
        @update:open="(v) => !v && (ventaAAnular = null)"
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>¿Anular venta?</DialogTitle>
                <DialogDescription>
                    Se devolverá el stock de la venta
                    <strong>#{{ ventaAAnular?.id }}</strong> y se borrarán sus cuotas.
                    Solo es posible si ninguna cuota fue pagada.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="anulando"
                    @click="confirmarAnular"
                >
                    Anular
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
