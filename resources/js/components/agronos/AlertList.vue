<script setup lang="ts">
import { useTimestamp } from '@/composables/useTimestamp';
import { Link } from '@inertiajs/vue3';

export interface AlertItem {
    id: string | number;
    title: string;
    severity: 'warning' | 'danger' | 'info';
    /** Ready-made second line of a sample row; real alerts send the fields below instead. */
    meta?: string;
    device?: string;
    farm?: string;
    href?: string;
    opened_at?: string;
}

defineProps<{
    alerts: AlertItem[];
}>();

const { formatAge } = useTimestamp();

const severityStyles: Record<AlertItem['severity'], { row: string; badge: string; mark: string }> = {
    warning: { row: 'bg-warning-surface', badge: 'bg-warning', mark: '!' },
    danger: { row: 'bg-danger-surface', badge: 'bg-destructive', mark: '×' },
    info: { row: 'bg-info-soft', badge: 'bg-info', mark: 'i' },
};

function metaLine(alert: AlertItem): string {
    if (alert.meta) {
        return alert.meta;
    }

    const age = formatAge(alert.opened_at);

    return `${alert.device} · ${alert.farm} · ${age === 'now' ? 'just now' : `${age} ago`}`;
}
</script>

<template>
    <ul class="m-0 flex list-none flex-col gap-3 p-0">
        <li v-for="alert in alerts" :key="alert.id">
            <component
                :is="alert.href ? Link : 'div'"
                :href="alert.href"
                class="group flex items-start gap-3 rounded-md p-3.5"
                :class="severityStyles[alert.severity].row"
            >
                <span
                    class="flex size-[26px] flex-none items-center justify-center rounded-[6px] font-mono text-sm font-medium text-white"
                    :class="severityStyles[alert.severity].badge"
                    aria-hidden="true"
                    >{{ severityStyles[alert.severity].mark }}</span
                >
                <div class="flex min-w-0 flex-col gap-0.5">
                    <div class="text-body font-semibold" :class="{ 'group-hover:underline': alert.href }">{{ alert.title }}</div>
                    <div class="text-meta break-words text-ink-soft">{{ metaLine(alert) }}</div>
                </div>
            </component>
        </li>
        <li v-if="alerts.length === 0" class="py-4 text-body text-muted-foreground">All clear. Nothing needs attention.</li>
    </ul>
</template>
