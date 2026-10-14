<script setup lang="ts">
import { useMapbox } from '@/composables/useMapbox';
import { fitToPolygon, isPolygon, polygonBounds, polygonFitOptions } from '@/lib/map';
import type { Feature, FeatureCollection, Polygon } from 'geojson';
import type mapboxgl from 'mapbox-gl';
import { ref } from 'vue';

// @ts-expect-error: MapboxDraw has no TypeScript types
import MapboxDraw from '@mapbox/mapbox-gl-draw';
import '@mapbox/mapbox-gl-draw/dist/mapbox-gl-draw.css';

/** The parts of MapboxDraw this component uses. */
interface DrawControl extends mapboxgl.IControl {
    add(feature: Feature): void;
    getAll(): FeatureCollection;
}

/**
 * Lets the user draw or edit a farm boundary. Centres on `lng`/`lat` until a boundary exists.
 */
const props = defineProps<{
    lng: number;
    lat: number;
    zoom?: number;
    polygon?: Polygon | null;
}>();

const emit = defineEmits<{
    'update:polygon': [polygon: Polygon | null];
}>();

const container = ref<HTMLElement | null>(null);
const initialPolygon = isPolygon(props.polygon) ? props.polygon : null;

const { whenLoaded } = useMapbox(
    container,
    initialPolygon
        ? { bounds: polygonBounds(initialPolygon), fitBoundsOptions: polygonFitOptions }
        : { center: [props.lng, props.lat], zoom: props.zoom },
);

whenLoaded((map) => {
    const draw: DrawControl = new MapboxDraw({
        displayControlsDefault: false,
        controls: { polygon: true, trash: true },
        defaultMode: 'draw_polygon',
    });
    map.addControl(draw);

    if (initialPolygon) {
        draw.add({ type: 'Feature', properties: {}, geometry: initialPolygon });
    }

    const emitPolygon = (): void => {
        const geometry = draw.getAll().features[0]?.geometry;
        if (isPolygon(geometry)) {
            emit('update:polygon', geometry);
            fitToPolygon(map, geometry);
        } else {
            emit('update:polygon', null);
        }
    };

    map.on('draw.create', emitPolygon);
    map.on('draw.update', emitPolygon);
    map.on('draw.delete', () => emit('update:polygon', null));
});
</script>

<template>
    <div ref="container" class="h-[400px] w-full overflow-hidden rounded-md border"></div>
</template>
