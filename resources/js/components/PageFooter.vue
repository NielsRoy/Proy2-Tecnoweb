<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();

// Visitas de la pagina actual (compartido por HandleInertiaRequests). Formateado con
// separador de miles en español; '—' si por algun motivo no viene el dato.
const visits = computed<string>(() => {
    const value = page.props.pageVisits;

    return typeof value === 'number' ? value.toLocaleString('es') : '—';
});
</script>

<template>
    <footer
        class="mt-auto flex items-center justify-center gap-1.5 border-t border-sidebar-border/70 px-4 py-3 text-xs text-muted-foreground"
    >
        <Eye class="h-3.5 w-3.5" />
        <span>Visitas a esta página: {{ visits }}</span>
    </footer>
</template>
