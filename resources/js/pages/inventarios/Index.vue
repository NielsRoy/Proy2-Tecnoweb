<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/inventarios';

type MovimientoItem = {
    id: number;
    fecha: string | null;
    producto: string | null;
    tipo_movimiento: string;
    cantidad: number;
    motivo: string;
};

type Paginado = {
    data: MovimientoItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filtros = {
    producto_id: number | null;
    tipo_movimiento: string | null;
    motivo: string | null;
    desde: string | null;
    hasta: string | null;
};

const props = defineProps<{
    movimientos: Paginado;
    filtros: Filtros;
    productos: { id: number; nombre: string }[];
    puedeCrear: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inventarios', href: index() }],
    },
});

const filtros = reactive({
    producto_id:
        props.filtros.producto_id != null ? String(props.filtros.producto_id) : '',
    tipo_movimiento: props.filtros.tipo_movimiento ?? '',
    motivo: props.filtros.motivo ?? '',
    desde: props.filtros.desde ?? '',
    hasta: props.filtros.hasta ?? '',
});

function aplicar(): void {
    const query: Record<string, string> = {};
    Object.entries(filtros).forEach(([clave, valor]) => {
        if (valor !== '' && valor != null) {
            query[clave] = String(valor);
        }
    });
    router.get(index().url, query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function limpiar(): void {
    filtros.producto_id = '';
    filtros.tipo_movimiento = '';
    filtros.motivo = '';
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
</script>

<template>
    <Head title="Inventarios" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Inventarios</h1>
                <p class="text-sm text-muted-foreground">
                    Libro de movimientos de stock. Los ingresos y salidas de compras
                    y ventas se registran solos; aquí registras ajustes manuales.
                    Para corregir un ajuste, registra otro en sentido contrario.
                </p>
            </div>
            <Button v-if="puedeCrear" as-child>
                <Link :href="create()">Nuevo ajuste</Link>
            </Button>
        </header>

        <!-- Filtros -->
        <div
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-2 lg:grid-cols-5 dark:border-sidebar-border"
        >
            <div class="grid gap-1.5">
                <Label for="f-producto">Producto</Label>
                <select
                    id="f-producto"
                    v-model="filtros.producto_id"
                    :class="selectClass"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="p in productos"
                        :key="p.id"
                        :value="String(p.id)"
                    >
                        {{ p.nombre }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-tipo">Tipo</Label>
                <select
                    id="f-tipo"
                    v-model="filtros.tipo_movimiento"
                    :class="selectClass"
                >
                    <option value="">Todos</option>
                    <option value="ingreso">Ingreso</option>
                    <option value="salida">Salida</option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-motivo">Motivo</Label>
                <select id="f-motivo" v-model="filtros.motivo" :class="selectClass">
                    <option value="">Todos</option>
                    <option value="compra">Compra</option>
                    <option value="venta">Venta</option>
                    <option value="ajuste">Ajuste</option>
                    <option value="correccion">Corrección</option>
                    <option value="inicial">Inicial</option>
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
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
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
                        <th class="p-3 text-left font-medium">Fecha</th>
                        <th class="p-3 text-left font-medium">Producto</th>
                        <th class="p-3 text-left font-medium">Tipo</th>
                        <th class="p-3 text-right font-medium">Cantidad</th>
                        <th class="p-3 text-left font-medium">Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="m in movimientos.data"
                        :key="m.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ m.fecha }}
                        </td>
                        <td class="p-3 font-medium">{{ m.producto ?? '—' }}</td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    m.tipo_movimiento === 'ingreso'
                                        ? 'default'
                                        : 'destructive'
                                "
                            >
                                {{
                                    m.tipo_movimiento === 'ingreso'
                                        ? 'Ingreso'
                                        : 'Salida'
                                }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">{{ m.cantidad }}</td>
                        <td class="p-3 text-muted-foreground capitalize">
                            {{ m.motivo }}
                        </td>
                    </tr>
                    <tr v-if="movimientos.data.length === 0">
                        <td
                            colspan="5"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay movimientos que coincidan con los filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div
            v-if="movimientos.total > 0"
            class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        >
            <span>
                Mostrando {{ movimientos.from }}–{{ movimientos.to }} de
                {{ movimientos.total }}
            </span>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!movimientos.prev_page_url"
                    @click="irA(movimientos.prev_page_url)"
                >
                    Anterior
                </Button>
                <span>
                    Página {{ movimientos.current_page }} de
                    {{ movimientos.last_page }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!movimientos.next_page_url"
                    @click="irA(movimientos.next_page_url)"
                >
                    Siguiente
                </Button>
            </div>
        </div>
    </div>
</template>
