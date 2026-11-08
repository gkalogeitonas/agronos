<script setup lang="ts">
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import FormField from '@/components/FormField.vue';

export interface ZoneOption {
    id: number;
    name: string;
}

defineProps<{
    /** The zones of the farm the sensor's device is on. */
    zones: ZoneOption[];
    /** Why no zone can be chosen yet, such as the device being unassigned; disables the select. */
    unavailableHint?: string | null;
    error?: string;
}>();

/** The chosen zone's id, or '' for no zone. */
const zoneId = defineModel<number | ''>({ required: true });
</script>

<template>
    <FormField id="zone_id" label="Zone" :error="error"
        ><NativeSelect id="zone_id" v-model="zoneId" :disabled="!!unavailableHint" :aria-describedby="unavailableHint ? 'zone_id-hint' : undefined">
            <option value="">No zone</option>
            <option v-for="zone in zones" :key="zone.id" :value="zone.id">{{ zone.name }}</option>
        </NativeSelect>
        <p v-if="unavailableHint" id="zone_id-hint" class="m-0 text-sm text-muted-foreground">{{ unavailableHint }}</p></FormField
    >
</template>
