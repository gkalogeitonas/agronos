<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import EmptyState from '@/components/agronos/EmptyState.vue';
import FilterChips, { type FilterChipOption } from '@/components/agronos/FilterChips.vue';
import FilterSelect from '@/components/agronos/FilterSelect.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useLiveSensorReadings } from '@/composables/useLiveSensorReadings';
import { useTimestamp } from '@/composables/useTimestamp';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatReading } from '@/lib/format';
import { sensorTypeLabel, sensorUnits } from '@/lib/theme';
import { type BreadcrumbItem } from '@/types';
import type { Sensor } from '@/types/sensor';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    sensors: Sensor[];
    farms: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sensors', href: route('sensors.index') }];

const { formatAge } = useTimestamp();
const liveReadings = useLiveSensorReadings(props.sensors);

const type = ref('all');
const farmId = ref('all');
const status = ref('all');
const groupBy = ref<'farm' | 'type'>('farm');

const farmName = (id: number | null): string => props.farms.find((farm) => farm.id === id)?.name ?? 'No farm';
const sensorStatus = (sensor: Sensor): string => sensor.device?.status ?? 'unknown';

const typeOptions = computed<FilterChipOption[]>(() => {
    const types = [...new Set(props.sensors.map((sensor) => sensor.type))].sort();
    return [
        { value: 'all', label: 'All', count: props.sensors.length },
        ...types.map((value) => ({ value, label: sensorTypeLabel(value), count: props.sensors.filter((sensor) => sensor.type === value).length })),
    ];
});

const groupOptions: FilterChipOption<'farm' | 'type'>[] = [
    { value: 'farm', label: 'Farm' },
    { value: 'type', label: 'Type' },
];

const filtered = computed(() =>
    props.sensors
        .filter((sensor) => type.value === 'all' || sensor.type === type.value)
        .filter((sensor) => farmId.value === 'all' || String(sensor.farm_id) === farmId.value)
        .filter((sensor) => status.value === 'all' || sensorStatus(sensor) === status.value),
);

const groups = computed(() => {
    const keyOf = (sensor: Sensor) => (groupBy.value === 'farm' ? farmName(sensor.farm_id) : sensorTypeLabel(sensor.type));
    const titles = [...new Set(filtered.value.map(keyOf))].sort();

    return titles.map((title) => {
        const rows = filtered.value
            .filter((sensor) => keyOf(sensor) === title)
            .map((sensor) => ({
                id: sensor.id,
                name: sensor.name,
                context: groupBy.value === 'farm' ? sensorTypeLabel(sensor.type) : farmName(sensor.farm_id),
                latest: formatReading(liveReadings[sensor.id]?.value, sensorUnits[sensor.type] ?? ''),
                seen: formatAge(liveReadings[sensor.id]?.time),
                status: sensorStatus(sensor),
            }));
        return { title, rows, count: rows.length === 1 ? '1 sensor' : `${rows.length} sensors` };
    });
});

const columns = computed<DataTableColumn[]>(() => [
    { key: 'name', label: 'Sensor', width: '2fr' },
    { key: 'context', label: groupBy.value === 'farm' ? 'Type' : 'Farm', width: '1.4fr' },
    { key: 'latest', label: 'Latest', mono: true },
    { key: 'seen', label: 'Last seen', mono: true },
    { key: 'status', label: 'Status' },
]);
</script>

<template>
    <Head title="Sensors" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Manage" title="Sensors">
                <template #actions>
                    <Button as-child>
                        <Link :href="route('sensors.create')">Add sensor</Link>
                    </Button>
                </template>
            </PageHeader>

            <template v-if="sensors.length > 0">
                <section aria-label="Filters" class="flex flex-col gap-4">
                    <FilterChips v-model="type" :options="typeOptions" label="Filter by sensor type" />
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <FilterSelect v-model="farmId" label="Farm">
                            <option value="all">All farms</option>
                            <option v-for="farm in farms" :key="farm.id" :value="String(farm.id)">{{ farm.name }}</option>
                        </FilterSelect>
                        <FilterSelect v-model="status" label="Status">
                            <option value="all">All statuses</option>
                            <option value="online">Online</option>
                            <option value="registered">Registered</option>
                            <option value="offline">Offline</option>
                            <option value="error">Error</option>
                        </FilterSelect>
                        <div class="inline-flex items-center gap-2.5 text-sm text-muted-foreground">
                            Group by
                            <FilterChips v-model="groupBy" :options="groupOptions" label="Group sensors by" />
                        </div>
                    </div>
                </section>

                <div v-if="groups.length === 0" class="rounded-xl border bg-card p-8 text-center text-body text-muted-foreground">
                    No sensors match these filters.
                </div>

                <SectionCard v-for="group in groups" :key="group.title" :title="group.title">
                    <template #title-extra>
                        <span class="font-mono text-meta text-muted-foreground">{{ group.count }}</span>
                    </template>
                    <DataTable :columns="columns" :rows="group.rows" row-key="id">
                        <template #cell-name="{ row }">
                            <Link :href="route('sensors.show', row.id)" class="font-semibold text-foreground hover:text-info">{{ row.name }}</Link>
                        </template>
                        <template #cell-status="{ row }">
                            <StatusBadge :status="row.status" />
                        </template>
                    </DataTable>
                </SectionCard>
            </template>

            <EmptyState v-else title="No sensors yet" text="Add a sensor to a device and a farm to start collecting readings."
                ><Button as-child>
                    <Link :href="route('sensors.create')">Add your first sensor</Link>
                </Button></EmptyState
            >
        </PageContainer>
    </AppLayout>
</template>
