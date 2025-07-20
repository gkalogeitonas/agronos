<template>
  <Head :title="sensor.name ? sensor.name : 'Sensor Details'" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container py-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold pl-3 flex items-center gap-2">
          <span v-if="sensor.name">{{ sensor.name }}</span>
          <span v-else class="text-muted-foreground">Unnamed Sensor</span>
          <span class="ml-2 px-2 py-1 rounded bg-muted text-xs">{{ sensor.type }}</span>
        </h1>
        <div class="space-x-2 flex items-center">
          <Link :href="route('sensors.edit', sensor.id)">
            <Button variant="outline" size="sm" class="flex items-center border border-gray-300">
              <Pencil class="h-4 w-4 mr-2" />
              <span>Edit</span>
            </Button>
          </Link>
          <Button
            variant="destructive"
            size="sm"
            class="flex items-center border border-red-600 text-red-700 bg-white hover:bg-red-50 hover:text-red-900"
            @click="deleteSensor"
          >
            <Trash2 class="h-4 w-4 mr-2" />
            <span>Delete</span>
          </Button>
        </div>
      </div>
      <div v-if="sensor.lat && sensor.lon" class="mb-6">
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
      <Card class="mb-6">
        <CardHeader>
          <CardTitle>Sensor Details</CardTitle>
          <CardDescription>Information about this sensor</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Sensor UUID</h3>
              <p class="font-mono break-all">{{ sensor.uuid }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Device UUID</h3>
              <p class="font-mono break-all">{{ sensor.device?.uuid || '—' }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Farm</h3>
              <p>{{ sensor.farm?.name || '—' }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Location</h3>
              <p>Lat: {{ sensor.lat ?? '—' }}, Lon: {{ sensor.lon ?? '—' }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Last Reading</h3>
              <p>{{ sensor.last_reading ?? '—' }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Last Seen</h3>
              <p>{{ sensor.last_reading_at ?? '—' }}</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head , Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { MapboxMap, MapboxMarker } from '@studiometa/vue-mapbox-gl';
import { usePage } from '@inertiajs/vue3';
import 'mapbox-gl/dist/mapbox-gl.css';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';


const page = usePage();
const sensor = computed(() => page.props.sensor as any);
const mapboxToken = import.meta.env.VITE_MAPBOX_TOKEN;

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Sensors', href: '/sensors' },
  { title: sensor.value.name || 'Sensor Details', href: null },
];

function deleteSensor() {
  if (confirm(`Are you sure you want to delete ${sensor.value.name || 'this sensor'}?`)) {
    router.delete(route('sensors.destroy', sensor.value.id));
  }
}
</script>

<style scoped>
.text-muted-foreground {
  color: #6b7280;
}
.bg-muted {
  background: #f3f4f6;
}
</style>
