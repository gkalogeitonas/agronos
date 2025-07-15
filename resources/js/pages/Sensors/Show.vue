<template>
  <Head :title="sensor.name ? sensor.name : 'Sensor Details'" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col h-full gap-6 p-4 w-full max-w-3xl mx-auto">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <span v-if="sensor.name">{{ sensor.name }}</span>
        <span v-else class="text-muted-foreground">Unnamed Sensor</span>
        <span class="ml-2 px-2 py-1 rounded bg-muted text-xs">{{ sensor.type }}</span>
      </h1>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <div class="mb-2 text-sm text-muted-foreground">Sensor UUID</div>
          <div class="font-mono break-all">{{ sensor.uuid }}</div>
        </div>
        <div>
          <div class="mb-2 text-sm text-muted-foreground">Device UUID</div>
          <div class="font-mono break-all">{{ sensor.device?.uuid || '—' }}</div>
        </div>
        <div>
          <div class="mb-2 text-sm text-muted-foreground">Farm</div>
          <div>{{ sensor.farm?.name || '—' }}</div>
        </div>
        <div>
          <div class="mb-2 text-sm text-muted-foreground">Location</div>
          <div>Lat: {{ sensor.lat ?? '—' }}, Lon: {{ sensor.lon ?? '—' }}</div>
        </div>
        <div>
          <div class="mb-2 text-sm text-muted-foreground">Last Reading</div>
          <div>{{ sensor.last_reading ?? '—' }}</div>
        </div>
        <div>
          <div class="mb-2 text-sm text-muted-foreground">Last Seen</div>
          <div>{{ sensor.last_reading_at ?? '—' }}</div>
        </div>
      </div>
      <div v-if="sensor.lat && sensor.lon" class="mt-8">
        <MapboxMap
          style="width: 100%; height: 300px; border-radius: 0.5rem;"
          :access-token="mapboxToken"
          map-style="mapbox://styles/mapbox/satellite-streets-v12"
          :center="[parseFloat(sensor.lon), parseFloat(sensor.lat)]"
          :zoom="15"
        >
          <MapboxMarker :lng-lat="[parseFloat(sensor.lon), parseFloat(sensor.lat)]" color="#2563eb" />
        </MapboxMap>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { MapboxMap, MapboxMarker } from '@studiometa/vue-mapbox-gl';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const sensor = computed(() => page.props.sensor);
const mapboxToken = import.meta.env.VITE_MAPBOX_TOKEN;

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Sensors', href: '/sensors' },
  { title: sensor.value.name || 'Sensor Details', href: null },
];
</script>

<style scoped>
.text-muted-foreground {
  color: #6b7280;
}
.bg-muted {
  background: #f3f4f6;
}
</style>
