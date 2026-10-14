<script setup lang="ts">
import NativeSelect from '@/components/agronos/NativeSelect.vue';

defineOptions({ inheritAttrs: false });

defineProps<{
    label: string;
}>();

const model = defineModel<string | number>();
</script>

<template>
    <label class="inline-flex items-center gap-2.5 text-sm text-muted-foreground">
        {{ label }}
        <NativeSelect v-model="model" v-bind="$attrs">
            <slot />
        </NativeSelect>
    </label>
</template>
