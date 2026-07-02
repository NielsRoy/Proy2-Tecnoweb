<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import BotonesReporte from '@/components/BotonesReporte.vue';
import { index, reporte } from '@/routes/bitacora';

type Registro = {
    id: number;
    fecha: string | null;
    usuario: string | null;
    accion: string;
    modulo: string | null;
    descripcion: string;
    ip: string | null;
};

type Paginado = {
    data: Registro[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filtros = {
    q: string | null;
    accion: string | null;
    desde: string | null;
    hasta: string | null;
};

const props = defineProps<{
    registros: Paginado;
    filtros: Filtros;
    acciones: string[];
    puedeReportar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Bitácora', href: index() }],
    },
});

// Estado local de los filtros (vacío = sin filtrar). Se inicializa con lo que vino del server.
// La búsqueda por nombre de usuario la provee el buscador global (filtro `q`), no un input de esta vista.
const filtros = reactive({
    accion: props.filtros.accion ?? '',
    desde: props.filtros.desde ?? '',
    hasta: props.filtros.hasta ?? '',
});

function queryFiltros(): Record<string, string> {
    // Solo enviamos los filtros con valor, para que la URL quede limpia.
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
    filtros.accion = '';
    filtros.desde = '';
    filtros.hasta = '';
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

function irA(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    }
}

// Color del badge según el tipo de acción.
function variante(
    accion: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (['login_fallido', 'login_bloqueado', 'eliminar'].includes(accion)) {
        return 'destructive';
    }
    if (accion === 'crear') {
        return 'default';
    }
    return 'secondary';
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Head title="Bitácora" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Bitácora</h1>
            <p class="text-sm text-muted-foreground">
                Registro de operaciones del sistema: inicios y cierres de sesión,
                intentos fallidos y operaciones de negocio.
            </p>
        </header>

        <!-- Filtros -->
        <div
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border"
        >
            <div class="grid gap-1.5">
                <Label for="f-accion">Acción</Label>
                <select id="f-accion" v-model="filtros.accion" :class="selectClass">
                    <option value="">Todas</option>
                    <option v-for="a in acciones" :key="a" :value="a">
                        {{ a }}
                    </option>
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
                class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4"
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
                        <th class="p-3 text-left font-medium">Usuario</th>
                        <th class="p-3 text-left font-medium">Acción</th>
                        <th class="p-3 text-left font-medium">Módulo</th>
                        <th class="p-3 text-left font-medium">Descripción</th>
                        <th class="p-3 text-left font-medium">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in registros.data"
                        :key="r.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ r.fecha }}
                        </td>
                        <td class="p-3">{{ r.usuario ?? '—' }}</td>
                        <td class="p-3">
                            <Badge :variant="variante(r.accion)">{{
                                r.accion
                            }}</Badge>
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ r.modulo ?? '—' }}
                        </td>
                        <td class="p-3">{{ r.descripcion }}</td>
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ r.ip ?? '—' }}
                        </td>
                    </tr>
                    <tr v-if="registros.data.length === 0">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay registros que coincidan con los filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div
            v-if="registros.total > 0"
            class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        >
            <span>
                Mostrando {{ registros.from }}–{{ registros.to }} de
                {{ registros.total }}
            </span>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!registros.prev_page_url"
                    @click="irA(registros.prev_page_url)"
                >
                    Anterior
                </Button>
                <span>Página {{ registros.current_page }} de {{ registros.last_page }}</span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!registros.next_page_url"
                    @click="irA(registros.next_page_url)"
                >
                    Siguiente
                </Button>
            </div>
        </div>
    </div>
</template>
