<script setup lang="ts">
import { ref } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FarmForm from '@/components/farms/FarmForm.vue';
import MapboxMap from '@/components/MapboxMap.vue'

import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
  farm: {
    id: number,
    name: string,
    location: string,
    size: number,
    coordinates: any,
    description: string | null,
  }
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Farms', href: '/farms' },
  { title: props.farm.name, href: route('farms.show', props.farm.id) },
  { title: 'Edit', href: route('farms.edit', props.farm.id), current: true },
];

const form = useForm({
  name: props.farm.name,
  location: props.farm.location,
  size: props.farm.size,
  coordinates: props.farm.coordinates,
  description: props.farm.description,
});

const polygon = ref<any>(props.farm.coordinates);

const handleMapPolygon = (poly: any) => {
  polygon.value = poly;
  form.coordinates = poly || null;
};

const resetPolygon = () => {
  polygon.value = null;
  form.coordinates = null;
};

const submit = () => {
  form.put(route('farms.update', props.farm.id));
};

// Get center of polygon for map view
const lng = props.farm.center?.lng ?? 23.7275;
const lat = props.farm.center?.lat ?? 37.9838;
</script>

<template>
  <Head :title="`Edit ${props.farm.name}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container py-8 max-w-7xl mx-auto">
      <FarmForm
        :form="form"
        :polygon="farm.coordinates"
        :onUpdatePolygon="handleMapPolygon"
        :onResetPolygon="resetPolygon"
        :onSubmit="submit"
        :isEdit="true"
        :defaultLng="lng"
        :defaultLat="lat"
      />
    </div>
  </AppLayout>
</template>
