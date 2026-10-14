<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import type { LiveReading } from '@/composables/useLiveSensorReadings';
import { useTimestamp } from '@/composables/useTimestamp';
import { formatReading } from '@/lib/format';
import { sensorTypeLabel, sensorUnits } from '@/lib/theme';
import type { Sensor } from '@/types/sensor';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    sensors: Sensor[];
    /** Latest readings keyed by sensor id, from `useLiveSensorReadings`. */
    readings: Record<number, LiveReading>;
    minWidth?: string;
    emptyText?: string;
}

const props = withDefaults(defineProps<Props>(), {
    minWidth: undefined,
    emptyText: 'No sensors yet.',
});

const { formatAge } = useTimestamp();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Sensor', width: '1.8fr' },
    { key: 'type', label: 'Type', width: '1.2fr' },
    { key: 'latest', label: 'Latest', mono: true },
    { key: 'seen', label: 'Last seen', mono: true },
];

const rows = computed(() =>
    props.sensors.map((sensor) => ({
        id: sensor.id,
        name: sensor.name,
        type: sensorTypeLabel(sensor.type),
        latest: formatReading(props.readings[sensor.id]?.value, sensorUnits[sensor.type] ?? ''),
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
