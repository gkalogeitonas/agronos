<script setup lang="ts">
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ScanLine } from 'lucide-vue-next';
import { ref } from 'vue';
import { QrcodeStream, type DetectedBarcode } from 'vue-qrcode-reader';

defineProps<{
    deviceTypes: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devices', href: '/devices' },
    { title: 'Add device', href: '/devices/create' },
];

const form = useForm({
    name: '',
    uuid: '',
    secret: '',
    type: '',
    lora_aes_key: '',
});

const showScanner = ref(false);
const scanError = ref<string | null>(null);

function submit() {
    form.post(route('devices.store'));
}

function onDetect(detectedCodes: DetectedBarcode[]) {
    if (detectedCodes.length === 0) {
        return;
    }
    try {
        const data = JSON.parse(detectedCodes[0].rawValue);
        if (data.uuid) form.uuid = data.uuid;
        if (data.secret) form.secret = data.secret;
        if (data.type) form.type = data.type;
        showScanner.value = false;
        scanError.value = null;
    } catch {
        scanError.value = 'That QR code is not an Agronos device code.';
    }
}

function onQrError(error: Error) {
    scanError.value = `The camera could not start: ${error.message}`;
}
</script>

<template>
    <Head title="Add device" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 md:p-8">
            <PageHeader eyebrow="Devices" title="Add device">
                <template #actions>
                    <Button variant="outline" @click="showScanner = !showScanner"
                        ><ScanLine /> {{ showScanner ? 'Close scanner' : 'Scan device QR' }}</Button
                    >
                </template>
            </PageHeader>

            <SectionCard v-if="showScanner" title="Scan the code on the device" class="max-w-2xl">
                <QrcodeStream class="overflow-hidden rounded-md" @detect="onDetect" @error="onQrError" />
            </SectionCard>
            <p v-if="scanError" role="alert" class="m-0 max-w-2xl rounded-md bg-danger-soft p-3 text-sm text-danger-ink">{{ scanError }}</p>

            <SectionCard title="Device details" class="max-w-2xl">
                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <div class="flex flex-col gap-2">
                        <Label for="name">Device name</Label>
                        <Input id="name" v-model="form.name" placeholder="Sensor node 01" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="uuid">UUID</Label>
                        <Input id="uuid" v-model="form.uuid" required class="font-mono" />
                        <InputError :message="form.errors.uuid" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="secret">Secret</Label>
                        <Input id="secret" v-model="form.secret" type="password" required />
                        <InputError :message="form.errors.secret" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="type">Type</Label>
                        <NativeSelect id="type" v-model="form.type" required>
                            <option value="" disabled>Select type</option>
                            <option v-for="type in deviceTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </NativeSelect>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div v-if="form.type === 'lora'" class="flex flex-col gap-2">
                        <Label for="lora_aes_key">AES key (32-character hex)</Label>
                        <Input
                            id="lora_aes_key"
                            v-model="form.lora_aes_key"
                            placeholder="0123456789abcdef0123456789abcdef"
                            maxlength="32"
                            class="font-mono"
                        />
                        <InputError :message="form.errors.lora_aes_key" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child><Link :href="route('devices.index')">Cancel</Link></Button>
                        <Button type="submit" :disabled="form.processing">Register device</Button>
                    </div>
                </form>
            </SectionCard>
        </div>
    </AppLayout>
</template>
