<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { QrcodeStream } from 'vue-qrcode-reader';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Devices', href: '/devices' },
  { title: 'Register Device', href: '/devices/create', current: true },
];

const form = useForm({
  name: '',
  uuid: '',
  secret: '',
  type: '',
});

const page = usePage();
const deviceTypes = computed(() => page.props.deviceTypes ?? []);
const showScanner = ref(false);

function submit() {
  form.post(route('devices.store'));
}

function onDetect(detectedCodes) {
  if (detectedCodes && detectedCodes.length > 0) {
    try {
      const data = JSON.parse(detectedCodes[0].rawValue);
      if (data.uuid) form.uuid = data.uuid;
      if (data.secret) form.secret = data.secret;
      if (data.type) form.type = data.type;
      showScanner.value = false;
    } catch (e) {
      alert('Invalid QR code format.');
    }
  }
}

function onQrError(error) {
  alert('QR Scanner Error: ' + error.message);
}
</script>

<template>
  <Head title="Register Device" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col h-full gap-6 p-4 w-full max-w-4xl mx-auto">
      <div class="flex justify-center mb-4">
        <Button type="button" variant="default" class="text-lg px-6 py-3" @click="showScanner = true">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3 5a2 2 0 012-2h2a1 1 0 110 2H5v2a1 1 0 11-2 0V5zm12-2a2 2 0 012 2v2a1 1 0 11-2 0V5h-2a1 1 0 110-2h2zm2 12a2 2 0 01-2 2h-2a1 1 0 110-2h2v-2a1 1 0 112 0v2zm-14 2a2 2 0 01-2-2v-2a1 1 0 112 0v2h2a1 1 0 110 2H5zm3-7a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
          </svg>
          Scan Device QR
        </Button>
      </div>
      <Card>
        <CardHeader>
          <CardTitle>Register New Device</CardTitle>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div>
              <label class="block mb-1 font-medium" for="name">Device Name</label>
              <input v-model="form.name" id="name" type="text" class="input w-full"  />
              <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</div>
            </div>
            <div>
              <label class="block mb-1 font-medium" for="uuid">UUID</label>
              <input v-model="form.uuid" id="uuid" type="text" class="input w-full" required />
              <div v-if="form.errors.uuid" class="text-red-500 text-sm mt-1">{{ form.errors.uuid }}</div>
            </div>
            <div>
              <label class="block mb-1 font-medium" for="secret">Secret</label>
              <input v-model="form.secret" id="secret" type="password" class="input w-full" required />
              <div v-if="form.errors.secret" class="text-red-500 text-sm mt-1">{{ form.errors.secret }}</div>
            </div>
            <div>
              <label class="block mb-1 font-medium" for="type">Type</label>
              <select v-model="form.type" id="type" class="input w-full" required>
                <option value="" disabled>Select type</option>
                <option v-for="type in deviceTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
              </select>
              <div v-if="form.errors.type" class="text-red-500 text-sm mt-1">{{ form.errors.type }}</div>
            </div>
            <div class="flex justify-end gap-2">
              <Link :href="route('devices.index')">
                <Button variant="outline" type="button">Cancel</Button>
              </Link>
              <Button type="submit" :disabled="form.processing">Register Device</Button>
            </div>
          </form>
          <QrcodeStream v-if="showScanner" @detect="onDetect" @error="onQrError" class="mb-4" />
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<style scoped>
.input {
  border: 1px solid #d1d5db; /* Tailwind's gray-300 */
  border-radius: 0.375rem; /* rounded-md */
  padding: 0.5rem 0.75rem; /* px-3 py-2 */
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


