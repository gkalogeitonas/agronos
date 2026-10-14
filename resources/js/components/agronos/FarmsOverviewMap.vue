<script setup lang="ts">
import { statusTone } from '@/lib/theme';
import type { FarmSummary } from '@/types';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    farms: FarmSummary[];
}>();

const mapContainer = ref<HTMLElement | null>(null);
let map: mapboxgl.Map | null = null;

const locatedFarms = computed(() => props.farms.filter((farm) => farm.center !== null));

const pinStyles = {
    success: { color: '#1F7A4D', mark: '✓' },
    warning: { color: '#8A4B00', mark: '!' },
    danger: { color: '#9B2C1C', mark: '×' },
    info: { color: '#1F6FA8', mark: 'i' },
    neutral: { color: '#4A6455', mark: '?' },
} as const;

/**
 * Builds a pill-shaped link marker with a status mark, matching the design's farm pins.
 */
function createPin(farm: FarmSummary): HTMLAnchorElement {
    const style = pinStyles[statusTone(farm.status)];
    const pin = document.createElement('a');
    pin.href = route('farms.show', farm.id);
    pin.className =
        'flex min-h-11 items-center gap-2 rounded-full border border-input bg-card py-0 pr-3 pl-1.5 text-sm font-semibold whitespace-nowrap text-foreground no-underline shadow-sm';
    const mark = document.createElement('span');
    mark.className = 'inline-flex size-7 items-center justify-center rounded-full text-sm font-bold text-white';
    mark.style.background = style.color;
    mark.setAttribute('aria-hidden', 'true');
    mark.textContent = style.mark;
    pin.append(mark, document.createTextNode(farm.name));
    pin.setAttribute('aria-label', `${farm.name}, status ${farm.status}`);
    return pin;
}

onMounted(() => {
    if (!mapContainer.value || locatedFarms.value.length === 0) {
        return;
    }
    mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string;
    map = new mapboxgl.Map({
        container: mapContainer.value,
        style: 'mapbox://styles/mapbox/outdoors-v12',
        center: [locatedFarms.value[0].center!.lng, locatedFarms.value[0].center!.lat],
        zoom: 9,
    });
    map.addControl(new mapboxgl.NavigationControl({ showCompass: false }));

    const bounds = new mapboxgl.LngLatBounds();
    locatedFarms.value.forEach((farm) => {
        const position: [number, number] = [farm.center!.lng, farm.center!.lat];
        bounds.extend(position);
        new mapboxgl.Marker({ element: createPin(farm), anchor: 'left' }).setLngLat(position).addTo(map!);
    });
    if (locatedFarms.value.length > 1) {
        map.fitBounds(bounds, { padding: 80, maxZoom: 13, duration: 0 });
    }
});

onBeforeUnmount(() => {
    map?.remove();
});
</script>

<template>
    <div class="relative h-[340px] overflow-hidden rounded-md bg-[#E4ECDF]">
        <div v-if="locatedFarms.length > 0" ref="mapContainer" class="h-full w-full"></div>
        <div v-else class="flex h-full items-center justify-center p-6 text-center text-[15px] text-muted-foreground">
            Draw a boundary on a farm to see it on the map.
        </div>
    </div>
</template>
