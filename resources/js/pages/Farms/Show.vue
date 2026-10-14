<script setup lang="ts">
import AlertList from '@/components/agronos/AlertList.vue';
import DescriptionList, { type DescriptionItem } from '@/components/agronos/DescriptionList.vue';
import DeviceTable from '@/components/agronos/DeviceTable.vue';
import LineChart from '@/components/agronos/LineChart.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import SensorTable from '@/components/agronos/SensorTable.vue';
import StatTile from '@/components/agronos/StatTile.vue';
import FarmMapbox from '@/components/FarmMapbox.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useLiveSensorReadings } from '@/composables/useLiveSensorReadings';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatReading } from '@/lib/format';
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

const farmInfo = computed<DescriptionItem[]>(() => [
    { label: 'Location', value: props.farm.location },
    { label: 'Size', value: props.farm.size !== null ? `${props.farm.size} m²` : null, mono: true },
    { label: 'Sensors', value: props.sensorDbStats?.totalSensors ?? props.sensors.length, mono: true },
    { label: 'Updated', value: new Date(props.farm.updated_at).toLocaleDateString(), mono: true },
    { label: 'Description', value: props.farm.description || 'No description yet.', stacked: true },
]);

const deleteFarm = () => {
    if (confirm(`Are you sure you want to delete ${props.farm.name}?`)) {
        router.delete(route('farms.destroy', props.farm.id));
    }
};
</script>

<template>
    <Head :title="farm.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
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
                <DeviceTable :devices="devices" empty-text="No devices report to this farm yet." />
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
                    <DescriptionList :items="farmInfo" />
                </SectionCard>
            </div>

            <SectionCard title="Sensors">
                <template #actions>
                    <Link :href="route('sensors.index')" class="text-sm font-medium text-info hover:text-foreground">All sensors</Link>
                </template>
                <SensorTable :sensors="sensors" :readings="liveReadings" empty-text="No sensors on this farm yet." />
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
