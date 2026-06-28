<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/productos';

type ProductoEdit = {
    id: number;
    nombre: string;
    descripcion: string | null;
    precio: string;
    foto_url: string | null;
};

const props = defineProps<{
    producto: ProductoEdit | null;
}>();

const esEdicion = computed(() => props.producto !== null);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Productos', href: index() }],
    },
});

const form = useForm<{
    nombre: string;
    descripcion: string;
    precio: string;
    foto: File | null;
}>({
    nombre: props.producto?.nombre ?? '',
    descripcion: props.producto?.descripcion ?? '',
    precio: props.producto?.precio ?? '',
    foto: null,
});

// Vista previa: la foto recién elegida, o la actual del producto al editar.
const previewNueva = ref<string | null>(null);
const preview = computed(
    () => previewNueva.value ?? props.producto?.foto_url ?? null,
);

function onFoto(e: Event): void {
    const archivo = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.foto = archivo;
    previewNueva.value = archivo ? URL.createObjectURL(archivo) : null;
}

function enviar(): void {
    if (props.producto) {
        // PUT con archivo: se postea con _method=put (PHP no parsea multipart en PUT).
        form
            .transform((data) => ({ ...data, _method: 'put' }))
            .post(update(props.producto.id).url, {
                preserveScroll: true,
                forceFormData: true,
            });
    } else {
        form.post(store().url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="esEdicion ? 'Editar producto' : 'Nuevo producto'" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">
                {{ esEdicion ? 'Editar producto' : 'Nuevo producto' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    esEdicion
                        ? 'Modifica los datos del producto. El stock no se edita aquí.'
                        : 'Registra un producto del catálogo. Nace con stock 0.'
                }}
            </p>
        </header>

        <form class="max-w-xl space-y-6" @submit.prevent="enviar">
            <div class="grid gap-2">
                <Label for="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    v-model="form.nombre"
                    required
                    placeholder="Ej. Aceite de Girasol 5L"
                />
                <InputError :message="form.errors.nombre" />
            </div>

            <div class="grid gap-2">
                <Label for="descripcion">Descripción</Label>
                <textarea
                    id="descripcion"
                    v-model="form.descripcion"
                    rows="3"
                    placeholder="Descripción del producto (opcional)"
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                ></textarea>
                <InputError :message="form.errors.descripcion" />
            </div>

            <div class="grid gap-2">
                <Label for="precio">Precio (Bs)</Label>
                <Input
                    id="precio"
                    type="number"
                    step="0.01"
                    min="0"
                    v-model="form.precio"
                    required
                    placeholder="0.00"
                />
                <InputError :message="form.errors.precio" />
            </div>

            <div class="grid gap-2">
                <Label for="foto">
                    Foto
                    <span
                        v-if="esEdicion"
                        class="font-normal text-muted-foreground"
                    >
                        (sube una nueva para reemplazarla)
                    </span>
                </Label>
                <div class="flex items-center gap-4">
                    <img
                        v-if="preview"
                        :src="preview"
                        alt="Vista previa"
                        class="h-20 w-20 rounded-md border border-sidebar-border/70 object-cover dark:border-sidebar-border"
                    />
                    <input
                        id="foto"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-secondary file:px-4 file:py-2 file:text-sm file:font-medium file:text-secondary-foreground hover:file:bg-secondary/80"
                        @change="onFoto"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    JPG, PNG o WEBP. Máximo 2 MB.
                </p>
                <InputError :message="form.errors.foto" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ esEdicion ? 'Guardar cambios' : 'Crear producto' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
