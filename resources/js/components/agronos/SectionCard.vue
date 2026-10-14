<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

interface Props {
    title?: string;
    class?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <section :aria-label="title" class="flex min-w-0 flex-col gap-4 rounded-xl border bg-card p-6" :class="$props.class">
        <div v-if="title || $slots.actions" class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <h2 v-if="title" class="m-0 text-lg font-semibold">{{ title }}</h2>
                <slot name="title-extra" />
            </div>
            <slot name="actions" />
        </div>
        <slot />
    </section>
</template>
