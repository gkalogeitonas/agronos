<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

interface Props {
    /** Id of the control in the default slot, used for the label's `for`. */
    id: string;
    label: string;
    error?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <Label :for="id">{{ label }}</Label>
        <slot />
        <InputError :message="error" />
    </div>
</template>
