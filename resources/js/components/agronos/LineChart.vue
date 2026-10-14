<script setup lang="ts">
import { signalColors } from '@/lib/theme';
import type { ApexOptions } from 'apexcharts';
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

export interface LineChartSeries {
    name: string;
    data: { x: number | string; y: number | null }[];
    color?: string;
    dashed?: boolean;
}

interface Props {
    series: LineChartSeries[];
    label: string;
    height?: number;
    unit?: string;
    /** Draws a dashed horizontal threshold line, e.g. the irrigation threshold. */
    threshold?: { value: number; label: string };
}

const props = withDefaults(defineProps<Props>(), {
    height: 240,
    unit: '',
    threshold: undefined,
});

const fallbackColors = [signalColors.leaf, signalColors.water, signalColors.ink, signalColors.threshold];

const chartSeries = computed(() => props.series.map(({ name, data }) => ({ name, data })));

const options = computed<ApexOptions>(() => ({
    chart: {
        type: 'line',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: "'Space Grotesk', system-ui, sans-serif",
        animations: { enabled: false },
    },
    colors: props.series.map((series, index) => series.color ?? fallbackColors[index % fallbackColors.length]),
    stroke: { width: 3, curve: 'straight', dashArray: props.series.map((series) => (series.dashed ? 7 : 0)) },
    grid: { borderColor: signalColors.grid, strokeDashArray: 0, xaxis: { lines: { show: false } } },
    legend: { show: props.series.length > 1, position: 'top', horizontalAlign: 'right', fontSize: '13px' },
    xaxis: {
        type: 'datetime',
        labels: { datetimeUTC: false, style: { colors: signalColors.muted, fontFamily: "'IBM Plex Mono', monospace", fontSize: '12px' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: { colors: signalColors.muted, fontFamily: "'IBM Plex Mono', monospace", fontSize: '12px' },
            formatter: (value: number) => `${Math.round(value)}${props.unit}`,
        },
    },
    tooltip: { x: { format: 'dd MMM HH:mm' } },
    annotations: props.threshold
        ? {
              yaxis: [
                  {
                      y: props.threshold.value,
                      borderColor: signalColors.threshold,
                      strokeDashArray: 4,
                      label: {
                          text: props.threshold.label,
                          borderWidth: 0,
                          style: { background: 'transparent', color: signalColors.threshold, fontSize: '12px' },
                      },
                  },
              ],
          }
        : {},
}));
</script>

<template>
    <div role="img" :aria-label="label">
        <VueApexCharts type="line" :height="height" :options="options" :series="chartSeries" />
    </div>
</template>
