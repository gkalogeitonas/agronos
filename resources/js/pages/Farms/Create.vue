<script setup lang="ts">
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FarmForm from '@/components/farms/FarmForm.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Farms', href: '/farms' },
  { title: 'Create', href: route('farms.create'), current: true },
];

const form = useForm({
  name: '',
  location: '',
  size: '',
  coordinates: '',
  description: '',
});

const defaultLng = 23.7275; // Example: Athens
const defaultLat = 37.9838;

const polygon = ref<any>(null);

const handleMapPolygon = (poly: any) => {
  polygon.value = poly;
  form.coordinates = poly || null;
};

const resetPolygon = () => {
  polygon.value = null;
  form.coordinates = null;
};

const submit = () => {
  form.post(route('farms.store'));
};
</script>

<template>
  <Head title="Create Farm" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container py-8 max-w-2xl mx-auto">
      <FarmForm
        :form="form"
        :polygon="polygon"
        :onUpdatePolygon="handleMapPolygon"
        :onResetPolygon="resetPolygon"
        :onSubmit="submit"
        :defaultLng="defaultLng"
        :defaultLat="defaultLat"
      />
    </div>
  </AppLayout>
</template>
