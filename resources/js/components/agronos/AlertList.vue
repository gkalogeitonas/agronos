<script setup lang="ts">
export interface AlertItem {
    id: string | number;
    title: string;
    meta: string;
    tone: 'warning' | 'danger' | 'info';
}

defineProps<{
    alerts: AlertItem[];
}>();

const toneStyles: Record<AlertItem['tone'], { row: string; badge: string; mark: string }> = {
    warning: { row: 'bg-warning-surface', badge: 'bg-warning', mark: '!' },
    danger: { row: 'bg-danger-surface', badge: 'bg-destructive', mark: '×' },
    info: { row: 'bg-info-soft', badge: 'bg-info', mark: 'i' },
};
</script>

<template>
    <ul class="m-0 flex list-none flex-col gap-3 p-0">
        <li v-for="alert in alerts" :key="alert.id" class="flex items-start gap-3 rounded-md p-3.5" :class="toneStyles[alert.tone].row">
            <span
                class="flex size-[26px] flex-none items-center justify-center rounded-[6px] font-mono text-sm font-medium text-white"
                :class="toneStyles[alert.tone].badge"
                aria-hidden="true"
                >{{ toneStyles[alert.tone].mark }}</span
            >
            <div class="flex min-w-0 flex-col gap-0.5">
                <div class="text-body font-semibold">{{ alert.title }}</div>
                <div class="text-meta text-ink-soft">{{ alert.meta }}</div>
            </div>
        </li>
        <li v-if="alerts.length === 0" class="py-4 text-body text-muted-foreground">All clear. Nothing needs attention.</li>
    </ul>
</template>
