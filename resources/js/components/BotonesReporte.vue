<script setup lang="ts">
import { Button } from '@/components/ui/button';

// Botones para descargar el reporte de un listado en PDF / Excel (.xlsx) / CSV. La descarga es una
// navegación normal del navegador (no Inertia): abre la URL del reporte con los filtros actuales.
const props = defineProps<{
    url: string;
    query?: Record<string, string>;
}>();

function exportar(formato: 'pdf' | 'xlsx' | 'csv'): void {
    const q = new URLSearchParams(props.query ?? {});
    q.set('formato', formato);
    window.open(`${props.url}?${q.toString()}`, '_blank');
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button variant="outline" size="sm" @click="exportar('pdf')">
            Exportar PDF
        </Button>
        <Button variant="outline" size="sm" @click="exportar('xlsx')">
            Exportar Excel
        </Button>
        <Button variant="outline" size="sm" @click="exportar('csv')">
            Exportar CSV
        </Button>
    </div>
</template>
