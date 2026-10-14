<script setup lang="ts">
import { signalColors } from '@/lib/theme';
import { computed } from 'vue';

interface Props {
    values: number[];
    color?: string;
    label: string;
}

const props = withDefaults(defineProps<Props>(), {
    color: signalColors.ink,
});

const width = 120;
const height = 32;
const padding = 3;

const points = computed(() => {
    if (props.values.length < 2) {
        return '';
    }
    const min = Math.min(...props.values);
    const max = Math.max(...props.values);
    const range = max - min || 1;
    const step = width / (props.values.length - 1);

    return props.values
        .map((value, index) => {
            const y = height - padding - ((value - min) / range) * (height - padding * 2);
            return `${(index * step).toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
});
</script>

<template>
    <svg :viewBox="`0 0 ${width} ${height}`" width="100%" :height="height" preserveAspectRatio="none" role="img" :aria-label="label">
        <polyline
            :points="points"
            fill="none"
            :stroke="color"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
    </svg>
</template>
