<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/usuarios';

type RolItem = { id: number; nombre: string };
type UsuarioEdit = {
    id: number;
    name: string;
    email: string;
    rol_id: number | null;
};

const props = defineProps<{
    usuario: UsuarioEdit | null;
    roles: RolItem[];
    // Al editar al Propietario su rol no se puede cambiar: el selector va bloqueado.
    rolBloqueado: boolean;
}>();

const esEdicion = computed(() => props.usuario !== null);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: index() }],
    },
});

const form = useForm({
    name: props.usuario?.name ?? '',
    email: props.usuario?.email ?? '',
    rol_id: props.usuario?.rol_id ?? ('' as number | ''),
    password: '',
    password_confirmation: '',
});

// Al editar, si no se escribe contrasena nueva, no se envia (queda la actual).
form.transform((data) => {
    if (esEdicion.value && !data.password) {
        const { password, password_confirmation, ...resto } = data;
        return resto;
    }
    return data;
});

function enviar(): void {
    if (props.usuario) {
        form.put(update(props.usuario.id).url, { preserveScroll: true });
    } else {
        form.post(store().url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="esEdicion ? 'Editar usuario' : 'Nuevo usuario'" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">
                {{ esEdicion ? 'Editar usuario' : 'Nuevo usuario' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    esEdicion
                        ? 'Modifica los datos de la cuenta y su rol.'
                        : 'Crea una cuenta y asígnale un rol existente.'
                }}
            </p>
        </header>

        <form class="max-w-xl space-y-6" @submit.prevent="enviar">
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    autocomplete="name"
                    placeholder="Nombre completo"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autocomplete="off"
                    placeholder="correo@ejemplo.com"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="rol_id">Rol</Label>
                <select
                    id="rol_id"
                    v-model="form.rol_id"
                    required
                    :disabled="rolBloqueado"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                >
                    <option value="" disabled>Selecciona un rol</option>
                    <option
                        v-for="rol in roles"
                        :key="rol.id"
                        :value="rol.id"
                    >
                        {{ rol.nombre }}
                    </option>
                </select>
                <p
                    v-if="rolBloqueado"
                    class="text-xs text-muted-foreground"
                >
                    El rol del Propietario no se puede cambiar.
                </p>
                <InputError :message="form.errors.rol_id" />
            </div>

            <div class="grid gap-2">
                <Label for="password">
                    Contraseña
                    <span
                        v-if="esEdicion"
                        class="font-normal text-muted-foreground"
                    >
                        (dejar en blanco para no cambiarla)
                    </span>
                </Label>
                <Input
                    id="password"
                    type="password"
                    v-model="form.password"
                    :required="!esEdicion"
                    autocomplete="new-password"
                    placeholder="Contraseña"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar contraseña</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    v-model="form.password_confirmation"
                    :required="!esEdicion"
                    autocomplete="new-password"
                    placeholder="Repite la contraseña"
                />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ esEdicion ? 'Guardar cambios' : 'Crear usuario' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
