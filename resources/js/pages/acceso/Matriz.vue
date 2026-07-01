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
    // El Propietario (super) es editable en todo menos el módulo "acceso" (bloqueado por celda).
    esSuper: boolean;
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

// Acción "base" de acceso a un módulo (ver la lista/pantalla): 'listar' en los CRUD admin, 'ver' en
// dashboard/mis_compras/mis_pagos. Tener cualquier acción DEPENDIENTE implica tener la base; quitar la
// base quita las dependientes. Ej: en mis_pagos, "realizar pago" (pagar) requiere "ver".
const ACCIONES_BASE = ['listar', 'ver'];
const DEPENDIENTES = ['registrar', 'modificar', 'eliminar', 'reportar', 'pagar'];

// Lookups para resolver la dependencia "=> base (ver/listar)" al togglear un checkbox.
const infoAccion = new Map<number, { moduloId: number; clave: string }>();
const baseDeModulo = new Map<number, number>(); // moduloId => accion base (listar|ver)
const dependientesDeModulo = new Map<number, number[]>(); // moduloId => [acciones que requieren la base]
props.modulos.forEach((modulo) => {
    const dependientes: number[] = [];
    modulo.acciones.forEach((accion) => {
        infoAccion.set(accion.id, { moduloId: modulo.id, clave: accion.clave });
        if (ACCIONES_BASE.includes(accion.clave)) {
            baseDeModulo.set(modulo.id, accion.id);
        }
        if (DEPENDIENTES.includes(accion.clave)) {
            dependientes.push(accion.id);
        }
    });
    dependientesDeModulo.set(modulo.id, dependientes);
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
        // Habilitar una accion dependiente (escritura/reportar/pagar) implica habilitar la base (ver/listar).
        if (info && DEPENDIENTES.includes(info.clave)) {
            const baseId = baseDeModulo.get(info.moduloId);
            if (baseId !== undefined) {
                set.add(baseId);
            }
        }
    } else {
        set.delete(accionId);
        // Quitar la base (ver/listar) implica quitar las acciones que dependen de ella en el modulo.
        if (info && ACCIONES_BASE.includes(info.clave)) {
            dependientesDeModulo
                .get(info.moduloId)
                ?.forEach((id) => set.delete(id));
        }
    }
}

const page = usePage();
const form = useForm<{ asignaciones: Record<number, number[]> }>({
    asignaciones: {},
});

// ¿La celda (rol, módulo) está bloqueada? Solo las del módulo "acceso" para el Propietario, que
// siempre las conserva (anti auto-bloqueo). El resto depende de `puedeEditar`.
function celdaBloqueada(rol: RolItem, modulo: ModuloItem): boolean {
    return rol.esSuper && modulo.clave === 'acceso';
}

function guardar(): void {
    const payload: Record<number, number[]> = {};
    props.roles.forEach((rol) => {
        // Se envían todos los roles (incluido el Propietario); el servidor le re-fuerza "acceso".
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
                                v-if="rol.esSuper"
                                class="block text-xs font-normal text-muted-foreground"
                            >
                                (Control de Acceso fijo)
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
                                        :disabled="
                                            !puedeEditar ||
                                            celdaBloqueada(rol, modulo)
                                        "
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
