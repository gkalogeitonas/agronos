<script setup lang="ts">
import { statusTone, type StatusTone } from '@/lib/theme';
import { computed } from 'vue';

interface Props {
    status: string | null | undefined;
    /** Overrides the tone derived from `status`. */
    tone?: StatusTone;
}

const props = defineProps<Props>();

const toneClasses: Record<StatusTone, string> = {
    success: 'bg-success-soft text-success-foreground',
    warning: 'bg-warning-soft text-warning-foreground',
    danger: 'bg-danger-soft text-danger-ink',
    info: 'bg-info-soft text-info',
    neutral: 'bg-muted text-muted-foreground',
};

const classes = computed(() => toneClasses[props.tone ?? statusTone(props.status)]);
const label = computed(() => {
    const value = props.status ?? 'Unknown';
    return value.charAt(0).toUpperCase() + value.slice(1);
});
</script>

<template>
    <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-meta font-semibold whitespace-nowrap" :class="classes">{{ label }}</span>
</template>
