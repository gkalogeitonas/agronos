<script setup lang="ts">
import DeviceTable from '@/components/agronos/DeviceTable.vue';
import EmptyState from '@/components/agronos/EmptyState.vue';
import FilterChips, { type FilterChipOption } from '@/components/agronos/FilterChips.vue';
import FilterSelect from '@/components/agronos/FilterSelect.vue';
import ListPagination from '@/components/agronos/ListPagination.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { deviceStatusOptions } from '@/lib/enums';
import { deviceTypeLabel } from '@/lib/format';
import { type BreadcrumbItem, type Device, type FarmOption, type Pagination } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

type StatusFilter = 'all' | Device['status'];

/** Filters applied on the server; `farm` is a farm id, `unassigned` or `all`, as in `?farm=<id>` from the farm page. */
interface DeviceFilters {
    status: string;
    farm: string;
    link: string;
    search: string;
}

const props = defineProps<{
    devices: Device[];
    pagination: Pagination;
    filters: DeviceFilters;
    /** Devices per status across the tenant, with `all` for the total. */
    statusCounts: Record<string, number>;
    farms: FarmOption[];
    hasUnassigned: boolean;
    linkTypes: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Devices', href: route('devices.index') }];

const status = ref(props.filters.status as StatusFilter);
const search = ref(props.filters.search);
const farm = ref(props.filters.farm);
const link = ref(props.filters.link);

const statusOptions = computed<FilterChipOption<StatusFilter>[]>(() => [
    { value: 'all', label: 'All', count: props.statusCounts.all ?? 0 },
    ...deviceStatusOptions().map(({ value, label }) => ({
        value: value as Device['status'],
        label,
        count: props.statusCounts[value] ?? 0,
    })),
]);

/** Reloads the list from the server with the current filters, back on its first page. */
const applyFilters = () => {
    const query = { status: status.value, farm: farm.value, link: link.value, search: search.value.trim() };
    const params = Object.fromEntries(Object.entries(query).filter(([, value]) => value !== 'all' && value !== ''));

    router.get(route('devices.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};

watch([status, farm, link], applyFilters);
watchDebounced(search, applyFilters, { debounce: 300 });
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

            <template v-if="(statusCounts.all ?? 0) > 0">
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
                            <option v-for="option in farms" :key="option.id" :value="String(option.id)">{{ option.name }}</option>
                            <option v-if="hasUnassigned" value="unassigned">Unassigned</option>
                        </FilterSelect>
                        <FilterSelect v-model="link" label="Link">
                            <option value="all">All links</option>
                            <option v-for="type in linkTypes" :key="type" :value="type">{{ deviceTypeLabel(type) }}</option>
                        </FilterSelect>
                    </div>
                </section>

                <ListPagination v-if="pagination.total > 0" :pagination="pagination" noun="devices" />

                <SectionCard>
                    <DeviceTable :devices="devices" show-farm min-width="860px" empty-text="No devices match these filters." />
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
