<script setup lang="ts">
import FarmForm from '@/components/farms/FarmForm.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import type { Polygon } from 'geojson';

const props = defineProps<{
    farm: {
        id: number;
        name: string;
        location: string | null;
        size: number | null;
        coordinates: Polygon | null;
        description: string | null;
        center: { lng: number; lat: number } | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Farms', href: route('farms.index') },
    { title: props.farm.name, href: route('farms.show', props.farm.id) },
    { title: 'Edit', href: route('farms.edit', props.farm.id) },
];
</script>

<template>
    <Head :title="`Edit ${farm.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Edit farm" :title="farm.name" />
            <FarmForm :farm="farm" :action="route('farms.update', farm.id)" method="put">
                <template #cancel>
                    <Button variant="outline" as-child><Link :href="route('farms.show', farm.id)">Cancel</Link></Button>
                </template>
            </FarmForm>
        </PageContainer>
    </AppLayout>
</template>
