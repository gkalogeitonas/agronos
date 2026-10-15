<script setup lang="ts">
import DescriptionList, { type DescriptionItem } from '@/components/agronos/DescriptionList.vue';
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import FormField from '@/components/FormField.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SensorLocationPicker from '@/components/SensorLocationPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { sensorTypeLabel } from '@/lib/enums';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { Polygon } from 'geojson';
import { computed } from 'vue';

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
    { title: 'Sensors', href: route('sensors.index') },
    { title: sensor.name || 'Sensor', href: route('sensors.show', sensor.id) },
    { title: 'Edit', href: route('sensors.edit', sensor.id) },
];

const details: DescriptionItem[] = [
    { label: 'Sensor UUID', value: sensor.uuid, mono: true },
    { label: 'Device UUID', value: sensor.device?.uuid, mono: true },
    { label: 'Type', value: sensorTypeLabel(sensor.type) },
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

const farmPolygon = computed(() => farms.value.find((farm) => farm.id === form.farm_id)?.coordinates ?? null);
</script>

<template>
    <Head :title="`Edit ${sensor.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Edit sensor" :title="sensor.name || 'Sensor'" />

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <div class="flex flex-wrap items-start gap-4">
                    <SectionCard title="Details" class="flex-[1_1_320px]">
                        <DescriptionList :items="details" />
                        <FormField id="name" label="Name" :error="form.errors.name"><Input id="name" v-model="form.name" /></FormField>
                        <FormField id="farm_id" label="Farm" :error="form.errors.farm_id"
                            ><NativeSelect id="farm_id" v-model="form.farm_id">
                                <option value="" disabled>Select a farm</option>
                                <option v-for="farm in farms" :key="farm.id" :value="farm.id">{{ farm.name }}</option>
                            </NativeSelect></FormField
                        >
                    </SectionCard>

                    <SectionCard title="Location" class="flex-[2_1_480px]">
                        <p class="m-0 text-sm text-muted-foreground">Click the map or drag the marker to move the sensor.</p>
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
                    <Button variant="outline" as-child><Link :href="route('sensors.show', sensor.id)">Cancel</Link></Button>
                    <Button type="submit" :disabled="form.processing">Save changes</Button>
                </div>
            </form>
        </PageContainer>
    </AppLayout>
</template>
