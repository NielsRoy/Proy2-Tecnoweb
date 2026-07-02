<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Loader2, Search, X } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { iconoMenu } from '@/lib/menuIcons';
import { buscar as rutaBuscar } from '@/routes';

// Buscador global (requisito #9). Sugiere ACCIONES permitidas (dropdown) y, en las vistas con tabla
// filtrable, actúa como UN FILTRO MÁS: el término `q` se combina (AND) con los demás filtros de la vista
// vía la query string. Se ejecuta solo al pulsar "Buscar" (o Enter). La X limpia solo el término.
type Sugerencia = {
    label: string;
    url: string;
    icono: string | null;
    modulo: string;
};

const page = usePage();
const q = ref('');
const acciones = ref<Sugerencia[]>([]);
const abierto = ref(false);
const cargando = ref(false);
const contenedor = ref<HTMLElement | null>(null);

onClickOutside(contenedor, () => (abierto.value = false));

// La vista actual soporta filtrar su tabla por texto si comparte un prop `filtros` con clave `q`.
function filtrosVista(): Record<string, unknown> | null {
    const f = (page.props as { filtros?: Record<string, unknown> }).filtros;
    return f && 'q' in f ? f : null;
}

// Ruta actual sin query (respeta el subdirectorio) y los params de query actuales (los demás filtros).
function rutaActual(): string {
    return page.url.split('?')[0];
}
function queryActual(): Record<string, string> {
    const params = new URLSearchParams(page.url.split('?')[1] ?? '');
    const obj: Record<string, string> = {};
    params.forEach((valor, clave) => (obj[clave] = valor));
    return obj;
}

// Sincroniza el input con el `q` activo de la vista (así "Limpiar" de la página, o navegar, lo refleja).
watch(
    () => filtrosVista()?.q as string | undefined,
    (valor) => (q.value = valor ?? ''),
    { immediate: true },
);

function navegar(query: Record<string, string>): void {
    router.get(rutaActual(), query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

async function ejecutar(): Promise<void> {
    const termino = q.value.trim();

    if (termino.length < 2) {
        // Término vacío/corto: no sugerencias; y si había filtro q en la vista, lo quitamos.
        acciones.value = [];
        abierto.value = false;
        if (filtrosVista()?.q) {
            const query = queryActual();
            delete query.q;
            navegar(query);
        }
        return;
    }

    // Sugerencias de ACCIONES (AJAX, dropdown).
    cargando.value = true;
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

    // Filtro de tabla: combina `q` con los demás filtros actuales de la vista (los respeta).
    if (filtrosVista()) {
        navegar({ ...queryActual(), q: termino });
    }
}

// X: limpia solo el término (mantiene los demás filtros de la vista).
function limpiar(): void {
    q.value = '';
    abierto.value = false;
    acciones.value = [];
    if (filtrosVista()?.q) {
        const query = queryActual();
        delete query.q;
        navegar(query);
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
                    type="text"
                    placeholder="Buscar…"
                    class="px-8"
                    @keyup.enter="ejecutar"
                    @keydown.escape="abierto = false"
                />
                <button
                    v-if="q"
                    type="button"
                    class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:bg-muted"
                    aria-label="Limpiar búsqueda"
                    @click="limpiar"
                >
                    <X class="size-4" />
                </button>
            </div>
            <Button size="sm" :disabled="cargando" @click="ejecutar">Buscar</Button>
        </div>

        <!-- Dropdown de sugerencias de acciones -->
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
                    Sin acciones que coincidan.
                </div>
            </template>
        </div>
    </div>
</template>
