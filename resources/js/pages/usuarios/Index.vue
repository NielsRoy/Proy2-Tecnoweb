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
import { create, destroy, edit, index, reporte } from '@/routes/usuarios';

type UsuarioItem = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    creado: string | null;
};

type Filtros = {
    rol_id: number | null;
    desde: string | null;
    hasta: string | null;
};

const props = defineProps<{
    usuarios: UsuarioItem[];
    filtros: Filtros;
    roles: { id: number; nombre: string }[];
    puedeCrear: boolean;
    puedeEditar: boolean;
    puedeEliminar: boolean;
    puedeReportar: boolean;
    usuarioActualId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: index() }],
    },
});

const filtros = reactive({
    rol_id: props.filtros.rol_id != null ? String(props.filtros.rol_id) : '',
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
    filtros.rol_id = '';
    filtros.desde = '';
    filtros.hasta = '';
    router.get(index().url, {}, { preserveScroll: true, replace: true });
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';

// Dialog de confirmacion de borrado: guarda el usuario a eliminar (o null si cerrado).
const usuarioAEliminar = ref<UsuarioItem | null>(null);
const eliminando = ref(false);

function confirmarEliminar(): void {
    if (!usuarioAEliminar.value) {
        return;
    }
    router.delete(destroy(usuarioAEliminar.value.id).url, {
        preserveScroll: true,
        onStart: () => (eliminando.value = true),
        onFinish: () => {
            eliminando.value = false;
            usuarioAEliminar.value = null;
        },
    });
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Usuarios</h1>
                <p class="text-sm text-muted-foreground">
                    Gestiona las cuentas del sistema y el rol asignado a cada
                    una.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <BotonesReporte
                    v-if="puedeReportar"
                    :url="reporte().url"
                    :query="queryFiltros()"
                />
                <Button v-if="puedeCrear" as-child>
                    <Link :href="create()">Nuevo usuario</Link>
                </Button>
            </div>
        </header>

        <!-- Filtros -->
        <div
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-3 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border"
        >
            <div class="grid gap-1.5">
                <Label for="f-rol">Rol</Label>
                <select id="f-rol" v-model="filtros.rol_id" :class="selectClass">
                    <option value="">Todos</option>
                    <option v-for="r in roles" :key="r.id" :value="String(r.id)">
                        {{ r.nombre }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="f-desde">Creados desde</Label>
                <Input id="f-desde" type="date" v-model="filtros.desde" />
            </div>
            <div class="grid gap-1.5">
                <Label for="f-hasta">Creados hasta</Label>
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
                        <th class="p-3 text-left font-medium">Nombre</th>
                        <th class="p-3 text-left font-medium">
                            Correo electrónico
                        </th>
                        <th class="p-3 text-left font-medium">Rol</th>
                        <th class="p-3 text-left font-medium">Creado</th>
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
                        v-for="usuario in usuarios"
                        :key="usuario.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="p-3 font-medium">{{ usuario.name }}</td>
                        <td class="p-3 text-muted-foreground">
                            {{ usuario.email }}
                        </td>
                        <td class="p-3">
                            <span
                                v-if="usuario.roles.length === 0"
                                class="text-muted-foreground"
                            >
                                —
                            </span>
                            <span v-else class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="rol in usuario.roles"
                                    :key="rol"
                                    variant="secondary"
                                >
                                    {{ rol }}
                                </Badge>
                            </span>
                        </td>
                        <td class="p-3 whitespace-nowrap text-muted-foreground">
                            {{ usuario.creado ?? '—' }}
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
                                    <Link :href="edit(usuario.id)">Editar</Link>
                                </Button>
                                <Button
                                    v-if="
                                        puedeEliminar &&
                                        usuario.id !== usuarioActualId
                                    "
                                    variant="destructive"
                                    size="sm"
                                    @click="usuarioAEliminar = usuario"
                                >
                                    Eliminar
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="usuarios.length === 0">
                        <td
                            colspan="5"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay usuarios que coincidan con los filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirmacion de borrado -->
    <Dialog
        :open="usuarioAEliminar !== null"
        @update:open="(v) => !v && (usuarioAEliminar = null)"
    >
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>¿Eliminar usuario?</DialogTitle>
                <DialogDescription>
                    Se eliminará la cuenta de
                    <strong>{{ usuarioAEliminar?.name }}</strong> de forma
                    permanente. Esta acción no se puede deshacer.
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
