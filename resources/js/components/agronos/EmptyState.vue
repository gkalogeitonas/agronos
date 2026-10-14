<script setup lang="ts">
interface Props {
    title?: string;
    text: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed bg-card p-8 text-center">
        <h2 v-if="title" class="m-0 text-lg font-semibold">{{ title }}</h2>
        <p class="m-0 text-body text-muted-foreground">{{ text }}</p>
        <slot />
    </div>
</template>
