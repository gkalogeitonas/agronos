import type { FlashMessages } from '@/types';
import { router } from '@inertiajs/vue3';
import { readonly, ref } from 'vue';

export type ToastKind = keyof FlashMessages;

export interface Toast {
    id: number;
    kind: ToastKind;
    message: string;
}

const DISMISS_AFTER_MS = 5000;
const KINDS: ToastKind[] = ['success', 'error', 'info'];

/**
 * Toasts live at module level so they survive Inertia visits.
 */
const toasts = ref<Toast[]>([]);
let nextId = 0;

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function show(flash: FlashMessages | undefined): void {
    KINDS.forEach((kind) => {
        const message = flash?.[kind];

        if (!message) {
            return;
        }

        const id = nextId++;
        toasts.value.push({ id, kind, message });
        setTimeout(() => dismiss(id), DISMISS_AFTER_MS);
    });
}

/**
 * Shows controller flash messages as toasts. Inertia fires `flash` for the
 * first page and for every later response that carries flash data, and keeps
 * flash data out of history state and deferred reloads, so a toast never
 * repeats. Call once at boot, before the app mounts.
 */
export function listenForFlashToasts(): void {
    router.on('flash', (event) => show(event.detail.flash));
}

export function useFlashToasts() {
    return { toasts: readonly(toasts), dismiss };
}
