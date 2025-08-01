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
            <Button variant="outline" size="sm" class="flex flex-row items-center border p-2">
              <Pencil class="h-4 w-4 mr-2" />
              <span>Edit</span>
            </Button>
          </Link>
          <Button
            variant="destructive"
            size="sm"
            class="flex flex-row items-center"
            @click="deleteSensor"
          >
            <Trash2 class="h-4 w-4 mr-2" />
            <span>Delete</span>
          </Button>
        </div>
      </div>
      <div v-if="sensor.lat && sensor.lon" class="mb-6">
        <div ref="mapContainer" class="map-container"></div>
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
              <p><Link :href="route('farms.show', sensor.farm?.id)">{{ sensor.farm?.name || '—' }}</Link></p>
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
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';


const page = usePage();
const sensor = computed(() => page.props.sensor as any);

function addFarmPolygon(farm: any, map: mapboxgl.Map) {
  if (!farm.coordinates) return;

  // Add farm polygon
  map.addSource('farm-area', {
    type: 'geojson',
    data: {
      type: 'Feature',
      properties: {},
      geometry: farm.coordinates,
    },
  });

  map.addLayer({
    id: 'farm-fill',
    type: 'fill',
    source: 'farm-area',
    layout: {},
    paint: {
      'fill-color': '#0080ff',
      'fill-opacity': 0.2,
    },
  });

  map.addLayer({
    id: 'farm-outline',
    type: 'line',
    source: 'farm-area',
    layout: {},
    paint: {
      'line-color': '#0080ff',
      'line-width': 2,
    },
  });

  // Fit the map to show the farm polygon
  const bounds = new mapboxgl.LngLatBounds();
  farm.coordinates.coordinates[0].forEach((coord: [number, number]) => bounds.extend(coord));
  map.fitBounds(bounds, { padding: 40, maxZoom: 17 });
}

const mapContainer = ref<HTMLElement | null>(null);
let map: mapboxgl.Map | null = null;

onMounted(() => {
  if (!mapContainer.value || !sensor.value.lat || !sensor.value.lon) {
    return;
  }

  mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string;

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/satellite-streets-v12',
    center: [parseFloat(sensor.value.lon), parseFloat(sensor.value.lat)],
    zoom: 15
  });

  // Add navigation controls
  map.addControl(new mapboxgl.NavigationControl());
  map.addControl(new mapboxgl.FullscreenControl());

  // Add marker at sensor location
  new mapboxgl.Marker({ color: '#2563eb' })
    .setLngLat([parseFloat(sensor.value.lon), parseFloat(sensor.value.lat)])
    .addTo(map);

  // Add farm polygon if available
  map.on('load', () => {
    if (sensor.value.farm && sensor.value.farm.coordinates && sensor.value.farm.coordinates.type === 'Polygon') {
      addFarmPolygon(sensor.value.farm, map!);
    }
  });
});

onBeforeUnmount(() => {
  if (map) {
    map.remove();
  }
});

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

.map-container {
  width: 100%;
  height: 300px;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}
</style>
