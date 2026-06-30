<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/promociones';

type ProductoOpcion = { id: number; nombre: string };

type PromocionEdit = {
    id: number;
    producto_id: number;
    nombre: string;
    descripcion: string | null;
    tipo_descuento: string;
    valor: string;
    fecha_inicio: string | null;
    fecha_fin: string | null;
};

const props = defineProps<{
    promocion: PromocionEdit | null;
    productos: ProductoOpcion[];
}>();

const esEdicion = computed(() => props.promocion !== null);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Promociones', href: index() }],
    },
});

// Fecha local del dispositivo (no toISOString, que da UTC y salta de día en Bolivia).
const ahora = new Date();
const hoy = `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`;

const form = useForm<{
    producto_id: string;
    nombre: string;
    descripcion: string;
    tipo_descuento: string;
    valor: string;
    fecha_inicio: string;
    fecha_fin: string;
}>({
    producto_id: props.promocion ? String(props.promocion.producto_id) : '',
    nombre: props.promocion?.nombre ?? '',
    descripcion: props.promocion?.descripcion ?? '',
    tipo_descuento: props.promocion?.tipo_descuento ?? 'porcentaje',
    valor: props.promocion?.valor ?? '',
    fecha_inicio: props.promocion?.fecha_inicio ?? hoy,
    fecha_fin: props.promocion?.fecha_fin ?? hoy,
});

function enviar(): void {
    if (props.promocion) {
        form.put(update(props.promocion.id).url, { preserveScroll: true });
    } else {
        form.post(store().url, { preserveScroll: true });
    }
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Head :title="esEdicion ? 'Editar promoción' : 'Nueva promoción'" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold">
                {{ esEdicion ? 'Editar promoción' : 'Nueva promoción' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Define un descuento por producto y su rango de fechas.
            </p>
        </header>

        <form class="max-w-xl space-y-6" @submit.prevent="enviar">
            <div class="grid gap-2">
                <Label for="producto_id">Producto</Label>
                <select
                    id="producto_id"
                    v-model="form.producto_id"
                    :class="selectClass"
                    required
                >
                    <option value="" disabled>Selecciona un producto…</option>
                    <option v-for="p in productos" :key="p.id" :value="String(p.id)">
                        {{ p.nombre }}
                    </option>
                </select>
                <InputError :message="form.errors.producto_id" />
            </div>

            <div class="grid gap-2">
                <Label for="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    v-model="form.nombre"
                    required
                    placeholder="Ej. Oferta de temporada"
                />
                <InputError :message="form.errors.nombre" />
            </div>

            <div class="grid gap-2">
                <Label for="descripcion">Descripción</Label>
                <textarea
                    id="descripcion"
                    v-model="form.descripcion"
                    rows="2"
                    placeholder="Descripción de la promoción (opcional)"
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                ></textarea>
                <InputError :message="form.errors.descripcion" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="tipo_descuento">Tipo de descuento</Label>
                    <select
                        id="tipo_descuento"
                        v-model="form.tipo_descuento"
                        :class="selectClass"
                        required
                    >
                        <option value="porcentaje">Porcentaje (%)</option>
                        <option value="monto">Monto fijo (Bs)</option>
                    </select>
                    <InputError :message="form.errors.tipo_descuento" />
                </div>
                <div class="grid gap-2">
                    <Label for="valor">
                        Valor
                        {{ form.tipo_descuento === 'porcentaje' ? '(%)' : '(Bs)' }}
                    </Label>
                    <Input
                        id="valor"
                        type="number"
                        step="0.01"
                        min="0"
                        v-model="form.valor"
                        required
                        placeholder="0.00"
                    />
                    <InputError :message="form.errors.valor" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="fecha_inicio">Fecha de inicio</Label>
                    <Input
                        id="fecha_inicio"
                        type="date"
                        v-model="form.fecha_inicio"
                        required
                    />
                    <InputError :message="form.errors.fecha_inicio" />
                </div>
                <div class="grid gap-2">
                    <Label for="fecha_fin">Fecha de fin</Label>
                    <Input
                        id="fecha_fin"
                        type="date"
                        v-model="form.fecha_fin"
                        required
                    />
                    <InputError :message="form.errors.fecha_fin" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ esEdicion ? 'Guardar cambios' : 'Crear promoción' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
