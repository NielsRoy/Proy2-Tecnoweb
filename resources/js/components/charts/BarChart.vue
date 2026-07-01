<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { colorGrilla, colorTexto, coloresPara } from '@/lib/chartColors';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps<{
    labels: string[];
    data: number[];
    label?: string;
    ejeY?: string | null;
}>();

const chartData = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            label: props.label ?? '',
            data: props.data,
            backgroundColor: coloresPara(props.data.length),
            borderRadius: 4,
        },
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { enabled: true },
    },
    scales: {
        x: {
            ticks: { color: colorTexto() },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: { color: colorTexto() },
            grid: { color: colorGrilla() },
            title: {
                display: !!props.ejeY,
                text: props.ejeY ?? '',
                color: colorTexto(),
            },
        },
    },
}));
</script>

<template>
    <div class="h-64">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
