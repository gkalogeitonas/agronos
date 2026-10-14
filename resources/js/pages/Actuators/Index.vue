<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { sampleActuators } from '@/lib/sampleData';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Actuators', href: '/actuators' }];

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Actuator', width: '1.6fr' },
    { key: 'farm', label: 'Farm', width: '1.4fr' },
    { key: 'kind', label: 'Kind', width: '1.3fr' },
    { key: 'lastRun', label: 'Last run', mono: true, width: '0.9fr' },
    { key: 'state', label: 'State', width: '0.9fr' },
    { key: 'actions', label: '', width: '1fr', align: 'end' },
];

const comingSoon = 'Coming soon: actuator control is not available yet';
</script>

<template>
    <Head title="Actuators" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Proposed" title="Actuators" sample-data>
                <template #actions>
                    <Button disabled :title="comingSoon">Add actuator</Button>
                </template>
            </PageHeader>

            <p class="m-0 max-w-3xl text-body text-muted-foreground">
                Actuators such as irrigation valves and vent motors will be controlled from here, manually or by schedule. This feature is proposed
                and the rows below are examples.
            </p>

            <SectionCard>
                <DataTable :columns="columns" :rows="sampleActuators" row-key="id" min-width="760px">
                    <template #cell-name="{ row }">
                        <span class="font-semibold">{{ row.name }}</span>
                    </template>
                    <template #cell-state="{ row }">
                        <StatusBadge :status="row.state" :tone="row.state === 'Open' ? 'info' : 'neutral'" />
                    </template>
                    <template #cell-actions>
                        <Button variant="outline" size="sm" disabled :title="comingSoon">Run</Button>
                    </template>
                </DataTable>
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
