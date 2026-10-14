<script setup lang="ts">
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import QrScanCard, { type QrPayload } from '@/components/agronos/QrScanCard.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import FormField from '@/components/FormField.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SensorLocationPicker from '@/components/SensorLocationPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Switch from '@/components/ui/switch/Switch.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { sensorTypeLabel } from '@/lib/theme';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { Polygon } from 'geojson';
import { ScanLine } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface Farm {
    id: number;
    name: string;
    coordinates?: Polygon | null;
}

const props = defineProps<{
    farms: Farm[];
    selectedFarm: Farm | null;
    SensorTypes: string[];
}>();

const SensorTypes = computed(() => props.SensorTypes ?? []);
const farms = computed(() => props.farms ?? []);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sensors', href: '/sensors' },
    { title: 'Add sensor', href: route('sensors.create') },
];

const form = useForm({
    name: '',
    farm_id: '' as number | '',
    type: '',
    uuid: '',
    device_uuid: '',
    lat: '',
    lon: '',
});

const showScanner = ref(false);
const locationError = ref<string | null>(null);

const allowEdit = ref(false);

const farmPolygon = computed(() => {
    const farm = props.selectedFarm ?? farms.value.find((option) => option.id === form.farm_id);

    return farm?.coordinates ?? null;
});

function submit(): void {
    form.post(route('sensors.scan'));
}

function onScan(data: QrPayload): void {
    if (data.uuid) form.uuid = data.uuid;
    if (data.device_uuid) form.device_uuid = data.device_uuid;
    if (data.type) form.type = data.type;
    showScanner.value = false;
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

onMounted(getLocation);

watch(
    () => props.selectedFarm,
    (selectedFarm) => {
        if (selectedFarm) {
            form.farm_id = selectedFarm.id;
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Add sensor" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Sensors" title="Add sensor">
                <template #actions>
                    <Button variant="outline" @click="showScanner = !showScanner"
                        ><ScanLine /> {{ showScanner ? 'Close scanner' : 'Scan sensor QR' }}</Button
                    >
                </template>
            </PageHeader>

            <QrScanCard
                v-if="showScanner"
                title="Scan the code on the sensor"
                invalid-message="That QR code is not an Agronos sensor code."
                @scan="onScan"
            />

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <div class="flex flex-wrap items-start gap-4">
                    <SectionCard title="Sensor" class="flex-[1_1_320px]">
                        <FormField id="name" label="Name" :error="form.errors.name"
                            ><Input id="name" v-model="form.name" placeholder="Zone A soil probe"
                        /></FormField>
                        <FormField id="farm_id" label="Farm" :error="form.errors.farm_id"
                            ><NativeSelect v-if="!selectedFarm" id="farm_id" v-model="form.farm_id" required>
                                <option value="" disabled>Select a farm</option>
                                <option v-for="farm in farms" :key="farm.id" :value="farm.id">{{ farm.name }}</option>
                            </NativeSelect>
                            <Input v-else id="farm_id" :model-value="selectedFarm.name" readonly class="bg-muted"
                        /></FormField>
                        <div class="flex items-center gap-3">
                            <Switch id="allow-edit-switch" v-model="allowEdit" />
                            <Label for="allow-edit-switch" class="cursor-pointer">Edit the scanned fields manually</Label>
                        </div>
                        <FormField id="type" label="Type" :error="form.errors.type"
                            ><NativeSelect v-if="allowEdit" id="type" v-model="form.type" required>
                                <option value="" disabled>Select type</option>
                                <option v-for="t in SensorTypes" :key="t" :value="t">{{ sensorTypeLabel(t) }}</option>
                            </NativeSelect>
                            <Input v-else id="type" :model-value="form.type ? sensorTypeLabel(form.type) : 'Scan the QR code'" disabled
                        /></FormField>
                        <FormField id="uuid" label="Sensor UUID" :error="form.errors.uuid"
                            ><Input id="uuid" v-model="form.uuid" :disabled="!allowEdit" required class="font-mono"
                        /></FormField>
                        <FormField id="device_uuid" label="Device UUID" :error="form.errors.device_uuid"
                            ><Input id="device_uuid" v-model="form.device_uuid" :disabled="!allowEdit" required class="font-mono"
                        /></FormField>
                    </SectionCard>

                    <SectionCard title="Location" class="flex-[2_1_480px]">
                        <p class="m-0 text-sm text-muted-foreground">
                            Your current location is used by default. Stand next to the sensor, click the map, drag the marker, or enter it manually.
                        </p>
                        <p v-if="locationError" role="alert" class="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning-foreground">
                            {{ locationError }}
                        </p>
                        <SensorLocationPicker v-model:lat="form.lat" v-model:lon="form.lon" :farm-polygon="farmPolygon" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField id="lat" label="Latitude" :error="form.errors.lat"
                                ><Input id="lat" v-model="form.lat" type="number" step="any" required class="font-mono"
                            /></FormField>
                            <FormField id="lon" label="Longitude" :error="form.errors.lon"
                                ><Input id="lon" v-model="form.lon" type="number" step="any" required class="font-mono"
                            /></FormField>
                        </div>
                    </SectionCard>
                </div>

                <div class="flex justify-end gap-3">
                    <Button variant="outline" as-child><Link :href="route('sensors.index')">Cancel</Link></Button>
                    <Button type="submit" :disabled="form.processing">Add sensor</Button>
                </div>
            </form>
        </PageContainer>
    </AppLayout>
</template>
