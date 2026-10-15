<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import EmptyState from '@/components/agronos/EmptyState.vue';
import FilterSelect from '@/components/agronos/FilterSelect.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { sampleOpenAlertsForFarm, sampleZoneCount } from '@/lib/sampleData';
import { type BreadcrumbItem, type FarmSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    farms: FarmSummary[];
    farmHealthOptions: { value: FarmSummary['health']; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Farms', href: route('farms.index') }];

const search = ref('');
const health = ref<FarmSummary['health'] | 'all'>('all');

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Farm', width: '2.2fr' },
    { key: 'health', label: 'Health', width: '1fr' },
    { key: 'zones', label: 'Zones', width: '0.8fr', mono: true },
    { key: 'devices', label: 'Devices', width: '1fr', mono: true },
    { key: 'alerts', label: 'Open alerts', width: '1.1fr', mono: true },
    { key: 'actions', label: '', width: '1.2fr', align: 'end' },
];

const rows = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.farms
        .filter((farm) => health.value === 'all' || farm.health === health.value)
        .filter((farm) => query === '' || `${farm.name} ${farm.location ?? ''}`.toLowerCase().includes(query))
        .map((farm) => ({
            ...farm,
            zones: sampleZoneCount,
            devices: `${farm.devicesOnline} / ${farm.devicesTotal}`,
            alerts: sampleOpenAlertsForFarm(farm.id),
            actions: null,
        }));
});
</script>

<template>
    <Head title="Farms" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Manage" title="Farms" sample-data>
                <template #actions>
                    <Button as-child>
                        <Link :href="route('farms.create')">Add farm</Link>
                    </Button>
                </template>
            </PageHeader>

            <SectionCard v-if="farms.length > 0" class="gap-5">
                <div class="flex flex-wrap items-center gap-3">
                    <Input v-model="search" type="search" aria-label="Search farms" placeholder="Search farms" class="min-w-0 flex-[1_1_240px]" />
                    <FilterSelect v-model="health" label="Health">
                        <option value="all">All</option>
                        <option v-for="option in farmHealthOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </FilterSelect>
                    <span class="font-mono text-meta text-muted-foreground">{{ rows.length }} of {{ farms.length }} farms</span>
                </div>

                <DataTable :columns="columns" :rows="rows" row-key="id" min-width="760px" empty-text="No farms match these filters.">
                    <template #cell-name="{ row }">
                        <span class="flex flex-col gap-0.5">
                            <Link :href="route('farms.show', row.id)" class="font-semibold text-foreground hover:text-info">{{ row.name }}</Link>
                            <span class="font-mono text-xs text-muted-foreground">{{ row.location ?? 'No location' }}</span>
                        </span>
                    </template>
                    <template #cell-health="{ row }">
                        <StatusBadge :status="row.healthLabel" />
                    </template>
                    <template #cell-actions="{ row }">
                        <span class="flex items-center gap-4">
                            <Link
                                :href="route('farms.edit', row.id)"
                                class="inline-flex min-h-11 items-center text-body font-medium text-muted-foreground hover:text-foreground"
                            >
                                Edit
                            </Link>
                            <Link
                                :href="route('farms.show', row.id)"
                                class="inline-flex min-h-11 items-center text-body font-semibold text-info hover:text-foreground"
                            >
                                Open farm
                            </Link>
                        </span>
                    </template>
                </DataTable>
            </SectionCard>

            <EmptyState v-else title="No farms yet" text="Add your first farm to start placing sensors."
                ><Button as-child>
                    <Link :href="route('farms.create')">Add your first farm</Link>
                </Button></EmptyState
            >
        </PageContainer>
    </AppLayout>
</template>
