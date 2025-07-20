<template>
  <Head :title="`Edit Sensor`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col h-full gap-6 p-4 w-full max-w-3xl mx-auto">
      <h1 class="text-2xl font-bold mb-6">Edit Sensor</h1>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="name">Name</label>
          <input v-model="form.name" id="name" type="text" class="input w-full" />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="farm_id">Farm</label>
          <select v-model="form.farm_id" id="farm_id" class="input w-full">
            <option value="" disabled>Select a farm</option>
            <option v-for="farm in farms" :key="farm.id" :value="farm.id">{{ farm.name }}</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="lat">Latitude</label>
          <input v-model="form.lat" id="lat" type="number" step="any" class="input w-full" required />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="lon">Longitude</label>
          <input v-model="form.lon" id="lon" type="number" step="any" class="input w-full" required />
        </div>
        <div class="flex justify-end gap-2">
          <Link :href="route('sensors.show', sensor.id)">
            <Button type="button" variant="secondary">Cancel</Button>
          </Link>
          <Button type="submit">Save Changes</Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const sensor = computed(() => page.props.sensor);
const farms = computed(() => page.props.farms ?? []);

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Sensors', href: '/sensors' },
  { title: sensor.value.name || 'Sensor Details', href: route('sensors.show', sensor.value.id) },
  { title: 'Edit', href: null },
];

const form = useForm({
  name: sensor.value.name || '',
  farm_id: sensor.value.farm_id || '',
  lat: sensor.value.lat || '',
  lon: sensor.value.lon || '',
});

function submit() {
  router.put(route('sensors.update', sensor.value.id), form);
}
</script>

<style scoped>
.input {
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  outline: none;
  transition: box-shadow 0.2s;
}
.input:focus {
  box-shadow: 0 0 0 2px var(--color-primary, #2563eb);
}
</style>
