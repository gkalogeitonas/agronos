<script setup lang="ts">
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import QrScanCard, { type QrPayload } from '@/components/agronos/QrScanCard.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import FormField from '@/components/FormField.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ScanLine } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    deviceTypes: { value: string; label: string }[];
    prefillUuid?: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devices', href: route('devices.index') },
    { title: 'Add device', href: route('devices.create') },
];

const form = useForm({
    name: '',
    uuid: props.prefillUuid ?? '',
    secret: '',
    type: '',
    lora_aes_key: '',
});

const showScanner = ref(false);

function submit() {
    form.post(route('devices.store'));
}

function onScan(data: QrPayload): void {
    if (data.uuid) form.uuid = data.uuid;
    if (data.secret) form.secret = data.secret;
    if (data.type) form.type = data.type;
    showScanner.value = false;
}
</script>

<template>
    <Head title="Add device" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Devices" title="Add device">
                <template #actions>
                    <Button variant="outline" @click="showScanner = !showScanner"
                        ><ScanLine /> {{ showScanner ? 'Close scanner' : 'Scan device QR' }}</Button
                    >
                </template>
            </PageHeader>

            <QrScanCard
                v-if="showScanner"
                title="Scan the code on the device"
                invalid-message="That QR code is not an Agronos device code."
                @scan="onScan"
            />

            <SectionCard title="Device details" class="max-w-2xl">
                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <FormField id="name" label="Device name" :error="form.errors.name"
                        ><Input id="name" v-model="form.name" placeholder="Sensor node 01"
                    /></FormField>
                    <FormField id="uuid" label="UUID" :error="form.errors.uuid"
                        ><Input id="uuid" v-model="form.uuid" required class="font-mono"
                    /></FormField>
                    <FormField id="secret" label="Secret" :error="form.errors.secret"
                        ><Input id="secret" v-model="form.secret" type="password" required
                    /></FormField>
                    <FormField id="type" label="Type" :error="form.errors.type"
                        ><NativeSelect id="type" v-model="form.type" required>
                            <option value="" disabled>Select type</option>
                            <option v-for="type in deviceTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </NativeSelect></FormField
                    >
                    <FormField v-if="form.type === 'lora'" id="lora_aes_key" label="AES key (32-character hex)" :error="form.errors.lora_aes_key"
                        ><Input
                            id="lora_aes_key"
                            v-model="form.lora_aes_key"
                            placeholder="0123456789abcdef0123456789abcdef"
                            maxlength="32"
                            class="font-mono"
                    /></FormField>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child><Link :href="route('devices.index')">Cancel</Link></Button>
                        <Button type="submit" :disabled="form.processing">Register device</Button>
                    </div>
                </form>
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
