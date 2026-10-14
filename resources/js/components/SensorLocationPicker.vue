<script setup lang="ts">
import { useMapbox } from '@/composables/useMapbox';
import { fitToPolygon, isPolygon, polygonBounds, polygonFitOptions, setFarmPolygon } from '@/lib/map';
import { signalColors } from '@/lib/theme';
import type { Polygon } from 'geojson';
import mapboxgl from 'mapbox-gl';
import { computed, ref, watch } from 'vue';

/**
 * Picks a sensor position on a satellite map: click the map or drag the marker.
 * `lat` and `lon` are two-way bound as strings so they can share a form with the coordinate inputs.
 */
const props = defineProps<{
    farmPolygon?: Polygon | null;
}>();

const lat = defineModel<string>('lat', { required: true });
const lon = defineModel<string>('lon', { required: true });

const position = computed<[number, number] | null>(() => {
    const latitude = parseFloat(lat.value);
    const longitude = parseFloat(lon.value);

    return Number.isFinite(latitude) && Number.isFinite(longitude) ? [longitude, latitude] : null;
});

const container = ref<HTMLElement | null>(null);
const initialPolygon = isPolygon(props.farmPolygon) ? props.farmPolygon : null;

const { whenLoaded } = useMapbox(
    container,
    position.value
        ? { center: position.value, zoom: 15 }
        : initialPolygon
          ? { bounds: polygonBounds(initialPolygon), fitBoundsOptions: polygonFitOptions }
          : { center: [0, 20], zoom: 1 },
);

let marker: mapboxgl.Marker | null = null;

function setPosition({ lng, lat: latitude }: mapboxgl.LngLat): void {
    lat.value = latitude.toString();
    lon.value = lng.toString();
}

function placeMarker(map: mapboxgl.Map, lngLat: [number, number]): void {
    if (marker) {
        marker.setLngLat(lngLat);
        return;
    }
    marker = new mapboxgl.Marker({ draggable: true, color: signalColors.water }).setLngLat(lngLat).addTo(map);
    marker.on('dragend', () => setPosition(marker!.getLngLat()));
}

whenLoaded((map) => {
    setFarmPolygon(map, initialPolygon);
    if (position.value) {
        placeMarker(map, position.value);
    }
    map.on('click', (event) => setPosition(event.lngLat));
});

/** Moves the marker when the inputs change, and brings it into view if it is off-screen or the map is zoomed far out. */
watch(position, (lngLat) => {
    if (!lngLat) {
        return;
    }
    whenLoaded((map) => {
        placeMarker(map, lngLat);
        if (map.getZoom() < 10 || !map.getBounds()?.contains(lngLat)) {
            map.jumpTo({ center: lngLat, zoom: Math.max(map.getZoom(), 15) });
        }
    });
});

watch(
    () => props.farmPolygon,
    (polygon) => {
        const boundary = isPolygon(polygon) ? polygon : null;
        whenLoaded((map) => {
            setFarmPolygon(map, boundary);
            if (boundary) {
                fitToPolygon(map, boundary);
            }
        });
    },
);
</script>

<template>
    <div ref="container" class="h-[320px] w-full overflow-hidden rounded-md border"></div>
</template>
