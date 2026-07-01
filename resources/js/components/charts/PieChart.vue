<script setup lang="ts">
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
import { computed } from 'vue';
import { Pie } from 'vue-chartjs';
import { colorTexto, coloresPara } from '@/lib/chartColors';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps<{
    labels: string[];
    data: number[];
}>();

const chartData = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            data: props.data,
            backgroundColor: coloresPara(props.data.length),
            borderWidth: 0,
        },
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
            labels: { color: colorTexto() },
        },
        tooltip: { enabled: true },
    },
}));
</script>

<template>
    <div class="h-64">
        <Pie :data="chartData" :options="chartOptions" />
    </div>
</template>
