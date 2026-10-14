<script setup lang="ts">
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { sensorTypeLabel } from '@/lib/theme';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { Polygon } from 'geojson';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface FarmOption {
    id: number;
    name: string;
    coordinates: Polygon | null;
}

const props = defineProps<{
    sensor: {
        id: number;
        name: string;
        uuid: string;
        type: string;
        farm_id: number | null;
        lat: number | string;
        lon: number | string;
        device?: { uuid: string } | null;
    };
    farms: FarmOption[];
}>();

const sensor = props.sensor;
const farms = computed(() => props.farms);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sensors', href: '/sensors' },
    { title: sensor.name || 'Sensor', href: route('sensors.show', sensor.id) },
    { title: 'Edit', href: route('sensors.edit', sensor.id) },
];

const form = useForm({
    name: sensor.name || '',
    farm_id: sensor.farm_id ?? ('' as number | ''),
    lat: String(sensor.lat ?? ''),
    lon: String(sensor.lon ?? ''),
});

function submit() {
    form.put(route('sensors.update', sensor.id));
}

function addFarmPolygon(farm: FarmOption) {
    if (!map || !farm.coordinates) return;

    // Remove existing farm polygon if it exists
    if (map.getSource('farm-area')) {
        map.removeLayer('farm-fill');
        map.removeLayer('farm-outline');
        map.removeSource('farm-area');
    }

    // Add farm polygon
    map.addSource('farm-area', {
        type: 'geojson',
        data: {
            type: 'Feature',
            properties: {},
            geometry: farm.coordinates!,
        },
    });

    map.addLayer({
        id: 'farm-fill',
        type: 'fill',
        source: 'farm-area',
        layout: {},
        paint: {
            'fill-color': '#1F7A4D',
            'fill-opacity': 0.2,
        },
    });

    map.addLayer({
        id: 'farm-outline',
        type: 'line',
        source: 'farm-area',
        layout: {},
        paint: {
            'line-color': '#1F7A4D',
            'line-width': 2,
        },
    });

    // Fit the map to show the farm polygon
    const bounds = new mapboxgl.LngLatBounds();
    farm.coordinates!.coordinates[0].forEach((coord) => bounds.extend(coord as [number, number]));
    map.fitBounds(bounds, { padding: 40, maxZoom: 17 });
}

const mapContainer = ref<HTMLElement | null>(null);
let map: mapboxgl.Map | null = null;
let marker: mapboxgl.Marker | null = null;

onMounted(() => {
    if (!mapContainer.value) {
        console.error('Map container not found');
        return;
    }

    mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string;

    map = new mapboxgl.Map({
        container: mapContainer.value,
        style: 'mapbox://styles/mapbox/satellite-streets-v12',
        center: [parseFloat(form.lon) || 0, parseFloat(form.lat) || 0],
        zoom: 15,
    });

    // Add navigation controls
    map.addControl(new mapboxgl.NavigationControl());
    map.addControl(new mapboxgl.FullscreenControl());

    // Add farm polygon if available
    map.on('load', () => {
        const selectedFarm = farms.value.find((f) => f.id == form.farm_id);
        if (selectedFarm && selectedFarm.coordinates && selectedFarm.coordinates.type === 'Polygon') {
            addFarmPolygon(selectedFarm);
        }
    });

    // Add marker at sensor location
    if (form.lat && form.lon) {
        marker = new mapboxgl.Marker({ draggable: true, color: '#1F6FA8' }).setLngLat([parseFloat(form.lon), parseFloat(form.lat)]).addTo(map);

        // Update form when marker is dragged
        marker.on('dragend', () => {
            const lngLat = marker!.getLngLat();
            form.lat = lngLat.lat.toString();
            form.lon = lngLat.lng.toString();
        });
    }

    // Move marker when map is clicked
    map.on('click', (e) => {
        const { lng, lat } = e.lngLat;
        form.lat = lat.toString();
        form.lon = lng.toString();

        if (marker) {
            marker.setLngLat([lng, lat]);
        } else {
            marker = new mapboxgl.Marker({ draggable: true, color: '#1F6FA8' }).setLngLat([lng, lat]).addTo(map!);

            marker.on('dragend', () => {
                const lngLat = marker!.getLngLat();
                form.lat = lngLat.lat.toString();
                form.lon = lngLat.lng.toString();
            });
        }
    });
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
    }
});

// Watch for farm selection changes and update polygon
watch(
    () => form.farm_id,
    (newFarmId) => {
        if (map && map.loaded()) {
            const selectedFarm = farms.value.find((f) => f.id == newFarmId);
            if (selectedFarm && selectedFarm.coordinates && selectedFarm.coordinates.type === 'Polygon') {
                addFarmPolygon(selectedFarm);
            } else if (map.getSource('farm-area')) {
                // Remove farm polygon if no farm is selected or farm has no coordinates
                map.removeLayer('farm-fill');
                map.removeLayer('farm-outline');
                map.removeSource('farm-area');
            }
        }
    },
);
</script>

<template>
    <Head :title="`Edit ${sensor.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 md:p-8">
            <PageHeader eyebrow="Edit sensor" :title="sensor.name || 'Sensor'" />

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <div class="flex flex-wrap items-start gap-4">
                    <SectionCard title="Details" class="flex-[1_1_320px]">
                        <dl class="m-0 flex flex-col">
                            <div class="flex justify-between gap-4 border-b border-row py-3">
                                <dt class="text-sm text-muted-foreground">Sensor UUID</dt>
                                <dd class="m-0 min-w-0 truncate font-mono text-sm" :title="sensor.uuid">{{ sensor.uuid }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 border-b border-row py-3">
                                <dt class="text-sm text-muted-foreground">Device UUID</dt>
                                <dd class="m-0 min-w-0 truncate font-mono text-sm">{{ sensor.device?.uuid || '–' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-3">
                                <dt class="text-sm text-muted-foreground">Type</dt>
                                <dd class="m-0 text-[15px]">{{ sensorTypeLabel(sensor.type) }}</dd>
                            </div>
                        </dl>
                        <div class="flex flex-col gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="farm_id">Farm</Label>
                            <NativeSelect id="farm_id" v-model="form.farm_id">
                                <option value="" disabled>Select a farm</option>
                                <option v-for="farm in farms" :key="farm.id" :value="farm.id">{{ farm.name }}</option>
                            </NativeSelect>
                            <InputError :message="form.errors.farm_id" />
                        </div>
                    </SectionCard>

                    <SectionCard title="Location" class="flex-[2_1_480px]">
                        <p class="m-0 text-sm text-muted-foreground">Click the map or drag the marker to move the sensor.</p>
                        <div ref="mapContainer" class="h-[320px] w-full overflow-hidden rounded-md border"></div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="flex flex-col gap-2">
                                <Label for="lat">Latitude</Label>
                                <Input id="lat" v-model="form.lat" type="number" step="any" required class="font-mono" />
                                <InputError :message="form.errors.lat" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <Label for="lon">Longitude</Label>
                                <Input id="lon" v-model="form.lon" type="number" step="any" required class="font-mono" />
                                <InputError :message="form.errors.lon" />
                            </div>
                        </div>
                    </SectionCard>
                </div>

                <div class="flex justify-end gap-3">
                    <Button variant="outline" as-child><Link :href="route('sensors.show', sensor.id)">Cancel</Link></Button>
                    <Button type="submit" :disabled="form.processing">Save changes</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
