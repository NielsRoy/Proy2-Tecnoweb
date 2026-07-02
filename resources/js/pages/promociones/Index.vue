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
import { create, destroy, edit, index, reporte } from '@/routes/promociones';

type PromocionItem = {
    id: number;
    producto: string | null;
    nombre: string;
    tipo_descuento: string;
    valor: string;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    vigente: boolean;
};

type Filtros = {
    q: string | null;
    desde: string | null;
    hasta: string | null;
};

const props = defineProps<{
    promociones: PromocionItem[];
    filtros: Filtros;
    puedeCrear: boolean;
    puedeEditar: boolean;
    puedeEliminar: boolean;
    puedeReportar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Promociones', href: index() }],
    },
});

const filtros = reactive({
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
    // Preserva el término del buscador global (filtro `q`) al filtrar o generar reportes.
    if (props.filtros.q) {
        query.q = props.filtros.q;
    }
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
    filtros.desde = '';
    filtros.hasta = '';
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';

const promocionAEliminar = ref<PromocionItem | null>(null);
const eliminando = ref(false);

function confirmarEliminar(): void {
    if (!promocionAEliminar.value) {
        return;
    }
    router.delete(destroy(promocionAEliminar.value.id).url, {
        preserveScroll: true,
        onStart: () => (eliminando.value = true),
        onFinish: () => {
            eliminando.value = false;
            promocionAEliminar.value = null;
        },
    });
}

function descuento(p: PromocionItem): string {
    return p.tipo_descuento === 'porcentaje' ? `${p.valor}%` : `Bs ${p.valor}`;
}
</script>

<template>
    <Head title="Promociones" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Promociones</h1>
                <p class="text-sm text-muted-foreground">
                    Descuentos por producto. No pueden solaparse dos promociones
                    activas del mismo producto.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <BotonesReporte
                    v-if="puedeReportar"
                    :url="reporte().url"
                    :query="queryFiltros()"
                />
                <Button v-if="puedeCrear" as-child>
                    <Link :href="create()">Nueva promoción</Link>
                </Button>
            </div>
        </header>

        <!-- Filtros -->
        <div
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border"
        >
            <div class="grid gap-1.5">
                <Label for="f-desde">Activas desde</Label>
                <Input id="f-desde" type="date" v-model="filtros.desde" />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-hasta">Activas hasta</Label>
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
                        <th class="p-3 text-left font-medium">Producto</th>
                        <th class="p-3 text-left font-medium">Promoción</th>
                        <th class="p-3 text-right font-medium">Descuento</th>
                        <th class="p-3 text-left font-medium">Vigencia</th>
                        <th class="p-3 text-left font-medium">Estado</th>
                        <th
                            v-if="puedeEditar || puedeEliminar"
                            class="p-3 text-right font-medium"
                        >
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in promociones"
                        :key="p.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 font-medium">{{ p.producto ?? '—' }}</td>
                        <td class="p-3">{{ p.nombre }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            {{ descuento(p) }}
                        </td>
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ p.fecha_inicio }} → {{ p.fecha_fin }}
                        </td>
                        <td class="p-3">
                            <Badge :variant="p.vigente ? 'default' : 'secondary'">
                                {{ p.vigente ? 'Vigente' : 'Fuera de fecha' }}
                            </Badge>
                        </td>
                        <td
                            v-if="puedeEditar || puedeEliminar"
                            class="p-3 text-right"
                        >
                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="puedeEditar"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(p.id)">Editar</Link>
                                </Button>
                                <Button
                                    v-if="puedeEliminar"
                                    variant="destructive"
                                    size="sm"
                                    @click="promocionAEliminar = p"
                                >
                                    Eliminar
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="promociones.length === 0">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay promociones que coincidan con los filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog
        :open="promocionAEliminar !== null"
        @update:open="(v) => !v && (promocionAEliminar = null)"
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>¿Eliminar promoción?</DialogTitle>
                <DialogDescription>
                    Se dará de baja
                    <strong>{{ promocionAEliminar?.nombre }}</strong>. Las ventas que
                    la usaron conservan su historial.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="eliminando"
                    @click="confirmarEliminar"
                >
                    Eliminar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
