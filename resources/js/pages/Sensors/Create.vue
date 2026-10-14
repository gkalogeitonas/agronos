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
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ScanLine } from 'lucide-vue-next';
import { computed, reactive, ref, watch, onMounted } from 'vue';
import { QrcodeStream, type DetectedBarcode } from 'vue-qrcode-reader';
import { MapboxMap, MapboxMarker } from '@studiometa/vue-mapbox-gl';
import Switch from '@/components/ui/switch/Switch.vue';
import 'mapbox-gl/dist/mapbox-gl.css';

interface Farm {
    id: number;
    name: string;
}

const props = defineProps<{
    farms: Farm[];
    selectedFarm: Farm | null;
    SensorTypes: string[];
}>();

const page = usePage();
const SensorTypes = computed(() => props.SensorTypes ?? []);
const farms = computed(() => props.farms ?? []);
const errors = computed(() => page.props.errors as Record<string, string | undefined>);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sensors', href: '/sensors' },
    { title: 'Add sensor', href: route('sensors.create') },
];

const form = reactive({
    name: '',
    farm_id: '' as number | '',
    type: '',
    uuid: '',
    device_uuid: '',
    lat: '',
    lon: '',
});

const showScanner = ref(false);
const scanError = ref<string | null>(null);
const locationError = ref<string | null>(null);
const mapboxToken = import.meta.env.VITE_MAPBOX_TOKEN;

const allowEdit = ref(false);

function submit() {
    //router.post(route('sensors.store'), form);
    router.post(route('sensors.scan'), form);
}

function onDetect(detectedCodes: DetectedBarcode[]) {
    if (detectedCodes.length === 0) {
        return;
    }
    try {
        const data = JSON.parse(detectedCodes[0].rawValue);
        if (data.uuid) form.uuid = data.uuid;
        if (data.device_uuid) form.device_uuid = data.device_uuid;
        if (data.type) form.type = data.type;
        showScanner.value = false;
        scanError.value = null;
    } catch {
        scanError.value = 'That QR code is not an Agronos sensor code.';
    }
}

function onQrError(error: Error) {
    scanError.value = `The camera could not start: ${error.message}`;
}

function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                form.lat = position.coords.latitude.toString();
                form.lon = position.coords.longitude.toString();
            },
            (err) => {
                locationError.value = `Could not get your location: ${err.message}. Enter it manually.`;
            },
        );
    } else {
        locationError.value = 'This browser cannot share its location. Enter it manually.';
    }
}

// Optionally, get location on mount
getLocation();

onMounted(() => {
    if (props.selectedFarm) {
        form.farm_id = props.selectedFarm.id;
    }
});

watch(
    () => props.selectedFarm,
    (selectedFarm) => {
        if (selectedFarm) {
            form.farm_id = selectedFarm.id;
        }
    },
);
</script>

<template>
    <Head title="Add sensor" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 md:p-8">
            <PageHeader eyebrow="Sensors" title="Add sensor">
                <template #actions>
                    <Button variant="outline" @click="showScanner = !showScanner"
                        ><ScanLine /> {{ showScanner ? 'Close scanner' : 'Scan sensor QR' }}</Button
                    >
                </template>
            </PageHeader>

            <SectionCard v-if="showScanner" title="Scan the code on the sensor" class="max-w-2xl">
                <QrcodeStream class="overflow-hidden rounded-md" @detect="onDetect" @error="onQrError" />
            </SectionCard>
            <p v-if="scanError" role="alert" class="m-0 max-w-2xl rounded-md bg-danger-soft p-3 text-sm text-danger-ink">{{ scanError }}</p>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <div class="flex flex-wrap items-start gap-4">
                    <SectionCard title="Sensor" class="flex-[1_1_320px]">
                        <div class="flex flex-col gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" placeholder="Zone A soil probe" />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="farm_id">Farm</Label>
                            <NativeSelect v-if="!selectedFarm" id="farm_id" v-model="form.farm_id" required>
                                <option value="" disabled>Select a farm</option>
                                <option v-for="farm in farms" :key="farm.id" :value="farm.id">{{ farm.name }}</option>
                            </NativeSelect>
                            <Input v-else id="farm_id" :model-value="selectedFarm.name" readonly class="bg-muted" />
                            <InputError :message="errors.farm_id" />
                        </div>
                        <div class="flex items-center gap-3">
                            <Switch id="allow-edit-switch" v-model="allowEdit" />
                            <Label for="allow-edit-switch" class="cursor-pointer">Edit the scanned fields manually</Label>
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="type">Type</Label>
                            <NativeSelect v-if="allowEdit" id="type" v-model="form.type" required>
                                <option value="" disabled>Select type</option>
                                <option v-for="t in SensorTypes" :key="t" :value="t">{{ sensorTypeLabel(t) }}</option>
                            </NativeSelect>
                            <Input v-else id="type" :model-value="form.type ? sensorTypeLabel(form.type) : 'Scan the QR code'" disabled />
                            <InputError :message="errors.type" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="uuid">Sensor UUID</Label>
                            <Input id="uuid" v-model="form.uuid" :disabled="!allowEdit" required class="font-mono" />
                            <InputError :message="errors.uuid" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="device_uuid">Device UUID</Label>
                            <Input id="device_uuid" v-model="form.device_uuid" :disabled="!allowEdit" required class="font-mono" />
                            <InputError :message="errors.device_uuid" />
                        </div>
                    </SectionCard>

                    <SectionCard title="Location" class="flex-[2_1_480px]">
                        <p class="m-0 text-sm text-muted-foreground">
                            Your current location is used by default. Stand next to the sensor, or enter it manually.
                        </p>
                        <p v-if="locationError" role="alert" class="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning-foreground">
                            {{ locationError }}
                        </p>
                        <MapboxMap
                            v-if="form.lat && form.lon"
                            class="h-[320px] w-full overflow-hidden rounded-md border"
                            :access-token="mapboxToken"
                            map-style="mapbox://styles/mapbox/satellite-streets-v12"
                            :center="[parseFloat(form.lon), parseFloat(form.lat)]"
                            :zoom="15"
                        >
                            <MapboxMarker :lng-lat="[parseFloat(form.lon), parseFloat(form.lat)]" color="#1F6FA8" />
                        </MapboxMap>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="flex flex-col gap-2">
                                <Label for="lat">Latitude</Label>
                                <Input id="lat" v-model="form.lat" type="number" step="any" required class="font-mono" />
                                <InputError :message="errors.lat" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <Label for="lon">Longitude</Label>
                                <Input id="lon" v-model="form.lon" type="number" step="any" required class="font-mono" />
                                <InputError :message="errors.lon" />
                            </div>
                        </div>
                    </SectionCard>
                </div>

                <div class="flex justify-end gap-3">
                    <Button variant="outline" as-child><Link :href="route('sensors.index')">Cancel</Link></Button>
                    <Button type="submit">Add sensor</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
