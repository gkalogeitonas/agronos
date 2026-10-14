<script setup lang="ts">
import AlertList, { type AlertItem } from '@/components/agronos/AlertList.vue';
import FilterChips, { type FilterChipOption } from '@/components/agronos/FilterChips.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { sampleAlerts } from '@/lib/sampleData';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type ToneFilter = 'all' | AlertItem['tone'];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Alerts', href: '/alerts' }];

const tone = ref<ToneFilter>('all');

const toneOptions = computed<FilterChipOption<ToneFilter>[]>(() => [
    { value: 'all', label: 'All', count: sampleAlerts.length },
    { value: 'danger', label: 'Critical', count: sampleAlerts.filter((alert) => alert.tone === 'danger').length },
    { value: 'warning', label: 'Warning', count: sampleAlerts.filter((alert) => alert.tone === 'warning').length },
    { value: 'info', label: 'Info', count: sampleAlerts.filter((alert) => alert.tone === 'info').length },
]);

const alerts = computed(() => sampleAlerts.filter((alert) => tone.value === 'all' || alert.tone === tone.value));
</script>

<template>
    <Head title="Alerts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Monitor" title="Alerts" sample-data />

            <p class="m-0 max-w-3xl text-[15px] text-muted-foreground">
                Alerts will be raised automatically when soil moisture crosses a threshold, a device stops reporting or a battery runs low. The alerts
                below are examples of what this page will show.
            </p>

            <FilterChips v-model="tone" :options="toneOptions" label="Filter by severity" />

            <SectionCard title="Open alerts">
                <AlertList :alerts="alerts" />
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
