<script setup lang="ts">
import ConfirmDeleteButton from '@/components/agronos/ConfirmDeleteButton.vue';
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import DescriptionList, { type DescriptionItem } from '@/components/agronos/DescriptionList.vue';
import FilterSelect from '@/components/agronos/FilterSelect.vue';
import LineChart from '@/components/agronos/LineChart.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatTile from '@/components/agronos/StatTile.vue';
import FarmMapbox from '@/components/FarmMapbox.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import useTimestamp from '@/composables/useTimestamp';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatReading } from '@/lib/format';
import { sensorTypeLabel } from '@/lib/enums';
import { sensorColors, signalColors } from '@/lib/theme';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import type { Polygon } from 'geojson';
import { Pencil } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/** Shape of App\Http\Resources\SensorResource with `farm` and `device` loaded. */
interface SensorDetail {
    id: number;
    name: string | null;
    uuid: string;
    type: string;
    device: { id: number; uuid: string } | null;
    farm: { id: number; name: string; coordinates: Polygon | null } | null;
    lat: number | string | null;
    lon: number | string | null;
    last_reading: number | string | null;
    last_reading_at: string | null;
    unit: string;
}

interface Reading {
    time: string;
    value: number | string;
}

const props = defineProps<{
    sensor: SensorDetail;
    recentReadings?: Reading[];
    stats?: { min: number | null; max: number | null; avg: number | null; count?: number };
    /** InfluxDB chart readings as [ms, value] pairs. */
    chartData?: [number, number][];
    rangeOptions?: Array<{ value: string; label: string }>;
    selectedRange?: string;
}>();

const MAX_RECENT_READINGS = 50;

/** Latest reading, kept separate from the prop so realtime events never mutate Inertia props. */
const latestReading = ref<{ value: number | string | null; time: string | null }>({ value: null, time: null });

/** Mutable copy of the deferred readings, so realtime events can prepend to it. */
const readings = ref<Reading[]>([]);

watch(
    () => props.sensor,
    (sensor) => {
        latestReading.value = { value: sensor.last_reading, time: sensor.last_reading_at };
    },
    { immediate: true },
);

watch(
    () => props.recentReadings,
    (recent) => {
        readings.value = recent ?? [];
    },
    { immediate: true },
);

const stats = computed(() => props.stats ?? { min: null, max: null, avg: null, count: 0 });

const { formatTimestamp } = useTimestamp();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Sensors', href: route('sensors.index') },
    { title: props.sensor.name || 'Sensor', href: route('sensors.show', props.sensor.id) },
]);

const unit = computed(() => props.sensor.unit ?? '');
const rangeLabel = computed(() => props.rangeOptions?.find((option) => option.value === selectedRange.value)?.label ?? 'Selected range');

const series = computed(() => [
    {
        name: sensorTypeLabel(props.sensor.type),
        color: sensorColors[props.sensor.type] ?? signalColors.ink,
        data: (props.chartData ?? []).map(([x, y]) => ({ x, y })),
    },
]);

const sensorInfo = computed<DescriptionItem[]>(() => [
    { label: 'Sensor UUID', value: props.sensor.uuid, mono: true },
    { key: 'device', label: 'Device', value: props.sensor.device?.uuid, mono: true },
    { label: 'Type', value: sensorTypeLabel(props.sensor.type) },
    { key: 'farm', label: 'Farm', value: props.sensor.farm?.name },
    { label: 'Coordinates', value: `${props.sensor.lat ?? '–'}, ${props.sensor.lon ?? '–'}`, mono: true },
]);

const readingColumns: DataTableColumn[] = [
    { key: 'time', label: 'Time', mono: true, width: '2fr' },
    { key: 'value', label: 'Value', mono: true },
];

const readingRows = computed(() =>
    readings.value.map((row) => ({ time: formatTimestamp(row.time), value: formatReading(row.value, unit.value, 2) })),
);

const urlRange = new URL(window.location.href).searchParams.get('range');
const selectedRange = ref<string>(props.selectedRange ?? urlRange ?? props.rangeOptions?.[1]?.value ?? '-24h');

/**
 * Reloads the page props for the chosen range without a full browser reload.
 */
function applyRange(): void {
    router.get(route('sensors.show', props.sensor.id), { range: selectedRange.value }, { replace: true, preserveScroll: true });
}

useEcho<{ value?: number | string; time?: string }>(`sensor.${props.sensor.id}`, 'SensorReadingEvent', (payload) => {
    if (!payload?.time || payload.value === undefined) {
        return;
    }
    latestReading.value = { value: payload.value, time: payload.time };
    readings.value = [{ time: payload.time, value: payload.value }, ...readings.value].slice(0, MAX_RECENT_READINGS);
});
</script>

<template>
    <Head :title="sensor.name ? sensor.name : 'Sensor'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader :eyebrow="`${sensorTypeLabel(sensor.type)} sensor`" :title="sensor.name || 'Unnamed sensor'">
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="route('sensors.edit', sensor.id)"><Pencil /> Edit</Link>
                    </Button>
                    <ConfirmDeleteButton :url="route('sensors.destroy', sensor.id)" :item-name="sensor.name || 'this sensor'" />
                </template>
            </PageHeader>

            <section aria-label="Readings" class="flex flex-wrap gap-4">
                <StatTile
                    label="Latest"
                    :value="formatReading(latestReading.value, '', 2)"
                    :unit="unit"
                    :note="latestReading.time ? formatTimestamp(latestReading.time) : 'No readings yet'"
                />
                <template v-if="props.stats !== undefined">
                    <StatTile :label="`Min · ${rangeLabel}`" :value="formatReading(stats.min, '', 2)" :unit="unit" />
                    <StatTile :label="`Max · ${rangeLabel}`" :value="formatReading(stats.max, '', 2)" :unit="unit" />
                    <StatTile :label="`Average · ${rangeLabel}`" :value="formatReading(stats.avg, '', 2)" :unit="unit" />
                </template>
                <template v-else>
                    <div v-for="n in 3" :key="n" class="h-[132px] flex-[1_1_200px] animate-pulse rounded-xl border bg-card" aria-hidden="true"></div>
                </template>
            </section>

            <SectionCard title="Reading history">
                <template #actions>
                    <FilterSelect v-model="selectedRange" label="Range" @change="applyRange">
                        <option v-for="opt in rangeOptions ?? []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </FilterSelect>
                </template>
                <div v-if="chartData === undefined" class="h-[320px] animate-pulse rounded-md bg-muted" aria-label="Loading chart"></div>
                <LineChart
                    v-else-if="series[0].data.length > 1"
                    :series="series"
                    :unit="unit"
                    :height="320"
                    :label="`Line chart of ${sensorTypeLabel(sensor.type).toLowerCase()} readings, ${rangeLabel.toLowerCase()}`"
                />
                <div v-else class="flex h-[200px] items-center justify-center rounded-md bg-muted/60 text-body text-muted-foreground">
                    No readings in the selected range.
                </div>
            </SectionCard>

            <div class="flex flex-wrap items-stretch gap-4">
                <SectionCard v-if="sensor.lat && sensor.lon" title="Location" class="flex-[2_1_480px]">
                    <FarmMapbox
                        :center="[Number(sensor.lon), Number(sensor.lat)]"
                        :farm-polygon="sensor.farm?.coordinates"
                        :sensors="[{ lat: Number(sensor.lat), lon: Number(sensor.lon), name: sensor.name ?? undefined }]"
                        :zoom="15"
                    />
                </SectionCard>

                <SectionCard title="Sensor information" class="flex-[1_1_280px]">
                    <DescriptionList :items="sensorInfo">
                        <template #value-device>
                            <Link v-if="sensor.device" :href="route('devices.show', sensor.device.id)" class="text-info hover:text-foreground">{{
                                sensor.device.uuid
                            }}</Link>
                            <template v-else>–</template>
                        </template>
                        <template #value-farm>
                            <Link v-if="sensor.farm" :href="route('farms.show', sensor.farm.id)" class="text-info hover:text-foreground">{{
                                sensor.farm.name
                            }}</Link>
                            <template v-else>–</template>
                        </template>
                    </DescriptionList>
                </SectionCard>
            </div>

            <SectionCard v-if="readingRows.length" title="Recent readings">
                <DataTable :columns="readingColumns" :rows="readingRows" row-key="time" min-width="360px" />
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
