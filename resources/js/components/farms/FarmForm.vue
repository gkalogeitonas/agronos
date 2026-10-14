<script setup lang="ts">
import SectionCard from '@/components/agronos/SectionCard.vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import MapboxMap from '@/components/MapboxMap.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { InertiaForm } from '@inertiajs/vue3';
import { area } from '@turf/turf';
import type { Polygon } from 'geojson';
import { ref, watch } from 'vue';

export interface FarmFormData {
    name: string;
    location: string;
    size: number | string;
    coordinates: Polygon | null;
    description: string;
}

const props = withDefaults(
    defineProps<{
        form: InertiaForm<FarmFormData>;
        polygon: Polygon | null;
        onUpdatePolygon: (polygon: Polygon | null) => void;
        onSubmit: () => void;
        isEdit?: boolean;
        defaultLng?: number;
        defaultLat?: number;
    }>(),
    {
        isEdit: false,
        defaultLng: 23.7275,
        defaultLat: 37.9838,
    },
);

const calculatedArea = ref<number | null>(null);

watch(
    () => props.polygon,
    (polygon) => {
        if (polygon && polygon.type === 'Polygon') {
            calculatedArea.value = area({ type: 'Feature', properties: {}, geometry: polygon });
            props.form.size = calculatedArea.value;
        } else {
            calculatedArea.value = null;
            props.form.size = '';
        }
    },
    { immediate: true },
);
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="onSubmit">
        <div class="flex flex-wrap items-start gap-4">
            <SectionCard title="Details" class="flex-[1_1_320px]">
                <FormField id="farm-name" label="Name" :error="form.errors.name"
                    ><Input id="farm-name" v-model="form.name" required placeholder="North Orchard"
                /></FormField>
                <FormField id="farm-location" label="Location" :error="form.errors.location"
                    ><Input id="farm-location" v-model="form.location" required placeholder="Town or region"
                /></FormField>
                <FormField id="farm-description" label="Description" :error="form.errors.description"
                    ><Textarea id="farm-description" v-model="form.description" placeholder="Crops, soil, anything worth remembering" class="bg-card"
                /></FormField>
            </SectionCard>

            <SectionCard title="Boundary" class="flex-[2_1_480px]">
                <p class="m-0 text-sm text-muted-foreground">Draw the farm's outline on the map. The area is calculated from it.</p>
                <MapboxMap :lng="defaultLng" :lat="defaultLat" :polygon="polygon" :editable="true" @update:polygon="onUpdatePolygon" />
                <div class="flex items-baseline gap-2 font-mono text-sm">
                    <span class="text-xs tracking-[0.06em] text-muted-foreground uppercase">Area</span>
                    <span>{{
                        calculatedArea !== null ? `${calculatedArea.toLocaleString(undefined, { maximumFractionDigits: 2 })} m²` : 'No boundary yet'
                    }}</span>
                </div>
                <InputError :message="form.errors.coordinates" />
            </SectionCard>
        </div>

        <div class="flex justify-end gap-3">
            <slot name="cancel" />
            <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Save changes' : 'Create farm' }}</Button>
        </div>
    </form>
</template>
