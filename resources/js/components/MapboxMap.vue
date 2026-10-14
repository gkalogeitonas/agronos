<script setup lang="ts">
import type { Polygon } from 'geojson';
import mapboxgl from 'mapbox-gl';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

// @ts-expect-error: MapboxDraw has no TypeScript types
import MapboxDraw from '@mapbox/mapbox-gl-draw';
import '@mapbox/mapbox-gl-draw/dist/mapbox-gl-draw.css';

const props = defineProps<{
    lng: number;
    lat: number;
    zoom?: number;
    polygon?: Polygon | null;
    editable?: boolean;
}>();

const emit = defineEmits(['update:polygon']);

const mapContainer = ref<HTMLElement | null>(null);
let map: mapboxgl.Map | null = null;
let draw: any = null;

function fitPolygon(polygon: Polygon | null | undefined) {
    if (!map || !polygon || !polygon.coordinates || !polygon.coordinates[0]) return;
    const bounds = new mapboxgl.LngLatBounds();
    polygon.coordinates[0].forEach((coord) => bounds.extend(coord as [number, number]));
    map.fitBounds(bounds, { padding: 40, maxZoom: 17 });
}

onMounted(() => {
    mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string;
    const m = new mapboxgl.Map({
        container: mapContainer.value!,
        style: 'mapbox://styles/mapbox/satellite-streets-v12',
        center: [props.lng, props.lat],
        zoom: props.zoom ?? 12,
    });
    map = m;
    m.addControl(new mapboxgl.NavigationControl());
    m.addControl(new mapboxgl.FullscreenControl());

    if (props.editable) {
        draw = new MapboxDraw({
            displayControlsDefault: false,
            controls: { polygon: true, trash: true },
            defaultMode: 'draw_polygon',
        });
        m.addControl(draw);
        m.on('draw.create', updatePolygon);
        m.on('draw.update', updatePolygon);
        m.on('draw.delete', () => emit('update:polygon', null));
        // If a polygon is provided, add it to the draw tool
        m.on('load', () => {
            if (props.polygon && props.polygon.type === 'Polygon') {
                draw.add({
                    type: 'Feature',
                    geometry: props.polygon,
                });
                fitPolygon(props.polygon);
            }
        });
    } else if (props.polygon && props.polygon.type === 'Polygon') {
        const polygon = props.polygon;
        m.on('load', () => {
            if (m.getSource('farm-area')) {
                m.removeLayer('farm-fill');
                m.removeLayer('farm-outline');
                m.removeSource('farm-area');
            }
            m.addSource('farm-area', {
                type: 'geojson',
                data: {
                    type: 'Feature',
                    properties: {},
                    geometry: polygon,
                },
            });
            m.addLayer({
                id: 'farm-fill',
                type: 'fill',
                source: 'farm-area',
                layout: {},
                paint: {
                    'fill-color': '#1F7A4D',
                    'fill-opacity': 0.3,
                },
            });
            m.addLayer({
                id: 'farm-outline',
                type: 'line',
                source: 'farm-area',
                layout: {},
                paint: {
                    'line-color': '#1F7A4D',
                    'line-width': 2,
                },
            });
            fitPolygon(polygon);
        });
    } else {
        new mapboxgl.Marker({ color: '#1F7A4D' }).setLngLat([props.lng, props.lat]).addTo(m);
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

watch(
    () => props.polygon,
    (newPolygon) => {
        if (map && newPolygon && newPolygon.type === 'Polygon') {
            const source = map.getSource<mapboxgl.GeoJSONSource>('farm-area');
            if (!props.editable && source) {
                source.setData({
                    type: 'Feature',
                    properties: {},
                    geometry: newPolygon,
                });
                fitPolygon(newPolygon);
            }
        }
    },
);

onBeforeUnmount(() => {
    if (map) map.remove();
});
</script>

<template>
    <div ref="mapContainer" class="h-[400px] w-full overflow-hidden rounded-md border" />
</template>
