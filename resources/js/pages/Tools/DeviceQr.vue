<script setup lang="ts">
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import FormField from '@/components/FormField.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import QRCodeVue from 'qrcode.vue';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Device QR generator', href: route('tools.device-qr') }];

const deviceTypes = [
    { label: 'Wi-Fi', value: 'wifi' },
    { label: 'LoRa', value: 'lora' },
    { label: 'Other', value: 'other' },
];

const type = ref(deviceTypes[0].value);
const uuid = ref('');
const secret = ref('');
const sensors = ref([
    { uuid: '', type: 'moisture' },
    { uuid: '', type: 'moisture' },
    { uuid: '', type: 'moisture' },
]);

function randomString(length = 32): string {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

function generate(): void {
    uuid.value = crypto.randomUUID();
    secret.value = randomString(32);
    sensors.value = sensors.value.map(() => ({
        uuid: crypto.randomUUID(),
        type: 'moisture',
    }));
}

generate();

const deviceQrData = computed(() => JSON.stringify({ uuid: uuid.value, secret: secret.value, type: type.value }));

const sensorQrData = computed(() => sensors.value.map((sensor) => JSON.stringify({ uuid: sensor.uuid, device_uuid: uuid.value, type: sensor.type })));

const pretty = (json: string): string => JSON.stringify(JSON.parse(json), null, 2);
</script>

<template>
    <Head title="Device QR generator" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Tools" title="Device QR generator">
                <template #actions>
                    <Button @click="generate"><RefreshCw /> Generate new</Button>
                </template>
            </PageHeader>

            <p class="m-0 max-w-3xl text-body text-muted-foreground">
                Print these codes and stick them on a new device and its sensors. Scanning them on the Add device and Add sensor pages fills in the
                IDs.
            </p>

            <div class="flex flex-wrap items-start gap-4">
                <SectionCard title="Device" class="flex-[1_1_320px]">
                    <FormField id="type" label="Device type">
                        <NativeSelect id="type" v-model="type">
                            <option v-for="option in deviceTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </NativeSelect>
                    </FormField>
                    <div class="flex justify-center rounded-md border bg-white p-4">
                        <QRCodeVue :value="deviceQrData" :size="220" />
                    </div>
                    <pre class="m-0 overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs text-muted-foreground">{{ pretty(deviceQrData) }}</pre>
                </SectionCard>

                <SectionCard title="Sensors" class="flex-[2_1_480px]">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="(sensor, index) in sensors" :key="sensor.uuid" class="flex flex-col gap-3">
                            <div class="flex justify-center rounded-md border bg-white p-3">
                                <QRCodeVue :value="sensorQrData[index]" :size="160" />
                            </div>
                            <pre class="m-0 overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs text-muted-foreground">{{
                                pretty(sensorQrData[index])
                            }}</pre>
                        </div>
                    </div>
                </SectionCard>
            </div>
        </PageContainer>
    </AppLayout>
</template>
