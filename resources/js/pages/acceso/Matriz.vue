<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { matriz } from '@/routes/acceso';

type AccionItem = { id: number; clave: string; nombre: string };
type ModuloItem = {
    id: number;
    clave: string;
    nombre: string;
    acciones: AccionItem[];
};
type RolItem = {
    id: number;
    nombre: string;
    descripcion: string | null;
    editable: boolean;
};

const props = defineProps<{
    roles: RolItem[];
    modulos: ModuloItem[];
    // mapa rol_id => [accion_id, ...] con la matriz actual
    asignaciones: Record<number, number[]>;
    // ¿el usuario actual puede editar? Si no, la matriz se ve en solo-lectura.
    puedeEditar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Control de Acceso', href: matriz() }],
    },
});

// Estado local editable: por cada rol, un Set con las acciones marcadas.
const seleccion = reactive<Record<number, Set<number>>>({});
props.roles.forEach((rol) => {
    seleccion[rol.id] = new Set(props.asignaciones[rol.id] ?? []);
});

// Acciones de escritura: tenerlas implica tener "listar" (ver) del mismo modulo.
const ESCRITURA = ['registrar', 'modificar', 'eliminar'];

// Lookups para resolver la dependencia "editar => ver" al togglear un checkbox.
const infoAccion = new Map<number, { moduloId: number; clave: string }>();
const listarDeModulo = new Map<number, number>(); // moduloId => accion "listar"
const escriturasDeModulo = new Map<number, number[]>(); // moduloId => [acciones de escritura]
props.modulos.forEach((modulo) => {
    const escrituras: number[] = [];
    modulo.acciones.forEach((accion) => {
        infoAccion.set(accion.id, { moduloId: modulo.id, clave: accion.clave });
        if (accion.clave === 'listar') {
            listarDeModulo.set(modulo.id, accion.id);
        }
        if (ESCRITURA.includes(accion.clave)) {
            escrituras.push(accion.id);
        }
    });
    escriturasDeModulo.set(modulo.id, escrituras);
});

function estaMarcado(rolId: number, accionId: number): boolean {
    return seleccion[rolId]?.has(accionId) ?? false;
}

function alternar(
    rolId: number,
    accionId: number,
    valor: boolean | 'indeterminate',
): void {
    const set = seleccion[rolId];
    const info = infoAccion.get(accionId);

    if (valor === true) {
        set.add(accionId);
        // Habilitar una accion de escritura implica habilitar "ver" (listar) del modulo.
        if (info && ESCRITURA.includes(info.clave)) {
            const listarId = listarDeModulo.get(info.moduloId);
            if (listarId !== undefined) {
                set.add(listarId);
            }
        }
    } else {
        set.delete(accionId);
        // Quitar "ver" (listar) implica quitar las acciones de escritura del modulo.
        if (info && info.clave === 'listar') {
            escriturasDeModulo
                .get(info.moduloId)
                ?.forEach((id) => set.delete(id));
        }
    }
}

const page = usePage();
const form = useForm<{ asignaciones: Record<number, number[]> }>({
    asignaciones: {},
});

function guardar(): void {
    const payload: Record<number, number[]> = {};
    props.roles.forEach((rol) => {
        if (!rol.editable) {
            return; // el super-rol (Propietario) no se envia: el servidor lo mantiene completo
        }
        payload[rol.id] = Array.from(seleccion[rol.id] ?? []);
    });

    form.asignaciones = payload;
    // Se postea a la misma URL del GET (acceso/matriz), respetando el subdirectorio.
    form.put(page.url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Control de Acceso" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">Matriz de Acceso</h1>
            <p class="text-sm text-muted-foreground">
                Habilita o deshabilita las acciones de cada rol. El menú de cada
                usuario se ajusta automáticamente a estos permisos.
            </p>
        </header>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                        <th class="p-3 text-left font-medium">Recurso / Acción</th>
                        <th
                            v-for="rol in roles"
                            :key="rol.id"
                            class="p-3 text-center font-medium"
                            :title="rol.descripcion ?? ''"
                        >
                            {{ rol.nombre }}
                            <span
                                v-if="!rol.editable"
                                class="block text-xs font-normal text-muted-foreground"
                            >
                                (acceso total)
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="modulo in modulos" :key="modulo.id">
                        <tr class="bg-muted/50">
                            <td
                                :colspan="roles.length + 1"
                                class="px-3 py-2 font-semibold"
                            >
                                {{ modulo.nombre }}
                            </td>
                        </tr>
                        <tr
                            v-for="accion in modulo.acciones"
                            :key="accion.id"
                            class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                        >
                            <td class="py-2 pr-3 pl-8 text-muted-foreground">
                                {{ accion.nombre }}
                            </td>
                            <td
                                v-for="rol in roles"
                                :key="rol.id"
                                class="p-2 text-center"
                            >
                                <div class="flex justify-center">
                                    <Checkbox
                                        :model-value="estaMarcado(rol.id, accion.id)"
                                        :disabled="!puedeEditar || !rol.editable"
                                        @update:model-value="
                                            alternar(rol.id, accion.id, $event)
                                        "
                                    />
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div v-if="puedeEditar" class="flex items-center gap-3">
            <Button :disabled="form.processing" @click="guardar">
                Guardar cambios
            </Button>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            Solo tienes permiso para <strong>ver</strong> la matriz (modo solo-lectura).
        </p>
    </div>
</template>
