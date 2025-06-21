<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import mapboxgl from 'mapbox-gl';

// @ts-ignore
import MapboxDraw from '@mapbox/mapbox-gl-draw';
import '@mapbox/mapbox-gl-draw/dist/mapbox-gl-draw.css';

const props = defineProps<{
  lng: number,
  lat: number,
  zoom?: number,
  polygon?: any, // GeoJSON Polygon
  editable?: boolean
}>();

const emit = defineEmits(['update:polygon']);

const mapContainer = ref<HTMLElement | null>(null);
let map: mapboxgl.Map | null = null;
let draw: any = null;

function fitPolygon(polygon) {
  if (!map || !polygon || !polygon.coordinates || !polygon.coordinates[0]) return;
  const bounds = new mapboxgl.LngLatBounds();
  polygon.coordinates[0].forEach(coord => bounds.extend(coord));
  map.fitBounds(bounds, { padding: 40, maxZoom: 17 });
}

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

  if (props.editable) {
    draw = new MapboxDraw({
      displayControlsDefault: false,
      controls: { polygon: true, trash: true },
      defaultMode: 'draw_polygon',
    });
    map.addControl(draw);
    map.on('draw.create', updatePolygon);
    map.on('draw.update', updatePolygon);
    map.on('draw.delete', () => emit('update:polygon', null));
    // If a polygon is provided, add it to the draw tool
    map.on('load', () => {
      if (props.polygon && props.polygon.type === 'Polygon') {
        draw.add({
          type: 'Feature',
          geometry: props.polygon,
        });
        fitPolygon(props.polygon);
      }
    });
  } else if (props.polygon && props.polygon.type === 'Polygon') {
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
      fitPolygon(props.polygon);
    });
  } else {
    new mapboxgl.Marker().setLngLat([props.lng, props.lat]).addTo(map);
  }
});

function updatePolygon() {
  if (!draw) return;
  const data = draw.getAll();
  if (data.features.length > 0) {
    const polygon = data.features[0].geometry;
    emit('update:polygon', polygon);
    fitPolygon(polygon);
  } else {
    emit('update:polygon', null);
  }
}

watch(() => props.polygon, (newPolygon) => {
  if (map && newPolygon && newPolygon.type === 'Polygon') {
    if (!props.editable && map.getSource('farm-area')) {
      map.getSource('farm-area').setData({
        type: 'Feature',
        geometry: newPolygon,
      });
      fitPolygon(newPolygon);
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
