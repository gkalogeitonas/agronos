<script setup lang="ts">
import FarmForm, { type FarmFormData } from '@/components/farms/FarmForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { Polygon } from 'geojson';
import { ref } from 'vue';

const props = defineProps<{
    farm: {
        id: number;
        name: string;
        location: string;
        size: number;
        coordinates: Polygon | null;
        description: string | null;
        center: { lng: number; lat: number } | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Farms', href: '/farms' },
    { title: props.farm.name, href: route('farms.show', props.farm.id) },
    { title: 'Edit', href: route('farms.edit', props.farm.id) },
];

const form = useForm<FarmFormData>({
    name: props.farm.name,
    location: props.farm.location,
    size: props.farm.size,
    coordinates: props.farm.coordinates,
    description: props.farm.description ?? '',
});

const polygon = ref<Polygon | null>(props.farm.coordinates);

const handleMapPolygon = (value: Polygon | null) => {
    polygon.value = value;
    form.coordinates = value;
};

const submit = () => {
    form.put(route('farms.update', props.farm.id));
};
</script>

<template>
    <Head :title="`Edit ${farm.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 md:p-8">
            <PageHeader eyebrow="Edit farm" :title="farm.name" />
            <FarmForm
                :form="form"
                :polygon="polygon"
                :on-update-polygon="handleMapPolygon"
                :on-submit="submit"
                is-edit
                :default-lng="farm.center?.lng"
                :default-lat="farm.center?.lat"
            >
                <template #cancel>
                    <Button variant="outline" as-child><Link :href="route('farms.show', farm.id)">Cancel</Link></Button>
                </template>
            </FarmForm>
        </div>
    </AppLayout>
</template>
