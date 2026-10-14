<script setup lang="ts">
import AlertList from '@/components/agronos/AlertList.vue';
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import LineChart from '@/components/agronos/LineChart.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatTile from '@/components/agronos/StatTile.vue';
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import FarmMapbox from '@/components/FarmMapbox.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useLiveSensorReadings } from '@/composables/useLiveSensorReadings';
import { useTimestamp } from '@/composables/useTimestamp';
import AppLayout from '@/layouts/AppLayout.vue';
import { deviceTypeLabel, formatBattery, formatReading, formatSignal, isLowBattery } from '@/lib/format';
import { sampleAlerts, sampleIrrigationThreshold, sampleMoistureSeries, sampleTrends } from '@/lib/sampleData';
import { sensorColors, sensorTypeLabel, sensorUnits, signalColors } from '@/lib/theme';
import { type BreadcrumbItem, type DeviceSummary } from '@/types';
import type { Sensor } from '@/types/sensor';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface ReadingStats {
    avgReading: number | null;
    minReading: number | null;
    maxReading: number | null;
}

const props = defineProps<{
    farm: {
        id: number;
        name: string;
        location: string | null;
        size: number | null;
        description: string | null;
        coordinates: { type: string; coordinates: number[][][] } | null;
        created_at: string;
        updated_at: string;
        center: { lng: number; lat: number } | null;
    };
    sensors: Sensor[];
    devices: DeviceSummary[];
    sensorDbStats?: {
        totalSensors: number;
        sensorTypeStats: Record<string, number>;
        lastAvgByType?: Record<string, number | null>;
    };
    timeSeriesStats?: {
        readingStatsByType?: Record<string, ReadingStats>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Farms', href: '/farms' },
    { title: props.farm.name, href: route('farms.show', props.farm.id) },
];

const { formatAge } = useTimestamp();
const liveReadings = useLiveSensorReadings(props.sensors);

const devicesOnline = computed(() => props.devices.filter((device) => device.status === 'online').length);

/** One tile per sensor type the farm has a recent average for, in a fixed signal order. */
const readingTiles = computed(() => {
    const averages = props.sensorDbStats?.lastAvgByType ?? {};
    const order = ['moisture', 'temperature', 'humidity', 'light'];

    return order
        .filter((type) => type in averages)
        .slice(0, 3)
        .map((type) => {
            const range = props.timeSeriesStats?.readingStatsByType?.[type];
            return {
                type,
                label: type === 'temperature' ? 'Air temperature' : sensorTypeLabel(type),
                value: formatReading(averages[type]),
                unit: sensorUnits[type] ?? '',
                note:
                    range && range.minReading !== null
                        ? `24 h range ${formatReading(range.minReading)}–${formatReading(range.maxReading, sensorUnits[type])}`
                        : 'Average of online sensors',
            };
        });
});

const moistureSeries = computed(() =>
    sampleMoistureSeries().map((series, index) => ({ ...series, color: index === 0 ? signalColors.leaf : signalColors.water, dashed: index === 1 })),
);

const deviceColumns: DataTableColumn[] = [
    { key: 'name', label: 'Device', width: '2fr' },
    { key: 'type', label: 'Link', mono: true },
    { key: 'signal', label: 'Signal', mono: true },
    { key: 'battery', label: 'Battery', mono: true },
    { key: 'seen', label: 'Last seen', mono: true },
    { key: 'status', label: 'Status', width: '1.2fr' },
];

const deviceRows = computed(() =>
    props.devices.map((device) => ({
        ...device,
        type: deviceTypeLabel(device.type),
        signal: formatSignal(device.signal_strength),
        battery: formatBattery(device.battery_level),
        seen: formatAge(device.last_seen_at),
    })),
);

const sensorColumns: DataTableColumn[] = [
    { key: 'name', label: 'Sensor', width: '2fr' },
    { key: 'type', label: 'Type' },
    { key: 'latest', label: 'Latest', mono: true },
    { key: 'seen', label: 'Last seen', mono: true },
];

const sensorRows = computed(() =>
    props.sensors.map((sensor) => ({
        id: sensor.id,
        name: sensor.name,
        type: sensorTypeLabel(sensor.type),
        latest: formatReading(liveReadings[sensor.id]?.value, sensorUnits[sensor.type] ?? ''),
        seen: formatAge(liveReadings[sensor.id]?.time),
    })),
);

const deleteFarm = () => {
    if (confirm(`Are you sure you want to delete ${props.farm.name}?`)) {
        router.delete(route('farms.destroy', props.farm.id));
    }
};
</script>

<template>
    <Head :title="farm.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 md:p-8">
            <PageHeader eyebrow="Farm detail" :title="farm.name" sample-data>
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="route('farms.edit', farm.id)"><Pencil /> Edit</Link>
                    </Button>
                    <Button variant="outline" class="text-destructive hover:text-destructive" @click="deleteFarm"><Trash2 /> Delete</Button>
                    <Button as-child>
                        <Link :href="route('farms.sensors.create', { farm: farm.id })"><Plus /> Add sensor</Link>
                    </Button>
                </template>
            </PageHeader>

            <section aria-label="Key readings" class="flex flex-wrap gap-4">
                <StatTile
                    v-for="tile in readingTiles"
                    :key="tile.type"
                    :label="tile.label"
                    :value="tile.value"
                    :unit="tile.unit"
                    :note="tile.note"
                    :trend="sampleTrends[tile.type]"
                    :trend-color="sensorColors[tile.type]"
                    :trend-label="`${tile.label} trend, sample data`"
                />
                <StatTile
                    label="Devices online"
                    :value="devicesOnline"
                    :unit="`/ ${devices.length}`"
                    :note="devices.length - devicesOnline === 0 ? 'All reporting' : `${devices.length - devicesOnline} need attention`"
                    :trend="sampleTrends.devices"
                    :trend-color="signalColors.ink"
                    trend-label="Devices online trend, sample data"
                />
            </section>

            <div class="flex flex-wrap items-stretch gap-4">
                <SectionCard title="Soil moisture, last 24 h" class="flex-[2_1_480px]">
                    <LineChart
                        :series="moistureSeries"
                        unit="%"
                        :threshold="{ value: sampleIrrigationThreshold, label: 'Irrigation threshold' }"
                        label="Line chart of soil moisture for Zone A and Zone B over the last 24 hours, sample data"
                    />
                </SectionCard>

                <SectionCard title="Needs attention" class="flex-[1_1_280px]">
                    <template #actions>
                        <Link href="/alerts" class="text-sm font-medium text-info hover:text-foreground">View all</Link>
                    </template>
                    <AlertList :alerts="sampleAlerts.slice(1, 4)" />
                </SectionCard>
            </div>

            <SectionCard title="Devices">
                <DataTable :columns="deviceColumns" :rows="deviceRows" row-key="id" empty-text="No devices report to this farm yet.">
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
            </SectionCard>

            <div class="flex flex-wrap items-stretch gap-4">
                <SectionCard title="Map" class="flex-[2_1_480px]">
                    <FarmMapbox
                        :center="farm.center ? [farm.center.lng, farm.center.lat] : [0, 0]"
                        :farm-polygon="farm.coordinates"
                        :sensors="sensors.map((sensor) => ({ lat: Number(sensor.lat), lon: Number(sensor.lon), name: sensor.name }))"
                    />
                </SectionCard>

                <SectionCard title="Farm information" class="flex-[1_1_280px]">
                    <dl class="m-0 flex flex-col">
                        <div class="flex justify-between gap-4 border-b border-row py-3">
                            <dt class="text-sm text-muted-foreground">Location</dt>
                            <dd class="m-0 text-right text-[15px]">{{ farm.location ?? '–' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-row py-3">
                            <dt class="text-sm text-muted-foreground">Size</dt>
                            <dd class="m-0 font-mono text-sm">{{ farm.size !== null ? `${farm.size} m²` : '–' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-row py-3">
                            <dt class="text-sm text-muted-foreground">Sensors</dt>
                            <dd class="m-0 font-mono text-sm">{{ sensorDbStats?.totalSensors ?? sensors.length }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-row py-3">
                            <dt class="text-sm text-muted-foreground">Updated</dt>
                            <dd class="m-0 font-mono text-sm">{{ new Date(farm.updated_at).toLocaleDateString() }}</dd>
                        </div>
                        <div class="flex flex-col gap-1 pt-3">
                            <dt class="text-sm text-muted-foreground">Description</dt>
                            <dd class="m-0 text-[15px]">{{ farm.description || 'No description yet.' }}</dd>
                        </div>
                    </dl>
                </SectionCard>
            </div>

            <SectionCard title="Sensors">
                <template #actions>
                    <Link :href="route('sensors.index')" class="text-sm font-medium text-info hover:text-foreground">All sensors</Link>
                </template>
                <DataTable :columns="sensorColumns" :rows="sensorRows" row-key="id" empty-text="No sensors on this farm yet.">
                    <template #cell-name="{ row }">
                        <Link :href="route('sensors.show', row.id)" class="font-semibold text-foreground hover:text-info">{{ row.name }}</Link>
                    </template>
                </DataTable>
            </SectionCard>
        </div>
    </AppLayout>
</template>
