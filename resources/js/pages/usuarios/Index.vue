<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import { create, destroy, edit, index } from '@/routes/usuarios';

type UsuarioItem = {
    id: number;
    name: string;
    email: string;
    roles: string[];
};

defineProps<{
    usuarios: UsuarioItem[];
    puedeCrear: boolean;
    puedeEditar: boolean;
    puedeEliminar: boolean;
    usuarioActualId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: index() }],
    },
});

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
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Usuarios</h1>
                <p class="text-sm text-muted-foreground">
                    Gestiona las cuentas del sistema y el rol asignado a cada
                    una.
                </p>
            </div>
            <Button v-if="puedeCrear" as-child>
                <Link :href="create()">Nuevo usuario</Link>
            </Button>
        </header>

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
                            colspan="4"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay usuarios registrados.
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
