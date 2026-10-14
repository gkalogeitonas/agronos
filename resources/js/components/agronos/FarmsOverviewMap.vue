<script setup lang="ts">
import { useMapbox } from '@/composables/useMapbox';
import { statusTone, toneColors, type StatusTone } from '@/lib/theme';
import type { FarmSummary } from '@/types';
import mapboxgl from 'mapbox-gl';
import { computed, ref } from 'vue';

const props = defineProps<{
    farms: FarmSummary[];
}>();

const mapContainer = ref<HTMLElement | null>(null);
const locatedFarms = computed(() => props.farms.filter((farm) => farm.center !== null));

const pinMarks: Record<StatusTone, string> = {
    success: '✓',
    warning: '!',
    danger: '×',
    info: 'i',
    neutral: '?',
};

/**
 * Builds a pill-shaped link marker with a status mark, matching the design's farm pins.
 */
function createPin(farm: FarmSummary): HTMLAnchorElement {
    const tone = statusTone(farm.status);
    const pin = document.createElement('a');
    pin.href = route('farms.show', farm.id);
    pin.className =
        'flex min-h-11 items-center gap-2 rounded-full border border-input bg-card py-0 pr-3 pl-1.5 text-sm font-semibold whitespace-nowrap text-foreground no-underline shadow-sm';
    const mark = document.createElement('span');
    mark.className = 'inline-flex size-7 items-center justify-center rounded-full text-sm font-bold text-white';
    mark.style.background = toneColors[tone];
    mark.setAttribute('aria-hidden', 'true');
    mark.textContent = pinMarks[tone];
    pin.append(mark, document.createTextNode(farm.name));
    pin.setAttribute('aria-label', `${farm.name}, status ${farm.status}`);
    return pin;
}

const positions = locatedFarms.value.map((farm): [number, number] => [farm.center!.lng, farm.center!.lat]);

const { whenLoaded } = useMapbox(mapContainer, {
    style: 'outdoors',
    fullscreen: false,
    compass: false,
    ...(positions.length > 1
        ? {
              bounds: positions.reduce((bounds, position) => bounds.extend(position), new mapboxgl.LngLatBounds()),
              fitBoundsOptions: { padding: 80, maxZoom: 13 },
          }
        : { center: positions[0], zoom: 9 }),
});

whenLoaded((map) => {
    locatedFarms.value.forEach((farm, index) => {
        new mapboxgl.Marker({ element: createPin(farm), anchor: 'left' }).setLngLat(positions[index]).addTo(map);
    });
});
</script>

<template>
    <div class="relative h-[340px] overflow-hidden rounded-md bg-secondary">
        <div v-if="locatedFarms.length > 0" ref="mapContainer" class="h-full w-full"></div>
        <div v-else class="flex h-full items-center justify-center p-6 text-center text-body text-muted-foreground">
            Draw a boundary on a farm to see it on the map.
        </div>
    </div>
</template>
