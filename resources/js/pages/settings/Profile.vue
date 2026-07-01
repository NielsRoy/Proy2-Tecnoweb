<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración de perfil',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.roles ?? []);
</script>

<template>
    <Head title="Configuración de perfil" />

    <h1 class="sr-only">Configuración de perfil</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Perfil"
            description="Actualiza tu nombre, correo electrónico y datos de contacto"
        />

        <div class="flex items-center gap-2">
            <span class="text-sm text-muted-foreground">Rol:</span>
            <template v-if="roles.length">
                <Badge v-for="rol in roles" :key="rol" variant="secondary">
                    {{ rol }}
                </Badge>
            </template>
            <span v-else class="text-sm text-muted-foreground">
                Sin rol asignado
            </span>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Nombre completo"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Correo electrónico"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="ci">CI / Documento</Label>
                    <Input
                        id="ci"
                        class="mt-1 block w-full"
                        name="ci"
                        :default-value="user.ci ?? ''"
                        autocomplete="off"
                        placeholder="Documento de identidad"
                    />
                    <InputError class="mt-2" :message="errors.ci" />
                </div>

                <div class="grid gap-2">
                    <Label for="telefono">Teléfono</Label>
                    <Input
                        id="telefono"
                        class="mt-1 block w-full"
                        name="telefono"
                        :default-value="user.telefono ?? ''"
                        autocomplete="off"
                        placeholder="Número de contacto"
                    />
                    <InputError class="mt-2" :message="errors.telefono" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Guardar</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
