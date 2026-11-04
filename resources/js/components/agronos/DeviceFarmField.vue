<script setup lang="ts">
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import FormField from '@/components/FormField.vue';
import type { FarmOption } from '@/types';

defineProps<{
    farms: FarmOption[];
    error?: string;
}>();

/** The chosen farm's id, or '' while the device is unassigned. */
const farmId = defineModel<number | ''>({ required: true });
</script>

<template>
    <FormField id="farm_id" label="Farm" :error="error"
        ><NativeSelect id="farm_id" v-model="farmId">
            <option value="">Unassigned</option>
            <option v-for="farm in farms" :key="farm.id" :value="farm.id">{{ farm.name }}</option>
        </NativeSelect></FormField
    >
</template>
