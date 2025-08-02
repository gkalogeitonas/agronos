<template>
  <div ref="mapContainer" class="map-container"></div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';

const props = defineProps<{
  center: [number, number],
  farmPolygon?: any, // GeoJSON Polygon
  sensors?: Array<{ lat: number, lon: number, name?: string }>,
  zoom?: number
}>();

const mapContainer = ref<HTMLElement | null>(null);
let map: mapboxgl.Map | null = null;

function addFarmPolygon(farmPolygon: any, map: mapboxgl.Map) {
  if (!farmPolygon) return;
  map.addSource('farm-area', {
    type: 'geojson',
    data: {
      type: 'Feature',
      properties: {},
      geometry: farmPolygon,
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
  // Fit bounds
  const bounds = new mapboxgl.LngLatBounds();
  farmPolygon.coordinates[0].forEach((coord: [number, number]) => bounds.extend(coord));
  map.fitBounds(bounds, { padding: 40, maxZoom: 17 });
}

function addSensorMarkers(sensors: any[], map: mapboxgl.Map) {
  sensors.forEach(sensor => {
    if (sensor.lat && sensor.lon) {
      new mapboxgl.Marker({ color: '#2563eb' })
        .setLngLat([parseFloat(sensor.lon), parseFloat(sensor.lat)])
        .setPopup(new mapboxgl.Popup().setText(sensor.name || 'Unnamed Sensor'))
        .addTo(map);
    }
  });
}

onMounted(() => {
  if (!mapContainer.value) return;
  mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string;
  map = new mapboxgl.Map({
    container: mapContainer.value,
    style: 'mapbox://styles/mapbox/satellite-streets-v12',
    center: props.center,
    zoom: props.zoom || 12,
  });
  map.addControl(new mapboxgl.NavigationControl());
  map.addControl(new mapboxgl.FullscreenControl());
  map.on('load', () => {
    if (props.farmPolygon && props.farmPolygon.type === 'Polygon') {
      addFarmPolygon(props.farmPolygon, map!);
    }
    if (props.sensors && props.sensors.length > 0) {
      addSensorMarkers(props.sensors, map!);
    }
  });
});

onBeforeUnmount(() => {
  if (map) {
    map.remove();
  }
});
</script>

<style scoped>
.map-container {
  width: 100%;
  height: 300px;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}
</style>
