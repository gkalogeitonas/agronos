<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import mapboxgl from 'mapbox-gl';

const props = defineProps<{
  lng: number,
  lat: number,
  zoom?: number,
  polygon?: any // GeoJSON Polygon
}>();

const mapContainer = ref<HTMLElement | null>(null);
let map: mapboxgl.Map | null = null;

onMounted(() => {
  mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string;
  map = new mapboxgl.Map({
    container: mapContainer.value!,
    style: 'mapbox://styles/mapbox/satellite-streets-v12',
    center: [props.lng, props.lat],
    zoom: props.zoom ?? 12,
  });
  map.addControl(new mapboxgl.NavigationControl());
  map.addControl(new mapboxgl.FullscreenControl());

  if (props.polygon && props.polygon.type === 'Polygon') {
    map.on('load', () => {
      if (map.getSource('farm-area')) {
        map.removeLayer('farm-fill');
        map.removeLayer('farm-outline');
        map.removeSource('farm-area');
      }
      map.addSource('farm-area', {
        type: 'geojson',
        data: {
          type: 'Feature',
          geometry: props.polygon,
        },
      });
      map.addLayer({
        id: 'farm-fill',
        type: 'fill',
        source: 'farm-area',
        layout: {},
        paint: {
          'fill-color': '#0080ff',
          'fill-opacity': 0.4,
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
    });
  } else {
    new mapboxgl.Marker().setLngLat([props.lng, props.lat]).addTo(map);
  }
});

watch(() => props.polygon, (newPolygon) => {
  if (map && newPolygon && newPolygon.type === 'Polygon') {
    if (map.getSource('farm-area')) {
      map.getSource('farm-area').setData({
        type: 'Feature',
        geometry: newPolygon,
      });
    }
  }
});

onBeforeUnmount(() => {
  if (map) map.remove();
});
</script>

<template>
  <div ref="mapContainer" style="width: 100%; height: 400px; border-radius: 0.5rem; overflow: hidden;" />
</template>
