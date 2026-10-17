<script setup lang="ts">
import DeviceTable from '@/components/agronos/DeviceTable.vue';
import EmptyState from '@/components/agronos/EmptyState.vue';
import FilterChips, { type FilterChipOption } from '@/components/agronos/FilterChips.vue';
import FilterSelect from '@/components/agronos/FilterSelect.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { deviceStatusOptions } from '@/lib/enums';
import { deviceTypeLabel } from '@/lib/format';
import { type BreadcrumbItem, type Device } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type DeviceRow = Device & { farms: string[] };
type StatusFilter = 'all' | Device['status'];

const props = defineProps<{
    devices: DeviceRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Devices', href: route('devices.index') }];

const status = ref<StatusFilter>('all');
const search = ref('');
const farm = ref('all');
const link = ref('all');

const statusOptions = computed<FilterChipOption<StatusFilter>[]>(() => [
    { value: 'all', label: 'All', count: props.devices.length },
    ...deviceStatusOptions().map(({ value, label }) => ({
        value: value as Device['status'],
        label,
        count: props.devices.filter((device) => device.status === value).length,
    })),
]);

const farmOptions = computed(() => [...new Set(props.devices.flatMap((device) => device.farms))].sort());
const linkOptions = computed(() => [...new Set(props.devices.map((device) => device.type))].sort());

const filteredDevices = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.devices
        .filter((device) => status.value === 'all' || device.status === status.value)
        .filter((device) => farm.value === 'all' || device.farms.includes(farm.value))
        .filter((device) => link.value === 'all' || device.type === link.value)
        .filter((device) => query === '' || `${device.name} ${device.uuid}`.toLowerCase().includes(query));
});
</script>

<template>
    <Head title="Devices" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
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
                        <FilterSelect v-model="farm" label="Farm">
                            <option value="all">All farms</option>
                            <option v-for="name in farmOptions" :key="name" :value="name">{{ name }}</option>
                        </FilterSelect>
                        <FilterSelect v-model="link" label="Link">
                            <option value="all">All links</option>
                            <option v-for="type in linkOptions" :key="type" :value="type">{{ deviceTypeLabel(type) }}</option>
                        </FilterSelect>
                    </div>
                </section>

                <SectionCard>
                    <div class="font-mono text-meta text-muted-foreground" aria-live="polite">
                        Showing {{ filteredDevices.length }} of {{ devices.length }} devices
                    </div>
                    <DeviceTable :devices="filteredDevices" show-farm min-width="860px" empty-text="No devices match these filters." />
                </SectionCard>
            </template>

            <EmptyState v-else title="No devices yet" text="Register a device to start receiving readings."
                ><Button as-child>
                    <Link :href="route('devices.create')">Add your first device</Link>
                </Button></EmptyState
            >
        </PageContainer>
    </AppLayout>
</template>
