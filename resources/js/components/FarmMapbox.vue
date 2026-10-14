<script setup lang="ts">
import { useMapbox } from '@/composables/useMapbox';
import { isPolygon, polygonBounds, polygonFitOptions, setFarmPolygon } from '@/lib/map';
import { signalColors } from '@/lib/theme';
import type { Polygon } from 'geojson';
import mapboxgl from 'mapbox-gl';
import { ref } from 'vue';

/**
 * Read-only map of a farm boundary and its sensors. Fits to the boundary when there is one, otherwise centres on `center`.
 */
const props = defineProps<{
    center: [number, number];
    farmPolygon?: Polygon | null;
    sensors?: { lat: number; lon: number; name?: string }[];
    zoom?: number;
}>();

const container = ref<HTMLElement | null>(null);
const polygon = isPolygon(props.farmPolygon) ? props.farmPolygon : null;

const { whenLoaded } = useMapbox(
    container,
    polygon ? { bounds: polygonBounds(polygon), fitBoundsOptions: polygonFitOptions } : { center: props.center, zoom: props.zoom },
);

whenLoaded((map) => {
    setFarmPolygon(map, polygon);
    (props.sensors ?? [])
        .filter((sensor) => sensor.lat && sensor.lon)
        .forEach((sensor) => {
            new mapboxgl.Marker({ color: signalColors.water })
                .setLngLat([sensor.lon, sensor.lat])
                .setPopup(new mapboxgl.Popup().setText(sensor.name || 'Unnamed sensor'))
                .addTo(map);
        });
});
</script>

<template>
    <div ref="container" class="h-[300px] w-full overflow-hidden rounded-md border"></div>
</template>
