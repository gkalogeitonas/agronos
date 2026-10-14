<script setup lang="ts">
import FarmForm, { type FarmFormData } from '@/components/farms/FarmForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { Polygon } from 'geojson';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Farms', href: '/farms' },
    { title: 'Add farm', href: route('farms.create') },
];

const form = useForm<FarmFormData>({
    name: '',
    location: '',
    size: '',
    coordinates: null,
    description: '',
});

const polygon = ref<Polygon | null>(null);

const handleMapPolygon = (value: Polygon | null) => {
    polygon.value = value;
    form.coordinates = value;
};

const submit = () => {
    form.post(route('farms.store'));
};
</script>

<template>
    <Head title="Add farm" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 md:p-8">
            <PageHeader eyebrow="Farms" title="Add farm" />
            <FarmForm :form="form" :polygon="polygon" :on-update-polygon="handleMapPolygon" :on-submit="submit">
                <template #cancel>
                    <Button variant="outline" as-child><Link :href="route('farms.index')">Cancel</Link></Button>
                </template>
            </FarmForm>
        </div>
    </AppLayout>
</template>
