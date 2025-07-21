<template>
  <Head :title="`Edit Sensor`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container py-8">
      <Card class="mb-6">
        <CardHeader>
          <CardTitle>Edit Sensor</CardTitle>
          <CardDescription>Update sensor details. Non-editable fields are shown for reference.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Sensor UUID</h3>
              <p class="font-mono break-all bg-gray-100 rounded px-2 py-1">{{ sensor.uuid }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Device UUID</h3>
              <p class="font-mono break-all bg-gray-100 rounded px-2 py-1">{{ sensor.device?.uuid || '—' }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Type</h3>
              <p class="bg-gray-100 rounded px-2 py-1">{{ sensor.type || '—' }}</p>
            </div>
          </div>
          <div class="mb-6">
            <div ref="mapContainer" class="map-container"></div>
          </div>
        </CardContent>
        <CardContent>
          <div class="flex flex-col h-full gap-6 p-4 w-full max-w-3xl mx-auto">
            <form @submit.prevent="submit">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                  <label class="block text-sm font-medium mb-1" for="name">Name</label>
                  <input v-model="form.name" id="name" type="text" class="input w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1" for="farm_id">Farm</label>
                  <select v-model="form.farm_id" id="farm_id" class="input w-full">
                    <option value="" disabled>Select a farm</option>
                    <option v-for="farm in farms" :key="farm.id" :value="farm.id">{{ farm.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1" for="lat">Latitude</label>
                  <input v-model="form.lat" id="lat" type="number" step="any" class="input w-full" required />
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1" for="lon">Longitude</label>
                  <input v-model="form.lon" id="lon" type="number" step="any" class="input w-full" required />
                </div>
              </div>
              <div class="flex justify-end gap-2">
                <Link :href="route('sensors.show', sensor.id)">
                  <Button type="button" variant="secondary">Cancel</Button>
                </Link>
                <Button type="submit">Save Changes</Button>
              </div>
            </form>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';
import { usePage } from '@inertiajs/vue3';


const page = usePage();
const sensor = page.props.sensor as any;
const farms = computed(() => page.props.farms ?? []);

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Sensors', href: '/sensors' },
  { title: sensor.name || 'Sensor Details', href: route('sensors.show', sensor.id) },
  { title: 'Edit', href: null },
];

const form = useForm({
  name: sensor.name || '',
  farm_id: sensor.farm_id || '',
  lat: sensor.lat || '',
  lon: sensor.lon || '',
});

function submit() {
  router.put(route('sensors.update', sensor.id), form);
}

function addFarmPolygon(farm: any) {
  if (!map || !farm.coordinates) return;

  // Remove existing farm polygon if it exists
  if (map.getSource('farm-area')) {
    map.removeLayer('farm-fill');
    map.removeLayer('farm-outline');
    map.removeSource('farm-area');
  }

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
let marker: mapboxgl.Marker | null = null;

onMounted(() => {
  if (!mapContainer.value) {
    console.error('Map container not found');
    return;
  }

  mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string;

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/satellite-streets-v12',
    center: [parseFloat(form.lon) || 0, parseFloat(form.lat) || 0],
    zoom: 15
  });

  // Add navigation controls
  map.addControl(new mapboxgl.NavigationControl());
  map.addControl(new mapboxgl.FullscreenControl());

  // Add farm polygon if available
  map.on('load', () => {
    const farmsArray = farms.value as any[];
    const selectedFarm = farmsArray.find((f: any) => f.id == form.farm_id);
    if (selectedFarm && selectedFarm.coordinates && selectedFarm.coordinates.type === 'Polygon') {
      addFarmPolygon(selectedFarm);
    }
  });

  // Add marker at sensor location
  if (form.lat && form.lon) {
    marker = new mapboxgl.Marker({ draggable: true })
      .setLngLat([parseFloat(form.lon), parseFloat(form.lat)])
      .addTo(map);

    // Update form when marker is dragged
    marker.on('dragend', () => {
      const lngLat = marker!.getLngLat();
      form.lat = lngLat.lat.toString();
      form.lon = lngLat.lng.toString();
    });
  }

  // Move marker when map is clicked
  map.on('click', (e) => {
    const { lng, lat } = e.lngLat;
    form.lat = lat.toString();
    form.lon = lng.toString();

    if (marker) {
      marker.setLngLat([lng, lat]);
    } else {
      marker = new mapboxgl.Marker({ draggable: true })
        .setLngLat([lng, lat])
        .addTo(map!);

      marker.on('dragend', () => {
        const lngLat = marker!.getLngLat();
        form.lat = lngLat.lat.toString();
        form.lon = lngLat.lng.toString();
      });
    }
  });
});

onBeforeUnmount(() => {
  if (map) {
    map.remove();
  }
});

// Watch for farm selection changes and update polygon
watch(() => form.farm_id, (newFarmId) => {
  if (map && map.loaded()) {
    const farmsArray = farms.value as any[];
    const selectedFarm = farmsArray.find((f: any) => f.id == newFarmId);
    if (selectedFarm && selectedFarm.coordinates && selectedFarm.coordinates.type === 'Polygon') {
      addFarmPolygon(selectedFarm);
    } else if (map.getSource('farm-area')) {
      // Remove farm polygon if no farm is selected or farm has no coordinates
      map.removeLayer('farm-fill');
      map.removeLayer('farm-outline');
      map.removeSource('farm-area');
    }
  }
});

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

.map-container {
  width: 100%;
  height: 300px;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}
</style>
