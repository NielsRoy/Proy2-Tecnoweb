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
import { create, destroy, edit, index } from '@/routes/promociones';

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

defineProps<{
    promociones: PromocionItem[];
    puedeCrear: boolean;
    puedeEditar: boolean;
    puedeEliminar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Promociones', href: index() }],
    },
});

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
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Promociones</h1>
                <p class="text-sm text-muted-foreground">
                    Descuentos por producto. No pueden solaparse dos promociones
                    activas del mismo producto.
                </p>
            </div>
            <Button v-if="puedeCrear" as-child>
                <Link :href="create()">Nueva promoción</Link>
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
                            No hay promociones registradas.
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
