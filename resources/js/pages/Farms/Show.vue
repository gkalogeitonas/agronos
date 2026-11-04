<script setup lang="ts">
import AlertList from '@/components/agronos/AlertList.vue';
import ConfirmDeleteButton from '@/components/agronos/ConfirmDeleteButton.vue';
import DescriptionList, { type DescriptionItem } from '@/components/agronos/DescriptionList.vue';
import DeviceTable from '@/components/agronos/DeviceTable.vue';
import EmptyState from '@/components/agronos/EmptyState.vue';
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
import { sensorTypeLabel, sensorUnit } from '@/lib/enums';
import { formatReading } from '@/lib/format';
import { sampleAlerts, sampleIrrigationThreshold, sampleMoistureSeries, sampleTrends } from '@/lib/sampleData';
import { sensorColors, signalColors } from '@/lib/theme';
import { type BreadcrumbItem, type Device } from '@/types';
import type { Sensor } from '@/types/sensor';
import { Head, Link } from '@inertiajs/vue3';
import type { Polygon } from 'geojson';
import { Pencil, Plus } from 'lucide-vue-next';
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
        coordinates: Polygon | null;
        created_at: string;
        updated_at: string;
        center: { lng: number; lat: number } | null;
    };
    /** The first sensors of the farm, by name; `sensorDbStats.totalSensors` has the full count. */
    sensors: Sensor[];
    /** Every sensor of the farm, with only what the map needs. */
    sensorLocations: { id: number; name: string; lat: number | string | null; lon: number | string | null }[];
    /** The first devices of the farm, by name. */
    devices: Device[];
    devicesTotal: number;
    devicesOnline: number;
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
    { title: 'Farms', href: route('farms.index') },
    { title: props.farm.name, href: route('farms.show', props.farm.id) },
];

const liveReadings = useLiveSensorReadings(props.sensors);

const hasDevices = computed(() => props.devicesTotal > 0);
const sensorsTotal = computed(() => props.sensorDbStats?.totalSensors ?? props.sensors.length);

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
                unit: sensorUnit(type),
                note:
                    range && range.minReading !== null
                        ? `24 h range ${formatReading(range.minReading)}–${formatReading(range.maxReading, sensorUnit(type))}`
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
    { label: 'Sensors', value: sensorsTotal.value, mono: true },
    { label: 'Updated', value: new Date(props.farm.updated_at).toLocaleDateString(), mono: true },
    { label: 'Description', value: props.farm.description || 'No description yet.', stacked: true },
]);
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
                    <ConfirmDeleteButton :url="route('farms.destroy', farm.id)" :item-name="farm.name" />
                    <Button as-child>
                        <Link :href="route('devices.create', { farm: farm.id })"><Plus /> Add device</Link>
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
                    :unit="`/ ${devicesTotal}`"
                    :note="devicesTotal - devicesOnline === 0 ? 'All reporting' : `${devicesTotal - devicesOnline} need attention`"
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
                        <Link :href="route('alerts.index')" class="text-sm font-medium text-info hover:text-foreground">View all</Link>
                    </template>
                    <AlertList :alerts="sampleAlerts.slice(1, 4)" />
                </SectionCard>
            </div>

            <SectionCard title="Sensors">
                <template #actions>
                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            v-if="sensorsTotal > 0"
                            :href="route('sensors.index', { farm: farm.id })"
                            class="text-sm font-medium text-info hover:text-foreground"
                            >View all {{ sensorsTotal }}</Link
                        >
                        <Button v-if="hasDevices" variant="outline" size="sm" as-child>
                            <Link :href="route('farms.sensors.create', { farm: farm.id })"><Plus /> Add sensor</Link>
                        </Button>
                    </div>
                </template>
                <template v-if="hasDevices">
                    <p v-if="sensorsTotal > sensors.length" class="m-0 font-mono text-meta text-muted-foreground">
                        Showing {{ sensors.length }} of {{ sensorsTotal }} sensors
                    </p>
                    <SensorTable :sensors="sensors" :readings="liveReadings" empty-text="No sensors on this farm yet." />
                </template>
                <EmptyState v-else title="No devices on this farm yet" text="Sensors are added to a device. Add a device to this farm first.">
                    <Button as-child>
                        <Link :href="route('devices.create', { farm: farm.id })"><Plus /> Add device</Link>
                    </Button>
                </EmptyState>
            </SectionCard>

            <div class="flex flex-wrap items-stretch gap-4">
                <SectionCard title="Map" class="flex-[2_1_480px]">
                    <FarmMapbox
                        :center="farm.center ? [farm.center.lng, farm.center.lat] : [0, 0]"
                        :farm-polygon="farm.coordinates"
                        :sensors="sensorLocations.map((sensor) => ({ lat: Number(sensor.lat), lon: Number(sensor.lon), name: sensor.name }))"
                    />
                </SectionCard>

                <SectionCard title="Farm information" class="flex-[1_1_280px]">
                    <DescriptionList :items="farmInfo" />
                </SectionCard>
            </div>

            <SectionCard title="Devices">
                <template v-if="hasDevices" #actions>
                    <Link :href="route('devices.index', { farm: farm.id })" class="text-sm font-medium text-info hover:text-foreground"
                        >View all {{ devicesTotal }}</Link
                    >
                </template>
                <p v-if="devicesTotal > devices.length" class="m-0 font-mono text-meta text-muted-foreground">
                    Showing {{ devices.length }} of {{ devicesTotal }} devices
                </p>
                <DeviceTable :devices="devices" empty-text="No devices on this farm yet." />
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
