<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2, Search } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { iconoMenu } from '@/lib/menuIcons';
import { buscar as rutaBuscar } from '@/routes';

// Buscador global (requisito #9), paso 1: sugiere ACCIONES permitidas que coinciden con el término. La
// búsqueda NO se dispara al teclear, solo al pulsar "Buscar" (o Enter). Máximo 5 sugerencias (las limita
// el server). Clic en una sugerencia navega a su destino.
type Sugerencia = {
    label: string;
    url: string;
    icono: string | null;
    modulo: string;
};

const q = ref('');
const acciones = ref<Sugerencia[]>([]);
const abierto = ref(false);
const cargando = ref(false);
const busco = ref(false);
const contenedor = ref<HTMLElement | null>(null);

onClickOutside(contenedor, () => (abierto.value = false));

async function ejecutar(): Promise<void> {
    const termino = q.value.trim();
    if (termino.length < 2) {
        acciones.value = [];
        abierto.value = false;
        return;
    }

    cargando.value = true;
    busco.value = true;
    abierto.value = true;
    try {
        const resp = await fetch(
            rutaBuscar().url + '?q=' + encodeURIComponent(termino),
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        const data = await resp.json().catch(() => ({ acciones: [] }));
        acciones.value = data.acciones ?? [];
    } catch {
        acciones.value = [];
    } finally {
        cargando.value = false;
    }
}

function irA(url: string): void {
    abierto.value = false;
    router.visit(url);
}
</script>

<template>
    <div ref="contenedor" class="relative w-full max-w-xs sm:max-w-sm">
        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    type="search"
                    placeholder="Buscar acciones…"
                    class="pl-8"
                    @keyup.enter="ejecutar"
                    @keydown.escape="abierto = false"
                    @focus="busco && (abierto = true)"
                />
            </div>
            <Button size="sm" :disabled="cargando" @click="ejecutar">Buscar</Button>
        </div>

        <!-- Dropdown de sugerencias -->
        <div
            v-if="abierto"
            class="absolute right-0 z-50 mt-2 w-full min-w-72 overflow-hidden rounded-lg border border-sidebar-border/70 bg-popover text-popover-foreground shadow-md dark:border-sidebar-border"
        >
            <div
                v-if="cargando"
                class="flex items-center gap-2 p-3 text-sm text-muted-foreground"
            >
                <Loader2 class="size-4 animate-spin" />
                Buscando…
            </div>

            <template v-else>
                <ul v-if="acciones.length > 0" class="py-1">
                    <li v-for="(a, i) in acciones" :key="i">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-muted"
                            @click="irA(a.url)"
                        >
                            <component
                                :is="iconoMenu(a.icono)"
                                v-if="iconoMenu(a.icono)"
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="flex flex-col">
                                <span class="font-medium">{{ a.label }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ a.modulo }}
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
                <div v-else class="p-3 text-sm text-muted-foreground">
                    Sin coincidencias.
                </div>
            </template>
        </div>
    </div>
</template>
