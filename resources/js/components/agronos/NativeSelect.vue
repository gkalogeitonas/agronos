<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<string | number>();
</script>

<template>
    <select
        v-model="model"
        :class="
            cn(
                'h-11 rounded-md border border-input bg-card px-3 text-[15px] text-foreground outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive',
                props.class,
            )
        "
    >
        <slot />
    </select>
</template>
