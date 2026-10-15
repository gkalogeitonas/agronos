<script setup lang="ts">
import { useFlashToasts, type ToastKind } from '@/composables/useFlashToasts';
import { CircleAlert, CircleCheck, Info, X } from 'lucide-vue-next';
import type { Component } from 'vue';

const { toasts, dismiss } = useFlashToasts();

const kinds: Record<ToastKind, { icon: Component; classes: string }> = {
    success: { icon: CircleCheck, classes: 'border-success/40 bg-success-soft text-success-foreground' },
    error: { icon: CircleAlert, classes: 'border-destructive/40 bg-danger-soft text-danger-ink' },
    info: { icon: Info, classes: 'border-info/40 bg-info-soft text-info-foreground' },
};
</script>

<template>
    <div aria-live="polite" class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:left-auto">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :role="toast.kind === 'error' ? 'alert' : 'status'"
            :class="['pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-md border p-3 text-sm shadow-md', kinds[toast.kind].classes]"
        >
            <component :is="kinds[toast.kind].icon" class="mt-0.5 size-4 shrink-0" />
            <p class="m-0 flex-1">{{ toast.message }}</p>
            <button type="button" class="shrink-0 opacity-70 hover:opacity-100" aria-label="Dismiss" @click="dismiss(toast.id)">
                <X class="size-4" />
            </button>
        </div>
    </div>
</template>
