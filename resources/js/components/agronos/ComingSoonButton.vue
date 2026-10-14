<script setup lang="ts">
import { Button, type ButtonVariants } from '@/components/ui/button';

/**
 * A disabled button for a feature that is not built yet. The reason is shown as a tooltip on the wrapper,
 * because disabled buttons receive no pointer events, and is read out to screen readers.
 */
defineProps<{
    reason: string;
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
}>();
</script>

<template>
    <span class="inline-flex cursor-not-allowed" :title="reason">
        <Button :variant="variant" :size="size" disabled>
            <slot />
            <span class="sr-only">({{ reason }})</span>
        </Button>
    </span>
</template>
