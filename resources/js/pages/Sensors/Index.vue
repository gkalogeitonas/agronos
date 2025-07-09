<template>
  <Head title="Sensors" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col h-full gap-6 p-4">
      <!-- Header -->
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Sensors Management</h1>
        <Link :href="route('sensors.create')">
          <Button>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 3a1 1 0 00-1 1v5H4a1 1 0 100 2h5v5a1 1 0 102 0v-5h5a1 1 0 100-2h-5V4a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            Add Sensor
          </Button>
        </Link>
      </div>
      <!-- Sensor List -->
      <div v-if="sensors.length > 0" class="flex flex-col gap-4">
        <SensorCard v-for="sensor in sensors" :key="sensor.id" :sensor="sensor" />
      </div>
      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center p-8 border rounded-lg border-dashed text-center">
        <div class="w-16 h-16 rounded-full bg-muted flex items-center justify-center mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-muted-foreground" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
          </svg>
        </div>
        <h3 class="text-lg font-medium mb-2">No sensors found</h3>
        <p class="text-sm text-muted-foreground mb-4">Get started by adding your first sensor.</p>
        <!--
        <Link :href="route('sensors.create')">
          <Button>Add Your First Sensor</Button>
        </Link>
        -->
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SensorCard from '@/components/SensorCard.vue';

interface Sensor {
  id: number;
  name: string;
  uuid: string;
  type: string;
  device_id: number;
  lat: number;
  lon: number;
  last_reading_at:timestamp | null;
  last_reading: number | null;
}

const page = usePage();
const sensors = computed(() => page.props.sensors as Sensor[] ?? []);

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Sensors',
    href: '/sensors',
    current: true,
  },
];
</script>

<style scoped>
.container {
  max-width: 900px;
}
</style>
