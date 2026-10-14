<template>
    <Head :title="sensor.name ? sensor.name : 'Sensor'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 md:p-8">
            <PageHeader :eyebrow="`${sensorTypeLabel(sensor.type)} sensor`" :title="sensor.name || 'Unnamed sensor'">
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="route('sensors.edit', sensor.id)"><Pencil /> Edit</Link>
                    </Button>
                    <Button variant="outline" class="text-destructive hover:text-destructive" @click="deleteSensor"><Trash2 /> Delete</Button>
                </template>
            </PageHeader>

            <section aria-label="Readings" class="flex flex-wrap gap-4">
                <StatTile
                    label="Latest"
                    :value="formatReading(sensor.last_reading, '', 2)"
                    :unit="unit"
                    :note="sensor.last_reading_at ? formatTimestamp(sensor.last_reading_at) : 'No readings yet'"
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
                    <label class="inline-flex items-center gap-2.5 text-sm text-muted-foreground">
                        Range
                        <NativeSelect v-model="selectedRange" @change="applyRange">
                            <option v-for="opt in rangeOptions ?? []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </NativeSelect>
                    </label>
                </template>
                <div v-if="chartData === undefined" class="h-[320px] animate-pulse rounded-md bg-muted" aria-label="Loading chart"></div>
                <LineChart
                    v-else-if="series[0].data.length > 1"
                    :series="series"
                    :unit="unit"
                    :height="320"
                    :label="`Line chart of ${sensorTypeLabel(sensor.type).toLowerCase()} readings, ${rangeLabel.toLowerCase()}`"
                />
                <div v-else class="flex h-[200px] items-center justify-center rounded-md bg-muted/60 text-[15px] text-muted-foreground">
                    No readings in the selected range.
                </div>
            </SectionCard>

            <div class="flex flex-wrap items-stretch gap-4">
                <SectionCard v-if="sensor.lat && sensor.lon" title="Location" class="flex-[2_1_480px]">
                    <FarmMapbox
                        :center="[parseFloat(sensor.lon), parseFloat(sensor.lat)]"
                        :farm-polygon="sensor.farm?.coordinates"
                        :sensors="[{ lat: parseFloat(sensor.lat), lon: parseFloat(sensor.lon), name: sensor.name }]"
                        :zoom="15"
                    />
                </SectionCard>

                <SectionCard title="Sensor information" class="flex-[1_1_280px]">
                    <dl class="m-0 flex flex-col">
                        <div class="flex justify-between gap-4 border-b border-row py-3">
                            <dt class="text-sm text-muted-foreground">Sensor UUID</dt>
                            <dd class="m-0 min-w-0 truncate font-mono text-sm" :title="sensor.uuid">{{ sensor.uuid }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-row py-3">
                            <dt class="text-sm text-muted-foreground">Device</dt>
                            <dd class="m-0 min-w-0 truncate font-mono text-sm">
                                <Link v-if="sensor.device" :href="route('devices.show', sensor.device.id)" class="text-info hover:text-foreground">{{
                                    sensor.device.uuid
                                }}</Link>
                                <template v-else>–</template>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-row py-3">
                            <dt class="text-sm text-muted-foreground">Type</dt>
                            <dd class="m-0 text-[15px]">{{ sensorTypeLabel(sensor.type) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-row py-3">
                            <dt class="text-sm text-muted-foreground">Farm</dt>
                            <dd class="m-0 text-[15px]">
                                <Link v-if="sensor.farm" :href="route('farms.show', sensor.farm.id)" class="text-info hover:text-foreground">{{
                                    sensor.farm.name
                                }}</Link>
                                <template v-else>–</template>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-3">
                            <dt class="text-sm text-muted-foreground">Coordinates</dt>
                            <dd class="m-0 font-mono text-sm">{{ sensor.lat ?? '–' }}, {{ sensor.lon ?? '–' }}</dd>
                        </div>
                    </dl>
                </SectionCard>
            </div>

            <SectionCard v-if="readingRows.length" title="Recent readings">
                <DataTable :columns="readingColumns" :rows="readingRows" row-key="time" min-width="360px" />
            </SectionCard>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import LineChart from '@/components/agronos/LineChart.vue';
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatTile from '@/components/agronos/StatTile.vue';
import FarmMapbox from '@/components/FarmMapbox.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import useTimestamp from '@/composables/useTimestamp';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatReading } from '@/lib/format';
import { sensorColors, sensorTypeLabel, signalColors } from '@/lib/theme';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    sensor: any;
    recentReadings?: Array<{ time: string; value: number }>;
    stats?: { min: number | null; max: number | null; avg: number | null; count?: number };
    // chartData may be provided as an array of [ms, value] pairs from the backend
    chartData?: Array<any>;
    rangeOptions?: Array<{ value: string; label: string }>;
    selectedRange?: string;
}>();

// local reactive copies so we can mutate on realtime events
const sensor = ref(props.sensor);
// keep a local mutable copy for realtime updates, but sync when the deferred prop resolves
const recentReadings = ref(props.recentReadings ?? []);
watch(
    () => props.recentReadings,
    (val) => {
        recentReadings.value = val ?? [];
    },
    { immediate: true },
);

// use a computed directly from props so Deferred updates are reflected automatically
const stats = computed(() => props.stats ?? { min: null, max: null, avg: null, count: 0 });

const { formatTimestamp } = useTimestamp();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sensors', href: '/sensors' },
    { title: sensor.value?.name || 'Sensor', href: route('sensors.show', sensor.value.id) },
];

const unit = computed<string>(() => sensor.value.unit ?? '');
const rangeLabel = computed(() => props.rangeOptions?.find((option) => option.value === selectedRange.value)?.label ?? 'Selected range');

/** Chart data comes from InfluxDB as [ms, value] pairs. */
const series = computed(() => {
    const points = Array.isArray(props.chartData) ? (props.chartData as Array<[number, number]>) : [];
    return [
        {
            name: sensorTypeLabel(props.sensor.type),
            color: sensorColors[props.sensor.type] ?? signalColors.ink,
            data: points.map(([x, y]) => ({ x, y })),
        },
    ];
});

const readingColumns: DataTableColumn[] = [
    { key: 'time', label: 'Time', mono: true, width: '2fr' },
    { key: 'value', label: 'Value', mono: true },
];

const readingRows = computed(() =>
    recentReadings.value.map((row) => ({ time: formatTimestamp(row.time), value: formatReading(row.value, unit.value, 2) })),
);

// Selected range — initialize from server-provided selectedRange or URL param
const urlRange = new URL(window.location.href).searchParams.get('range');
const selectedRange = ref<string>(props.selectedRange ?? urlRange ?? (props.rangeOptions && props.rangeOptions[1]?.value) ?? '-24h');

function applyRange() {
    // Use Inertia to visit the same sensor show route with the new range param.
    // This performs an XHR and replaces Inertia props without a full browser reload.
    router.get(route('sensors.show', sensor.value.id), { range: selectedRange.value }, { replace: true, preserveScroll: true });
}

function deleteSensor() {
    if (confirm(`Are you sure you want to delete ${sensor.value.name || 'this sensor'}?`)) {
        router.delete(route('sensors.destroy', sensor.value.id));
    }
}

// Subscribe to private sensor channel (only authorized users can listen)
useEcho(`sensor.${sensor.value.id}`, 'SensorReadingEvent', (payload: any) => {
    try {
        if (payload.time && payload.value !== undefined) {
            const arr = recentReadings.value.slice();
            arr.unshift({ time: payload.time, value: payload.value });
            if (arr.length > 50) arr.pop();
            recentReadings.value = arr;

            // update local sensor latest reading
            sensor.value = sensor.value || {};
            sensor.value.last_reading = payload.value;
            sensor.value.last_reading_at = payload.time;

            // push to chart data (live update)
            //try { pushToChartData(payload.time, payload.value); } catch { /* ignore */ }
        }
    } catch {
        // ignore
    }
});
</script>
