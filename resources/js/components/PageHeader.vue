<script setup lang="ts">
interface Props {
    title: string;
    eyebrow?: string;
    /** Shows the SAMPLE DATA pill when the page renders placeholder data from `@/lib/sampleData`. */
    sampleData?: boolean;
}

withDefaults(defineProps<Props>(), {
    eyebrow: undefined,
    sampleData: false,
});
</script>

<template>
    <header class="flex flex-col gap-3">
        <slot name="breadcrumb" />
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1">
                <div v-if="eyebrow" class="eyebrow text-xs">{{ eyebrow }}</div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="m-0 text-title leading-tight font-bold">{{ title }}</h1>
                    <slot name="meta" />
                </div>
            </div>
            <div v-if="sampleData || $slots.actions" class="flex flex-wrap items-center gap-3">
                <span v-if="sampleData" class="rounded-full bg-warning-soft px-2.5 py-1.5 font-mono text-xs text-warning-foreground"
                    >SAMPLE DATA</span
                >
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
