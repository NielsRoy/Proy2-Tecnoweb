<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
import { index, pagar } from '@/routes/mis-pagos';

type CuotaPlan = {
    id: number;
    venta_id: number;
    numero_cuota: number;
    total_cuotas: number | null;
    monto: string;
    fecha_vencimiento: string | null;
    es_proxima: boolean;
};

type CuotaHistorial = {
    venta_id: number;
    numero_cuota: number;
    monto: string;
    fecha_pago: string | null;
    metodo: string | null;
};

const props = defineProps<{
    plan: CuotaPlan[];
    historial: CuotaHistorial[];
    filtros: { desde: string | null; hasta: string | null };
    metodos: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mis pagos', href: index() }],
    },
});

const filtros = reactive({
    desde: props.filtros.desde ?? '',
    hasta: props.filtros.hasta ?? '',
});

function aplicar(): void {
    const query: Record<string, string> = {};
    if (filtros.desde) query.desde = filtros.desde;
    if (filtros.hasta) query.hasta = filtros.hasta;
    router.get(index().url, query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function limpiar(): void {
    filtros.desde = '';
    filtros.hasta = '';
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

// Pago de una cuota (solo la próxima).
const cuotaAPagar = ref<CuotaPlan | null>(null);
const metodo = ref(props.metodos[0] ?? 'efectivo');
const pagando = ref(false);

function abrirPago(c: CuotaPlan): void {
    cuotaAPagar.value = c;
    metodo.value = props.metodos[0] ?? 'efectivo';
}

function confirmarPago(): void {
    if (!cuotaAPagar.value) {
        return;
    }
    router.put(
        pagar(cuotaAPagar.value.id).url,
        { metodo: metodo.value },
        {
            preserveScroll: true,
            onStart: () => (pagando.value = true),
            onFinish: () => {
                pagando.value = false;
                cuotaAPagar.value = null;
            },
        },
    );
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Head title="Mis pagos" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Mis pagos</h1>
            <p class="text-sm text-muted-foreground">
                Tu plan de pagos a crédito e historial. Solo puedes pagar la próxima
                cuota de cada compra; el monto es fijo.
            </p>
        </header>

        <!-- Plan de pagos activo -->
        <section class="space-y-2">
            <h2 class="text-sm font-medium">Plan de pagos activo</h2>
            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr
                            class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <th class="p-3 text-left font-medium">Compra</th>
                            <th class="p-3 text-left font-medium">Cuota</th>
                            <th class="p-3 text-right font-medium">Monto</th>
                            <th class="p-3 text-left font-medium">Vence</th>
                            <th class="p-3 text-left font-medium">Estado</th>
                            <th class="p-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in plan"
                            :key="c.id"
                            class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                        >
                            <td class="p-3 text-muted-foreground">
                                #{{ c.venta_id }}
                            </td>
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
                                <Badge
                                    :variant="c.es_proxima ? 'default' : 'secondary'"
                                >
                                    {{ c.es_proxima ? 'Próxima' : 'En espera' }}
                                </Badge>
                            </td>
                            <td class="p-3 text-right">
                                <Button
                                    v-if="c.es_proxima"
                                    size="sm"
                                    @click="abrirPago(c)"
                                >
                                    Pagar
                                </Button>
                                <span v-else class="text-xs text-muted-foreground">
                                    —
                                </span>
                            </td>
                        </tr>
                        <tr v-if="plan.length === 0">
                            <td
                                colspan="6"
                                class="p-6 text-center text-muted-foreground"
                            >
                                No tienes cuotas pendientes.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Historial de pagos -->
        <section class="space-y-2">
            <h2 class="text-sm font-medium">Historial de pagos</h2>

            <!-- Filtros por fecha (solo para el historial, sobre la fecha de pago). -->
            <div
                class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-3 dark:border-sidebar-border"
            >
                <div class="grid gap-1.5">
                    <Label for="f-desde">Desde</Label>
                    <Input id="f-desde" type="date" v-model="filtros.desde" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="f-hasta">Hasta</Label>
                    <Input id="f-hasta" type="date" v-model="filtros.hasta" />
                </div>
                <div class="flex items-end gap-2">
                    <Button @click="aplicar">Filtrar</Button>
                    <Button variant="outline" @click="limpiar">Limpiar</Button>
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
                            <th class="p-3 text-left font-medium">Fecha de pago</th>
                            <th class="p-3 text-left font-medium">Compra</th>
                            <th class="p-3 text-left font-medium">Cuota</th>
                            <th class="p-3 text-right font-medium">Monto</th>
                            <th class="p-3 text-left font-medium">Método</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(h, i) in historial"
                            :key="i"
                            class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                        >
                            <td class="p-3 whitespace-nowrap text-muted-foreground">
                                {{ h.fecha_pago }}
                            </td>
                            <td class="p-3 text-muted-foreground">
                                #{{ h.venta_id }}
                            </td>
                            <td class="p-3">{{ h.numero_cuota }}</td>
                            <td class="p-3 text-right whitespace-nowrap">
                                Bs {{ h.monto }}
                            </td>
                            <td class="p-3 capitalize">{{ h.metodo ?? '—' }}</td>
                        </tr>
                        <tr v-if="historial.length === 0">
                            <td
                                colspan="5"
                                class="p-6 text-center text-muted-foreground"
                            >
                                Aún no has realizado pagos.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <Dialog
        :open="cuotaAPagar !== null"
        @update:open="(v) => !v && (cuotaAPagar = null)"
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Pagar cuota</DialogTitle>
                <DialogDescription>
                    Cuota {{ cuotaAPagar?.numero_cuota }} de la compra #{{
                        cuotaAPagar?.venta_id
                    }}
                    · <strong>Bs {{ cuotaAPagar?.monto }}</strong>. El monto es fijo
                    (sin pago parcial).
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-2 py-2">
                <Label for="metodo">Método de pago</Label>
                <select id="metodo" v-model="metodo" :class="selectClass">
                    <option v-for="m in metodos" :key="m" :value="m">
                        {{ m.charAt(0).toUpperCase() + m.slice(1) }}
                    </option>
                </select>
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button :disabled="pagando" @click="confirmarPago">
                    Confirmar pago
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
