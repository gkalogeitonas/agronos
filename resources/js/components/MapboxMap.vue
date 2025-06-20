<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import mapboxgl from 'mapbox-gl';

const props = defineProps<{
  lng: number,
  lat: number,
  zoom?: number
}>();

const mapContainer = ref<HTMLElement | null>(null);
let map: mapboxgl.Map | null = null;

onMounted(() => {
  mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string;
  map = new mapboxgl.Map({
    container: mapContainer.value!,
    style: 'mapbox://styles/mapbox/satellite-streets-v12', // Satellite style
    center: [props.lng, props.lat],
    zoom: props.zoom ?? 12,
  });
  new mapboxgl.Marker().setLngLat([props.lng, props.lat]).addTo(map);
  // Add navigation controls (zoom/rotation)
  map.addControl(new mapboxgl.NavigationControl());
  // Add fullscreen control
  map.addControl(new mapboxgl.FullscreenControl());
});

onBeforeUnmount(() => {
  if (map) map.remove();
});
</script>

<template>
  <div ref="mapContainer" style="width: 100%; height: 400px; border-radius: 0.5rem; overflow: hidden;" />
</template>
