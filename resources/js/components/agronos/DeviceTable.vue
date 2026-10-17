<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import { useTimestamp } from '@/composables/useTimestamp';
import { deviceTypeLabel, formatBattery, formatSignal, isLowBattery } from '@/lib/format';
import type { Device } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    /** `farms` holds the names of the farms the device reports to; only shown with `showFarm`. */
    devices: Device[];
    showFarm?: boolean;
    minWidth?: string;
    emptyText?: string;
}

const props = withDefaults(defineProps<Props>(), {
    showFarm: false,
    minWidth: undefined,
    emptyText: 'No devices yet.',
});

const { formatAge } = useTimestamp();

const columns = computed<DataTableColumn[]>(() => [
    { key: 'name', label: 'Device', width: '1.8fr' },
    ...(props.showFarm ? [{ key: 'farm', label: 'Farm', width: '1.4fr' }] : []),
    { key: 'link', label: 'Link', mono: true, width: '0.8fr' },
    { key: 'signal', label: 'Signal', mono: true, width: '0.9fr' },
    { key: 'battery', label: 'Battery', mono: true },
    { key: 'seen', label: 'Last seen', mono: true, width: '0.9fr' },
    { key: 'status', label: 'Status' },
]);

const rows = computed(() =>
    props.devices.map((device) => ({
        ...device,
        farm: device.farms?.length ? device.farms.join(', ') : '–',
        link: deviceTypeLabel(device.type),
        signal: formatSignal(device.signal_strength),
        battery: formatBattery(device.battery_level),
        seen: formatAge(device.last_seen_at),
    })),
);
</script>

<template>
    <DataTable :columns="columns" :rows="rows" row-key="id" :min-width="minWidth" :empty-text="emptyText">
        <template #cell-name="{ row }">
            <Link :href="route('devices.show', row.id)" class="font-semibold text-foreground hover:text-info">{{ row.name }}</Link>
        </template>
        <template #cell-battery="{ row }">
            <span :class="{ 'text-danger-ink': isLowBattery(row.battery_level) }">{{ row.battery }}</span>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :status="row.status" />
        </template>
    </DataTable>
</template>
