<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import FilterChips, { type FilterChipOption } from '@/components/agronos/FilterChips.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTimestamp } from '@/composables/useTimestamp';
import AppLayout from '@/layouts/AppLayout.vue';
import { deviceTypeLabel, formatBattery, formatSignal, isLowBattery } from '@/lib/format';
import { type BreadcrumbItem, type DeviceSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type DeviceRow = DeviceSummary & { uuid: string; farms: string[] };
type StatusFilter = 'all' | DeviceSummary['status'];

const props = defineProps<{
    devices: DeviceRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Devices', href: '/devices' }];

const { formatAge } = useTimestamp();

const status = ref<StatusFilter>('all');
const search = ref('');
const farm = ref('all');
const link = ref('all');

const statusOptions = computed<FilterChipOption<StatusFilter>[]>(() => [
    { value: 'all', label: 'All', count: props.devices.length },
    ...(['online', 'registered', 'offline', 'error'] as const).map((value) => ({
        value,
        label: value.charAt(0).toUpperCase() + value.slice(1),
        count: props.devices.filter((device) => device.status === value).length,
    })),
]);

const farmOptions = computed(() => [...new Set(props.devices.flatMap((device) => device.farms))].sort());
const linkOptions = computed(() => [...new Set(props.devices.map((device) => device.type))].sort());

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Device', width: '1.8fr' },
    { key: 'farm', label: 'Farm', width: '1.4fr' },
    { key: 'link', label: 'Link', mono: true, width: '0.8fr' },
    { key: 'signal', label: 'Signal', mono: true, width: '0.9fr' },
    { key: 'battery', label: 'Battery', mono: true },
    { key: 'seen', label: 'Last seen', mono: true, width: '0.9fr' },
    { key: 'status', label: 'Status' },
];

const rows = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.devices
        .filter((device) => status.value === 'all' || device.status === status.value)
        .filter((device) => farm.value === 'all' || device.farms.includes(farm.value))
        .filter((device) => link.value === 'all' || device.type === link.value)
        .filter((device) => query === '' || `${device.name} ${device.uuid}`.toLowerCase().includes(query))
        .map((device) => ({
            ...device,
            farm: device.farms.length === 0 ? '–' : device.farms.join(', '),
            link: deviceTypeLabel(device.type),
            signal: formatSignal(device.signal_strength),
            battery: formatBattery(device.battery_level),
            seen: formatAge(device.last_seen_at),
        }));
});
</script>

<template>
    <Head title="Devices" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 md:p-8">
            <PageHeader eyebrow="Manage" title="Devices">
                <template #actions>
                    <Button as-child>
                        <Link :href="route('devices.create')">Add device</Link>
                    </Button>
                </template>
            </PageHeader>

            <template v-if="devices.length > 0">
                <section aria-label="Filters" class="flex flex-col gap-4">
                    <FilterChips v-model="status" :options="statusOptions" label="Filter by status" />
                    <div class="flex flex-wrap items-center gap-3">
                        <Input
                            v-model="search"
                            type="search"
                            aria-label="Search devices"
                            placeholder="Search devices"
                            class="min-w-0 flex-[1_1_240px]"
                        />
                        <label class="inline-flex items-center gap-2.5 text-sm text-muted-foreground">
                            Farm
                            <select v-model="farm" class="h-11 rounded-md border border-input bg-card px-3 text-[15px] text-foreground">
                                <option value="all">All farms</option>
                                <option v-for="name in farmOptions" :key="name" :value="name">{{ name }}</option>
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-2.5 text-sm text-muted-foreground">
                            Link
                            <select v-model="link" class="h-11 rounded-md border border-input bg-card px-3 text-[15px] text-foreground">
                                <option value="all">All links</option>
                                <option v-for="type in linkOptions" :key="type" :value="type">{{ deviceTypeLabel(type) }}</option>
                            </select>
                        </label>
                    </div>
                </section>

                <SectionCard>
                    <div class="font-mono text-[13px] text-muted-foreground" aria-live="polite">
                        Showing {{ rows.length }} of {{ devices.length }} devices
                    </div>
                    <DataTable :columns="columns" :rows="rows" row-key="id" min-width="860px" empty-text="No devices match these filters.">
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
            </template>

            <div v-else class="flex flex-col items-center gap-3 rounded-xl border border-dashed bg-card p-8 text-center">
                <h2 class="m-0 text-lg font-semibold">No devices yet</h2>
                <p class="m-0 text-[15px] text-muted-foreground">Register a device to start receiving readings.</p>
                <Button as-child>
                    <Link :href="route('devices.create')">Add your first device</Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
