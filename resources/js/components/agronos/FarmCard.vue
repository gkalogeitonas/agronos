<script setup lang="ts">
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import { formatReading } from '@/lib/format';
import { farmStatusLabels } from '@/lib/theme';
import type { FarmSummary } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    farm: FarmSummary;
    zoneCount: number;
}>();

const metrics = computed(() => [
    { label: 'Moisture', value: formatReading(props.farm.moisture, '%') },
    { label: 'Air temp', value: formatReading(props.farm.temperature, '°C') },
    { label: 'Devices', value: `${props.farm.devicesOnline} / ${props.farm.devicesTotal}` },
]);
</script>

<template>
    <article class="flex flex-[1_1_300px] flex-col gap-4 rounded-xl border bg-card p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-0.5">
                <h3 class="m-0 truncate text-xl font-bold">{{ farm.name }}</h3>
                <div class="font-mono text-xs text-muted-foreground">{{ farm.location ?? 'No location' }} · {{ zoneCount }} zones</div>
            </div>
            <StatusBadge :status="farmStatusLabels[farm.status]" />
        </div>
        <dl class="m-0 grid grid-cols-3 gap-3">
            <div v-for="metric in metrics" :key="metric.label" class="flex flex-col gap-0.5">
                <dt class="eyebrow text-micro">{{ metric.label }}</dt>
                <dd class="m-0 font-mono text-metric font-medium">{{ metric.value }}</dd>
            </div>
        </dl>
        <div class="flex items-center justify-between gap-3 border-t border-row pt-3.5">
            <span class="text-sm text-muted-foreground">{{ farm.sensors }} sensors</span>
            <Link
                :href="route('farms.show', farm.id)"
                class="inline-flex min-h-11 items-center text-body font-semibold text-info hover:text-foreground"
            >
                Open farm
            </Link>
        </div>
    </article>
</template>
