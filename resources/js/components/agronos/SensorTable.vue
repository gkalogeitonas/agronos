<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import type { LiveReading } from '@/composables/useLiveSensorReadings';
import { useTimestamp } from '@/composables/useTimestamp';
import { sensorTypeLabel, sensorUnit } from '@/lib/enums';
import { formatReading } from '@/lib/format';
import type { Sensor } from '@/types/sensor';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    sensors: Sensor[];
    /** Latest readings keyed by sensor id, from `useLiveSensorReadings`. */
    readings: Record<number, LiveReading>;
    minWidth?: string;
    emptyText?: string;
    /** Add a Zone column; the sensors need their `zone` loaded. */
    showZone?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    minWidth: undefined,
    emptyText: 'No sensors yet.',
    showZone: false,
});

const { formatAge } = useTimestamp();

const columns = computed<DataTableColumn[]>(() => [
    { key: 'name', label: 'Sensor', width: '1.8fr' },
    { key: 'type', label: 'Type', width: '1.2fr' },
    ...(props.showZone ? [{ key: 'zone', label: 'Zone', width: '1.2fr' }] : []),
    { key: 'latest', label: 'Latest', mono: true },
    { key: 'seen', label: 'Last seen', mono: true },
]);

const rows = computed(() =>
    props.sensors.map((sensor) => ({
        id: sensor.id,
        name: sensor.name,
        type: sensorTypeLabel(sensor.type),
        zone: sensor.zone?.name ?? '—',
        latest: formatReading(props.readings[sensor.id]?.value, sensorUnit(sensor.type)),
        seen: formatAge(props.readings[sensor.id]?.time),
    })),
);
</script>

<template>
    <DataTable :columns="columns" :rows="rows" row-key="id" :min-width="minWidth" :empty-text="emptyText">
        <template #cell-name="{ row }">
            <Link :href="route('sensors.show', row.id)" class="font-semibold text-foreground hover:text-info">{{ row.name }}</Link>
        </template>
    </DataTable>
</template>
