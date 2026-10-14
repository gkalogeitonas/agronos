<script setup lang="ts">
import Sparkline from './Sparkline.vue';

interface Props {
    label: string;
    value: string | number;
    unit?: string;
    note?: string;
    trend?: number[];
    trendColor?: string;
    trendLabel?: string;
}

withDefaults(defineProps<Props>(), {
    unit: '',
    note: undefined,
    trend: undefined,
    trendColor: undefined,
    trendLabel: undefined,
});
</script>

<template>
    <div class="flex flex-[1_1_200px] flex-col gap-2.5 rounded-xl border bg-card p-5">
        <div class="eyebrow text-xs">{{ label }}</div>
        <div class="flex items-baseline gap-1.5 font-mono">
            <span class="text-stat leading-none font-medium">{{ value }}</span>
            <span v-if="unit && value !== '–'" class="text-base text-muted-foreground">{{ unit }}</span>
        </div>
        <Sparkline v-if="trend && trend.length > 1" :values="trend" :color="trendColor" :label="trendLabel ?? `${label} trend`" />
        <div v-if="note" class="text-meta text-muted-foreground">{{ note }}</div>
    </div>
</template>
