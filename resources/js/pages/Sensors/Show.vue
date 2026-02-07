<template>

    <Head :title="sensor.name ? sensor.name : 'Sensor Details'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container py-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold pl-3 flex items-center gap-2">
                    <span v-if="sensor.name">{{ sensor.name }}</span>
                    <span v-else class="text-muted-foreground">Unnamed Sensor</span>
                    <span class="ml-2 px-2 py-1 rounded bg-muted text-xs">{{ sensor.type }}</span>
                </h1>
                <div class="space-x-2 flex items-center">
                    <Link :href="route('sensors.edit', sensor.id)">
                    <Button variant="outline" size="sm" class="flex flex-row items-center border p-2">
                        <Pencil class="h-4 w-4 mr-2" />
                        <span>Edit</span>
                    </Button>
                    </Link>
                    <Button variant="destructive" size="sm" class="flex flex-row items-center" @click="deleteSensor">
                        <Trash2 class="h-4 w-4 mr-2" />
                        <span>Delete</span>
                    </Button>
                </div>
            </div>
            <div v-if="sensor.lat && sensor.lon" class="mb-6">
                <FarmMapbox :center="[parseFloat(sensor.lon), parseFloat(sensor.lat)]"
                    :farmPolygon="sensor.farm?.coordinates"
                    :sensors="[{ lat: parseFloat(sensor.lat), lon: parseFloat(sensor.lon), name: sensor.name }]"
                    :zoom="15" />
            </div>
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Sensor Details</CardTitle>
                    <CardDescription>Information about this sensor</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-sm font-medium text-muted-foreground">Sensor UUID</h3>
                            <p class="font-mono break-all">{{ sensor.uuid }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-muted-foreground">Device UUID</h3>
                            <p class="font-mono break-all"><Link :href="route('devices.show', sensor.device?.id)">{{ sensor.device?.uuid || '—' }}</Link></p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-muted-foreground">Sensor type</h3>
                            <p class="font-mono break-all">{{ sensor.type || '—' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-muted-foreground">Farm</h3>
                            <p>
                                <template v-if="sensor.farm">
                                    <Link :href="route('farms.show', sensor.farm.id)">{{ sensor.farm.name }}</Link>
                                </template>
                                <template v-else>
                                    —
                                </template>
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-muted-foreground">Location</h3>
                            <p>Lat: {{ sensor.lat ?? '—' }}, Lon: {{ sensor.lon ?? '—' }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Time Range</CardTitle>
                    <CardDescription>Choose the time range for chart and statistics</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-3">
                        <label class="text-sm text-muted-foreground">Range:</label>
                        <select v-model="selectedRange" @change="applyRange"
                            class="border rounded px-2 py-1 text-sm">
                            <option v-for="opt in rangeOptions ?? []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Latest Reading</CardTitle>
                        <CardDescription>Most recent data from this sensor</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div>
                                <h3 class="text-sm font-medium text-muted-foreground">Value</h3>
                                <p class="text-2xl font-bold">
                                    {{ sensor.last_reading ?? '—' }}
                                    <span v-if="sensor.unit" class="text-base font-normal text-muted-foreground">{{
                                        sensor.unit
                                        }}</span>
                                </p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-muted-foreground">Last Seen</h3>
                                <p class="text-sm">{{ formatTimestamp(sensor.last_reading_at) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Deferred data="stats">
                    <template #fallback>
                        <div class="flex justify-center items-center text-sm text-muted-foreground h-24">
                            <span>Loading statistics...</span>
                        </div>
                    </template>
                    <Card>
                        <CardHeader>
                            <CardTitle>24h Statistics</CardTitle>
                            <CardDescription>Min/Max/Average</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <h3 class="text-sm font-medium text-muted-foreground">Min</h3>
                                    <p class="text-lg font-semibold">
                                        {{ stats?.min ?? '—' }}
                                        <span v-if="sensor.unit" class="text-base font-normal text-muted-foreground">{{
                                            sensor.unit
                                            }}</span>
                                    </p>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-muted-foreground">Max</h3>
                                    <p class="text-lg font-semibold">
                                        {{ stats?.max ?? '—' }}
                                        <span v-if="sensor.unit" class="text-base font-normal text-muted-foreground">{{
                                            sensor.unit
                                            }}</span>
                                    </p>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-muted-foreground">Avg</h3>
                                    <p class="text-lg font-semibold">
                                        {{ stats?.avg != null ? stats.avg.toFixed(2) : '—' }}
                                        <span v-if="sensor.unit" class="text-base font-normal text-muted-foreground">{{
                                            sensor.unit
                                            }}</span>
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Deferred>
            </div>

            <Deferred data="chartData">
                <template #fallback>
                    <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-center text-sm text-muted-foreground h-32">
                            <span>Loading chart...</span>
                        </div>
                    </div>
                </template>

                <template #default>
                    <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-100">
                        <h3 class="text-lg font-semibold mb-4">Ιστορικό Μετρήσεων</h3>

                        <template v-if="series && series.length">
                            <VueApexCharts
                                type="line"
                                height="350"
                                :options="chartOptions"
                                :series="series"
                            />
                        </template>

                        <template v-else>
                            <div class="flex items-center justify-center text-sm text-muted-foreground h-32">
                                <span>No chart data for the selected range.</span>
                            </div>
                        </template>
                    </div>
                </template>
            </Deferred>

            <Card class="mb-6" v-if="recentReadings && recentReadings.length">
                <CardHeader>
                    <CardTitle>Recent Readings</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="px-2 py-1 text-left">Timestamp</th>
                                    <th class="px-2 py-1 text-left">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in recentReadings" :key="row.time">
                                    <td class="px-2 py-1">{{ formatTimestamp(row.time) }}</td>
                                    <td class="px-2 py-1">
                                        {{ row.value }}
                                        <span v-if="sensor.unit" class="text-xs text-muted-foreground">{{ sensor.unit
                                            }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, Deferred } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useEcho, useEchoPublic } from '@laravel/echo-vue';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import FarmMapbox from '@/components/FarmMapbox.vue'
import useTimestamp from '@/composables/useTimestamp';
import VueApexCharts from "vue3-apexcharts";

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
watch(() => props.recentReadings, (val) => {
    recentReadings.value = val ?? [];
}, { immediate: true });

// use a computed directly from props so Deferred updates are reflected automatically
const stats = computed(() => props.stats ?? { min: null, max: null, avg: null, count: 0 });

const { formatTimestamp, parseAsUTCDate } = useTimestamp();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sensors', href: '/sensors' },
    { title: sensor.value?.name || 'Sensor Details', href: null },
];


// Ρυθμίσεις Γραφήματος
const chartOptions = {
  chart: {
    type: 'line',
    datetimeUTC: false,
    toolbar: { show: true },
    zoom: { enabled: true }
  },
  xaxis: {
    type: 'datetime', // native διαχείριση χρόνου
  },
  stroke: {
    curve: 'smooth', // Για "οργανική" εμφάνιση των αγροτικών μετρήσεων
    width: 3
  },
  colors: ['#4f46e5'], // Indigo χρώμα για επαγγελματική εμφάνιση
};

// Chart data comes directly from the Influx-driven `props.chartData`.
const series = computed(() => {
    const d = Array.isArray(props.chartData) ? (props.chartData as Array<[number, number]>) : [];
    return [{ name: props.sensor.type, data: d.slice() }];
});

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

<style scoped>
.text-muted-foreground {
    color: #6b7280;
}

.bg-muted {
    background: #f3f4f6;
}
</style>
