<script setup lang="ts">
import ActivityFeed from '@/components/agronos/ActivityFeed.vue';
import AlertList from '@/components/agronos/AlertList.vue';
import EmptyState from '@/components/agronos/EmptyState.vue';
import FarmCard from '@/components/agronos/FarmCard.vue';
import FarmsOverviewMap from '@/components/agronos/FarmsOverviewMap.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatTile from '@/components/agronos/StatTile.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { sampleActivity, sampleAlerts, sampleZoneCount } from '@/lib/sampleData';
import { type BreadcrumbItem, type FarmSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Fleet {
    farms: number;
    devicesTotal: number;
    devicesOnline: number;
    lowBattery: number;
}

const props = defineProps<{
    fleet: Fleet;
    farms: FarmSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Overview', href: route('dashboard') }];

const farmsNeedingAttention = computed(() => props.farms.filter((farm) => farm.health === 'warning' || farm.health === 'offline').length);
</script>

<template>
    <Head title="Overview" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Organisation overview" title="All farms" sample-data>
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="route('devices.create')">Add device</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('farms.create')">Add farm</Link>
                    </Button>
                </template>
            </PageHeader>

            <section aria-label="Fleet health" class="flex flex-wrap gap-4">
                <StatTile
                    label="Farms"
                    :value="fleet.farms"
                    :note="farmsNeedingAttention === 0 ? 'All healthy' : `${farmsNeedingAttention} need attention`"
                />
                <StatTile
                    label="Devices online"
                    :value="fleet.devicesOnline"
                    :unit="`/ ${fleet.devicesTotal}`"
                    :note="`${fleet.devicesTotal - fleet.devicesOnline} not online`"
                />
                <StatTile label="Open alerts" :value="sampleAlerts.length" note="Sample data" />
                <StatTile label="Low battery" :value="fleet.lowBattery" unit="devices" note="Below 20%" />
            </section>

            <div class="flex flex-wrap items-stretch gap-4">
                <SectionCard title="Farm map" class="flex-[2_1_480px]">
                    <FarmsOverviewMap :farms="farms" />
                </SectionCard>

                <SectionCard title="Needs attention" class="flex-[1_1_280px]">
                    <template #actions>
                        <Link :href="route('alerts.index')" class="text-sm font-medium text-info hover:text-foreground">View all</Link>
                    </template>
                    <AlertList :alerts="sampleAlerts" />
                </SectionCard>
            </div>

            <section aria-label="Farms" class="flex flex-col gap-3.5">
                <h2 class="m-0 text-lg font-semibold">Your farms</h2>
                <div v-if="farms.length > 0" class="flex flex-wrap gap-4">
                    <FarmCard v-for="farm in farms" :key="farm.id" :farm="farm" :zone-count="sampleZoneCount" />
                </div>
                <EmptyState v-else text="You have no farms yet."
                    ><Button as-child>
                        <Link :href="route('farms.create')">Add your first farm</Link>
                    </Button></EmptyState
                >
            </section>

            <SectionCard title="Recent activity">
                <ActivityFeed :items="sampleActivity" />
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
