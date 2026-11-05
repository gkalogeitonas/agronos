<script setup lang="ts">
import AlertList, { type AlertItem } from '@/components/agronos/AlertList.vue';
import FilterChips, { type FilterChipOption } from '@/components/agronos/FilterChips.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type SeverityFilter = 'all' | AlertItem['severity'];

const props = defineProps<{
    alerts: AlertItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Alerts', href: route('alerts.index') }];

const severity = ref<SeverityFilter>('all');

const severityOptions = computed<FilterChipOption<SeverityFilter>[]>(() => [
    { value: 'all', label: 'All', count: props.alerts.length },
    { value: 'danger', label: 'Critical', count: props.alerts.filter((alert) => alert.severity === 'danger').length },
    { value: 'warning', label: 'Warning', count: props.alerts.filter((alert) => alert.severity === 'warning').length },
    { value: 'info', label: 'Info', count: props.alerts.filter((alert) => alert.severity === 'info').length },
]);

const visibleAlerts = computed(() => props.alerts.filter((alert) => severity.value === 'all' || alert.severity === severity.value));
</script>

<template>
    <Head title="Alerts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Monitor" title="Alerts" />

            <p class="m-0 max-w-3xl text-body text-muted-foreground">
                Alerts open automatically when a device stops reporting, and close on their own once the problem clears.
            </p>

            <FilterChips v-model="severity" :options="severityOptions" label="Filter by severity" />

            <SectionCard title="Open alerts">
                <AlertList :alerts="visibleAlerts" />
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
