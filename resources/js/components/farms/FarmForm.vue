<script setup lang="ts">
import SectionCard from '@/components/agronos/SectionCard.vue';
import FarmBoundaryEditor from '@/components/FarmBoundaryEditor.vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { area } from '@turf/turf';
import type { Polygon } from 'geojson';
import { computed, watch } from 'vue';

export interface FarmFormData {
    name: string;
    location: string;
    size: number | string;
    coordinates: Polygon | null;
    description: string;
}

/** Map centre used when the farm has no boundary yet (Athens). */
const DEFAULT_CENTER = { lng: 23.7275, lat: 37.9838 };

/**
 * Create or edit form for a farm. Owns the Inertia form; the area is calculated from the drawn boundary.
 */
const props = defineProps<{
    /** Existing farm to edit; omit to create a new one. */
    farm?: {
        name: string;
        location: string | null;
        size: number | null;
        coordinates: Polygon | null;
        description: string | null;
        center: { lng: number; lat: number } | null;
    };
    /** URL the form submits to. */
    action: string;
    method: 'post' | 'put';
}>();

const form = useForm<FarmFormData>({
    name: props.farm?.name ?? '',
    location: props.farm?.location ?? '',
    size: props.farm?.size ?? '',
    coordinates: props.farm?.coordinates ?? null,
    description: props.farm?.description ?? '',
});

const center = props.farm?.center ?? DEFAULT_CENTER;

const calculatedArea = computed(() =>
    form.coordinates?.type === 'Polygon' ? area({ type: 'Feature', properties: {}, geometry: form.coordinates }) : null,
);

watch(
    calculatedArea,
    (value) => {
        form.size = value ?? '';
    },
    { immediate: true },
);

function submit(): void {
    if (props.method === 'put') {
        form.put(props.action);
    } else {
        form.post(props.action);
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="flex flex-wrap items-start gap-4">
            <SectionCard title="Details" class="flex-[1_1_320px]">
                <FormField id="farm-name" label="Name" :error="form.errors.name">
                    <Input id="farm-name" v-model="form.name" required placeholder="North Orchard" />
                </FormField>
                <FormField id="farm-location" label="Location" :error="form.errors.location">
                    <Input id="farm-location" v-model="form.location" required placeholder="Town or region" />
                </FormField>
                <FormField id="farm-description" label="Description" :error="form.errors.description">
                    <Textarea
                        id="farm-description"
                        v-model="form.description"
                        placeholder="Crops, soil, anything worth remembering"
                        class="bg-card"
                    />
                </FormField>
            </SectionCard>

            <SectionCard title="Boundary" class="flex-[2_1_480px]">
                <p class="m-0 text-sm text-muted-foreground">Draw the farm's outline on the map. The area is calculated from it.</p>
                <FarmBoundaryEditor :lng="center.lng" :lat="center.lat" :polygon="form.coordinates" @update:polygon="form.coordinates = $event" />
                <div class="flex items-baseline gap-2 font-mono text-sm">
                    <span class="eyebrow text-xs">Area</span>
                    <span>{{
                        calculatedArea !== null ? `${calculatedArea.toLocaleString(undefined, { maximumFractionDigits: 2 })} m²` : 'No boundary yet'
                    }}</span>
                </div>
                <InputError :message="form.errors.coordinates" />
            </SectionCard>
        </div>

        <div class="flex justify-end gap-3">
            <slot name="cancel" />
            <Button type="submit" :disabled="form.processing">{{ farm ? 'Save changes' : 'Create farm' }}</Button>
        </div>
    </form>
</template>
