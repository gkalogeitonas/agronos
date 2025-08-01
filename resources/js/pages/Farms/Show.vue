<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { router } from '@inertiajs/vue3'
import { Pencil, Trash2 } from 'lucide-vue-next'
import { type BreadcrumbItem } from '@/types'
import { Link } from '@inertiajs/vue3'
import SensorCard from '@/components/SensorCard.vue'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import mapboxgl from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'

// Props
const props = defineProps<{
  farm: {
    id: number
    name: string
    location: string
    size: number
    description: string | null
    coordinates: any // GeoJSON object or null
    created_at: string
    updated_at: string
    center: {
      lng: number
      lat: number
    } | null
  },
  sensors: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Farms',
    href: '/farms',
  },
  {
    title: props.farm.name,
    href: route('farms.show', props.farm.id),
  },
]

// Delete farm handler
const deleteFarm = () => {
  if (confirm(`Are you sure you want to delete ${props.farm.name}?`)) {
    router.delete(route('farms.destroy', props.farm.id))
  }
}

// Map logic
const mapContainer = ref<HTMLElement | null>(null)
let map: mapboxgl.Map | null = null

function addFarmPolygon(farm: any, map: mapboxgl.Map) {
  if (!farm.coordinates) return

  // Add farm polygon
  map.addSource('farm-area', {
    type: 'geojson',
    data: {
      type: 'Feature',
      properties: {},
      geometry: farm.coordinates,
    },
  })

  map.addLayer({
    id: 'farm-fill',
    type: 'fill',
    source: 'farm-area',
    layout: {},
    paint: {
      'fill-color': '#0080ff',
      'fill-opacity': 0.2,
    },
  })

  map.addLayer({
    id: 'farm-outline',
    type: 'line',
    source: 'farm-area',
    layout: {},
    paint: {
      'line-color': '#0080ff',
      'line-width': 2,
    },
  })

  // Fit the map to show the farm polygon
  const bounds = new mapboxgl.LngLatBounds()
  farm.coordinates.coordinates[0].forEach((coord: [number, number]) => bounds.extend(coord))
  map.fitBounds(bounds, { padding: 40, maxZoom: 17 })
}

function addSensorMarkers(sensors: any[], map: mapboxgl.Map) {
  sensors.forEach(sensor => {
    if (sensor.lat && sensor.lon) {
      new mapboxgl.Marker({ color: '#2563eb' })
        .setLngLat([parseFloat(sensor.lon), parseFloat(sensor.lat)])
        .setPopup(new mapboxgl.Popup().setText(sensor.name || 'Unnamed Sensor'))
        .addTo(map)
    }
  })
}

onMounted(() => {
  if (!mapContainer.value) return

  mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string

  // Default center: farm center or fallback
  const center = props.farm.center
    ? [props.farm.center.lng, props.farm.center.lat]
    : [0, 0]

  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/satellite-streets-v12',
    center,
    zoom: 12,
  })

  map.addControl(new mapboxgl.NavigationControl())
  map.addControl(new mapboxgl.FullscreenControl())

  map.on('load', () => {
    if (props.farm.coordinates && props.farm.coordinates.type === 'Polygon') {
      addFarmPolygon(props.farm, map!)
    }
    addSensorMarkers(props.sensors, map!)
  })
})

onBeforeUnmount(() => {
  if (map) {
    map.remove()
  }
})
</script>

<template>
  <Head :title="farm.name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container py-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold pl-3">{{ farm.name }}</h1>
        <div class="space-x-2">
          <Link :href="route('farms.edit', farm.id)">
            <Button
              variant="outline"
              size="sm"
            >
              <Pencil class="h-4 w-4 mr-2" />
              Edit
            </Button>
          </Link>
          <Button
            variant="destructive"
            size="sm"
            @click="deleteFarm"
          >
            <Trash2 class="h-4 w-4 mr-2" />
            Delete
          </Button>
          <Link :href="route('farms.sensors.create', { farm: farm.id })">
            <Button variant="default" size="sm">
              Add Sensor
            </Button>
          </Link>
        </div>
      </div>

      <div class="mb-6">
        <div ref="mapContainer" class="map-container"></div>
      </div>

      <Card class="mb-6">
        <CardHeader>
          <CardTitle>Farm Details</CardTitle>
          <CardDescription>Information about this farm</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Location</h3>
              <p>{{ farm.location }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Size</h3>
              <p>{{ farm.size }} m²</p>
            </div>
            <div class="col-span-1 md:col-span-2">
              <h3 class="text-sm font-medium text-muted-foreground">Description</h3>
              <p>{{ farm.description || 'No description available' }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Created</h3>
              <p>{{ new Date(farm.created_at).toLocaleDateString() }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Last Updated</h3>
              <p>{{ new Date(farm.updated_at).toLocaleDateString() }}</p>
            </div>
          </div>
        </CardContent>
        <CardFooter class="flex justify-between">
          <Link :href="route('farms.index')">
            <Button variant="outline">
              Back to Farms
            </Button>
          </Link>
        </CardFooter>
      </Card>

      <Card class="mb-6">
        <CardHeader>
          <CardTitle>Sensors</CardTitle>
          <CardDescription>All sensors registered to this farm</CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="sensors.length === 0" class="text-muted-foreground">No sensors found for this farm.</div>
          <div v-else class="grid grid-cols-1 gap-4">
            <SensorCard v-for="sensor in sensors" :key="sensor.id" :sensor="sensor" />
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<style scoped>
.map-container {
  width: 100%;
  height: 300px;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}
</style>
