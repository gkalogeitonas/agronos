<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ref, computed, watch } from 'vue';
import QRCodeVue from 'qrcode.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Device QR Generator', href: '/tools/device-qr', current: true },
];

const deviceTypes = [
  { label: 'WiFi', value: 'wifi' },
  { label: 'LoRa', value: 'lora' },
  { label: 'Other', value: 'other' },
];

const sensorTypes = [
  { label: 'Soil Moisture', value: 'moisture' },
  { label: 'Soil Moisture', value: 'moisture' },
  { label: 'Soil Moisture', value: 'moisture' },
];

const type = ref('wifi');
const uuid = ref('');
const secret = ref('');
const sensors = ref([
  { uuid: '', type: 'moisture' },
  { uuid: '', type: 'moisture' },
  { uuid: '', type: 'moisture' },
]);

function randomString(length = 32) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let result = '';
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}

function generate() {
  uuid.value = crypto.randomUUID();
  secret.value = randomString(32);
  sensors.value = sensors.value.map(() => ({
    uuid: crypto.randomUUID(),
    type: 'moisture',
  }));
}

// Watch for type changes and regenerate QR data
watch(type, () => {
  // Optionally, you can regenerate uuid/secret on type change, or just update QR
  // Here, we just update the QR code (uuid/secret remain the same)
}, { immediate: true });

type.value = deviceTypes[0].value;
generate(); // Generate on mount

const deviceQrData = computed(() => JSON.stringify({
  uuid: uuid.value,
  secret: secret.value,
  type: type.value,
}));

const sensorQrData = computed(() => sensors.value.map(sensor => JSON.stringify({
  uuid: sensor.uuid,
  device_uuid: uuid.value,
  type: sensor.type,
})));


function pretty(json: string) {
  try {
    return JSON.stringify(JSON.parse(json), null, 2);
  } catch {
    return json;
  }
}
</script>

<template>
  <Head title="Device QR Generator" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col h-full gap-6 p-4 w-full max-w-2xl mx-auto">
      <Card>
        <CardHeader>
          <CardTitle>Device QR Generator</CardTitle>
        </CardHeader>
        <CardContent>
          <form class="flex flex-col gap-6" @submit.prevent="generate">
            <div>
              <label class="block mb-1 font-medium" for="type">Device Type</label>
              <select v-model="type.value" id="type" class="input w-full" required>
                <option v-for="t in deviceTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </div>
            <div class="flex justify-end">
              <Button type="submit">Generate New</Button>
            </div>
          </form>
          <div class="flex flex-col items-center mt-8">
            <QRCodeVue :value="deviceQrData" :size="220" />
            <div class="mt-2 text-xs text-muted-foreground break-all max-w-full">
                <pre class="mt-2 text-xs text-muted-foreground break-all max-w-full bg-muted p-2 rounded">
                  {{ pretty(deviceQrData) }}
                </pre>
            </div>
          </div>
          <div class="mt-8">
            <h3 class="text-lg font-medium mb-4">Sensor QR Codes</h3>
            <div v-for="(sensor, index) in sensors" :key="sensor.uuid" class="flex flex-col items-center mb-4">
              <QRCodeVue :value="sensorQrData[index]" :size="180" />
              <pre class="mt-2 text-xs text-muted-foreground break-all max-w-full bg-muted p-2 rounded">
                {{pretty( sensorQrData[index]) }}
              </pre>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<style scoped>
.input {
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  padding: 0.5rem 0.75rem;
  outline: none;
  transition: box-shadow 0.2s;
  width: 100%;
  background: var(--color-background, #fff);
  color: var(--color-foreground, #111);
}
.input:focus {
  box-shadow: 0 0 0 2px var(--color-primary, #2563eb33);
}
</style>
