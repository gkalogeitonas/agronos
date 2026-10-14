<script setup lang="ts">
import SectionCard from '@/components/agronos/SectionCard.vue';
import { ref } from 'vue';
import { QrcodeStream, type DetectedBarcode } from 'vue-qrcode-reader';

export type QrPayload = Record<string, string | undefined>;

interface Props {
    title: string;
    /** Shown when a scanned code is not a JSON object. */
    invalidMessage: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    scan: [payload: QrPayload];
}>();

const error = ref<string | null>(null);

/**
 * Parses the QR text as a JSON object, or returns null for anything else.
 */
function parsePayload(rawValue: string): QrPayload | null {
    try {
        const data: unknown = JSON.parse(rawValue);

        return data !== null && typeof data === 'object' && !Array.isArray(data) ? (data as QrPayload) : null;
    } catch {
        return null;
    }
}

function onDetect(detectedCodes: DetectedBarcode[]): void {
    if (detectedCodes.length === 0) {
        return;
    }
    const payload = parsePayload(detectedCodes[0].rawValue);
    if (payload === null) {
        error.value = props.invalidMessage;
        return;
    }
    error.value = null;
    emit('scan', payload);
}

function onCameraError(cause: Error): void {
    error.value = `The camera could not start: ${cause.message}`;
}
</script>

<template>
    <div class="flex max-w-2xl flex-col gap-4">
        <SectionCard :title="title">
            <QrcodeStream class="overflow-hidden rounded-md" @detect="onDetect" @error="onCameraError" />
        </SectionCard>
        <p v-if="error" role="alert" class="m-0 rounded-md bg-danger-soft p-3 text-sm text-danger-ink">{{ error }}</p>
    </div>
</template>
