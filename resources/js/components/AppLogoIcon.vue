<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        :class="className"
        v-bind="$attrs"
    >
        <path d="M5 19c0-8 5-13 14-14 0 9-5 14-13 14" />
        <path d="M5 19c3-4 6-7 9-9" />
    </svg>
</template>
