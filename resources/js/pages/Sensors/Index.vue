<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import EmptyState from '@/components/agronos/EmptyState.vue';
import FilterChips, { type FilterChipOption } from '@/components/agronos/FilterChips.vue';
import FilterSelect from '@/components/agronos/FilterSelect.vue';
import ListPagination from '@/components/agronos/ListPagination.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useLiveSensorReadings } from '@/composables/useLiveSensorReadings';
import { useTimestamp } from '@/composables/useTimestamp';
import AppLayout from '@/layouts/AppLayout.vue';
import { deviceStatusOptions, sensorTypeLabel, sensorUnit } from '@/lib/enums';
import { formatReading } from '@/lib/format';
import { type BreadcrumbItem, type FarmOption, type Pagination } from '@/types';
import type { Sensor } from '@/types/sensor';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type GroupBy = 'farm' | 'type';

/** Filters applied on the server; `farm` is a farm id, `unassigned` or `all`, as in `?farm=<id>` from the farm page. */
interface SensorFilters {
    type: string;
    farm: string;
    status: string;
    group: GroupBy;
}

const props = defineProps<{
    /** One page of sensors, ordered by group and then name. */
    sensors: Sensor[];
    pagination: Pagination;
    filters: SensorFilters;
    /** Filtered sensors in each whole group, keyed by farm id (or `unassigned`) or by type. */
    groupCounts: Record<string, number>;
    /** Sensors per type across the tenant, with `all` for the total. */
    typeCounts: Record<string, number>;
    farms: FarmOption[];
    hasUnassigned: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sensors', href: route('sensors.index') }];

const { formatAge } = useTimestamp();
/** Subscribes to the sensors on this page only; each filter or page change remounts the page with its own sensors. */
const liveReadings = useLiveSensorReadings(props.sensors);

const type = ref(props.filters.type);
const farmId = ref(props.filters.farm);
const status = ref(props.filters.status);
const groupBy = ref<GroupBy>(props.filters.group);

/** Filter value for sensors whose device is on no farm. */
const UNASSIGNED = 'unassigned';

const sensorFarmId = (sensor: Sensor): number | null => sensor.device?.farm_id ?? null;
const farmName = (id: number | null): string => props.farms.find((farm) => farm.id === id)?.name ?? 'Unassigned';
const sensorStatus = (sensor: Sensor): string => sensor.device?.status ?? 'unknown';

const typeOptions = computed<FilterChipOption[]>(() => [
    { value: 'all', label: 'All', count: props.typeCounts.all ?? 0 },
    ...Object.entries(props.typeCounts)
        .filter(([value]) => value !== 'all')
        .map(([value, count]) => ({ value, label: sensorTypeLabel(value), count })),
]);

const groupOptions: FilterChipOption<GroupBy>[] = [
    { value: 'farm', label: 'Farm' },
    { value: 'type', label: 'Type' },
];

/** Splits the page's rows into consecutive groups; the server orders sensors by group, so a group may continue on the next page. */
const groups = computed(() => {
    const keyOf = (sensor: Sensor): string => (groupBy.value === 'farm' ? String(sensorFarmId(sensor) ?? UNASSIGNED) : sensor.type);
    const titleOf = (sensor: Sensor): string => (groupBy.value === 'farm' ? farmName(sensorFarmId(sensor)) : sensorTypeLabel(sensor.type));
    const result: { key: string; title: string; sensors: Sensor[] }[] = [];

    props.sensors.forEach((sensor) => {
        const key = keyOf(sensor);
        if (result.at(-1)?.key !== key) {
            result.push({ key, title: titleOf(sensor), sensors: [] });
        }
        result.at(-1)!.sensors.push(sensor);
    });

    return result.map(({ key, title, sensors }) => {
        const total = props.groupCounts[key] ?? sensors.length;
        return {
            key,
            title,
            count: total === 1 ? '1 sensor' : `${total} sensors`,
            rows: sensors.map((sensor) => ({
                id: sensor.id,
                name: sensor.name,
                context: groupBy.value === 'farm' ? sensorTypeLabel(sensor.type) : farmName(sensorFarmId(sensor)),
                latest: formatReading(liveReadings[sensor.id]?.value, sensorUnit(sensor.type)),
                seen: formatAge(liveReadings[sensor.id]?.time),
                status: sensorStatus(sensor),
            })),
        };
    });
});

const columns = computed<DataTableColumn[]>(() => [
    { key: 'name', label: 'Sensor', width: '2fr' },
    { key: 'context', label: groupBy.value === 'farm' ? 'Type' : 'Farm', width: '1.4fr' },
    { key: 'latest', label: 'Latest', mono: true },
    { key: 'seen', label: 'Last seen', mono: true },
    { key: 'status', label: 'Status' },
]);

/** Reloads the list from the server with the current filters, back on its first page. */
watch([type, farmId, status, groupBy], () => {
    const query = { type: type.value, farm: farmId.value, status: status.value };
    const params = {
        ...Object.fromEntries(Object.entries(query).filter(([, value]) => value !== 'all')),
        ...(groupBy.value === 'type' ? { group: 'type' } : {}),
    };

    router.get(route('sensors.index'), params, { preserveScroll: true, replace: true });
});
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

            <template v-if="(typeCounts.all ?? 0) > 0">
                <section aria-label="Filters" class="flex flex-col gap-4">
                    <FilterChips v-model="type" :options="typeOptions" label="Filter by sensor type" />
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <FilterSelect id="farm-filter" v-model="farmId" label="Farm">
                            <option value="all">All farms</option>
                            <option v-for="farm in farms" :key="farm.id" :value="String(farm.id)">{{ farm.name }}</option>
                            <option v-if="hasUnassigned" :value="UNASSIGNED">Unassigned</option>
                        </FilterSelect>
                        <FilterSelect v-model="status" label="Status">
                            <option value="all">All statuses</option>
                            <option v-for="option in deviceStatusOptions()" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </FilterSelect>
                        <div class="inline-flex items-center gap-2.5 text-sm text-muted-foreground">
                            Group by
                            <FilterChips v-model="groupBy" :options="groupOptions" label="Group sensors by" />
                        </div>
                    </div>
                </section>

                <div v-if="pagination.total === 0" class="rounded-xl border bg-card p-8 text-center text-body text-muted-foreground">
                    No sensors match these filters.
                </div>

                <ListPagination v-else :pagination="pagination" noun="sensors" />

                <SectionCard v-for="group in groups" :key="group.key" :title="group.title">
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

            <EmptyState v-else title="No sensors yet" text="Add a sensor to a device to start collecting readings."
                ><Button as-child>
                    <Link :href="route('sensors.create')">Add your first sensor</Link>
                </Button></EmptyState
            >
        </PageContainer>
    </AppLayout>
</template>
