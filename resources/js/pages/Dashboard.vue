<script setup lang="ts">
import ActivityFeed from '@/components/agronos/ActivityFeed.vue';
import AlertList from '@/components/agronos/AlertList.vue';
import FarmsOverviewMap from '@/components/agronos/FarmsOverviewMap.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatTile from '@/components/agronos/StatTile.vue';
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatReading } from '@/lib/format';
import { sampleActivity, sampleAlerts, sampleZoneCount } from '@/lib/sampleData';
import { farmStatusLabels } from '@/lib/theme';
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Overview', href: '/dashboard' }];

const farmsNeedingAttention = computed(() => props.farms.filter((farm) => farm.status === 'warning' || farm.status === 'offline').length);
</script>

<template>
    <Head title="Overview" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 md:p-8">
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
                        <Link href="/alerts" class="text-sm font-medium text-info hover:text-foreground">View all</Link>
                    </template>
                    <AlertList :alerts="sampleAlerts" />
                </SectionCard>
            </div>

            <section aria-label="Farms" class="flex flex-col gap-3.5">
                <h2 class="m-0 text-lg font-semibold">Your farms</h2>
                <div v-if="farms.length > 0" class="flex flex-wrap gap-4">
                    <article v-for="farm in farms" :key="farm.id" class="flex flex-[1_1_300px] flex-col gap-4 rounded-xl border bg-card p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <h3 class="m-0 truncate text-xl font-bold">{{ farm.name }}</h3>
                                <div class="font-mono text-xs text-muted-foreground">
                                    {{ farm.location ?? 'No location' }} · {{ sampleZoneCount }} zones
                                </div>
                            </div>
                            <StatusBadge :status="farmStatusLabels[farm.status]" />
                        </div>
                        <dl class="m-0 grid grid-cols-3 gap-3">
                            <div class="flex flex-col gap-0.5">
                                <dt class="font-mono text-[11px] tracking-[0.06em] text-muted-foreground uppercase">Moisture</dt>
                                <dd class="m-0 font-mono text-[22px] font-medium">{{ formatReading(farm.moisture, '%') }}</dd>
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <dt class="font-mono text-[11px] tracking-[0.06em] text-muted-foreground uppercase">Air temp</dt>
                                <dd class="m-0 font-mono text-[22px] font-medium">{{ formatReading(farm.temperature, '°C') }}</dd>
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <dt class="font-mono text-[11px] tracking-[0.06em] text-muted-foreground uppercase">Devices</dt>
                                <dd class="m-0 font-mono text-[22px] font-medium">{{ farm.devicesOnline }} / {{ farm.devicesTotal }}</dd>
                            </div>
                        </dl>
                        <div class="flex items-center justify-between gap-3 border-t border-row pt-3.5">
                            <span class="text-sm text-muted-foreground">{{ farm.sensors }} sensors</span>
                            <Link
                                :href="route('farms.show', farm.id)"
                                class="inline-flex min-h-11 items-center text-[15px] font-semibold text-info hover:text-foreground"
                            >
                                Open farm
                            </Link>
                        </div>
                    </article>
                </div>
                <div v-else class="flex flex-col items-center gap-3 rounded-xl border border-dashed bg-card p-8 text-center">
                    <p class="m-0 text-[15px] text-muted-foreground">You have no farms yet.</p>
                    <Button as-child>
                        <Link :href="route('farms.create')">Add your first farm</Link>
                    </Button>
                </div>
            </section>

            <SectionCard title="Recent activity">
                <ActivityFeed :items="sampleActivity" />
            </SectionCard>
        </div>
    </AppLayout>
</template>
