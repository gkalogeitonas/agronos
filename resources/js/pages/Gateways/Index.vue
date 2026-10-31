<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/agronos/DataTable.vue';
import EmptyState from '@/components/agronos/EmptyState.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useTimestamp } from '@/composables/useTimestamp';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Gateway } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    gateways: Gateway[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Gateways', href: route('gateways.index') }];
const { formatTimestamp } = useTimestamp();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Name', width: 'minmax(0,1fr)' },
    { key: 'mqtt_username', label: 'MQTT username', width: 'minmax(0,2fr)', mono: true },
    { key: 'created', label: 'Created', width: '120px', mono: true, align: 'end' },
];

const rows = computed(() =>
    props.gateways.map((gateway) => ({
        id: gateway.id,
        name: gateway.name,
        mqtt_username: gateway.mqtt_username,
        created: formatTimestamp(gateway.created_at).slice(0, 10),
    })),
);
</script>

<template>
    <Head title="Gateways" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Manage" title="Gateways">
                <template #actions>
                    <Button as-child>
                        <Link :href="route('gateways.create')">Add gateway</Link>
                    </Button>
                </template>
            </PageHeader>

            <SectionCard v-if="gateways.length > 0">
                <DataTable :columns="columns" :rows="rows" row-key="id">
                    <template #cell-name="{ row }">
                        <Link :href="route('gateways.show', row.id)" class="font-medium hover:text-info">{{ row.name }}</Link>
                    </template>
                    <template #cell-mqtt_username="{ value }"
                        ><span class="block truncate">{{ value }}</span></template
                    >
                </DataTable>
            </SectionCard>

            <EmptyState v-else title="No gateways yet" text="Add a LoRa gateway to get the MQTT credentials for its web config."
                ><Button as-child>
                    <Link :href="route('gateways.create')">Add your first gateway</Link>
                </Button></EmptyState
            >
        </PageContainer>
    </AppLayout>
</template>
