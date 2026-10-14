<script setup lang="ts" generic="Value extends string">
export interface FilterChipOption<V extends string = string> {
    value: V;
    label: string;
    count?: number;
}

defineProps<{
    options: FilterChipOption<Value>[];
    label: string;
}>();

const model = defineModel<Value>({ required: true });
</script>

<template>
    <div role="group" :aria-label="label" class="flex flex-wrap gap-2">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="model === option.value"
            class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            :class="model === option.value ? 'border-foreground bg-foreground text-white' : 'border-input bg-card text-foreground hover:bg-accent'"
            @click="model = option.value"
        >
            {{ option.label }}
            <span v-if="option.count !== undefined" class="font-mono text-xs opacity-75">{{ option.count }}</span>
        </button>
    </div>
</template>
