<template>
  <Head title="Devices" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col h-full gap-6 p-4">
      <!-- Header -->
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Devices Management</h1>
        <Link :href="route('devices.create')">
          <Button>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 3a1 1 0 00-1 1v5H4a1 1 0 100 2h5v5a1 1 0 102 0v-5h5a1 1 0 100-2h-5V4a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            Add Device
          </Button>
        </Link>
      </div>
      <!-- Device List -->
      <div v-if="devices.length > 0" class="flex flex-col gap-4">
        <Card v-for="device in devices" :key="device.id" class="overflow-hidden">
          <CardHeader class="bg-muted/20 flex flex-row items-center justify-between gap-4">
            <div class="flex flex-col gap-1 min-w-[180px]">
              <span class="text-xs text-muted-foreground">UUID</span>
              <span class="font-mono text-sm">{{ device.uuid }}</span>
              <span class="text-xs text-muted-foreground mt-1">Type</span>
              <span class="text-sm">{{ device.type }}</span>
            </div>
            <div class="flex-1 flex flex-col items-start justify-center">
              <CardDescription class="text-lg font-semibold">{{ device.name }}</CardDescription>
            </div>
            <div class="flex flex-col items-end gap-2 min-w-[120px]">
              <span :class="statusClass(device.status)">{{ device.status }}</span>
              <Link :href="route('devices.show', device.id)">
                <Button variant="outline">View Details</Button>
              </Link>
            </div>
          </CardHeader>
        </Card>
      </div>
      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center p-8 border rounded-lg border-dashed text-center">
        <div class="w-16 h-16 rounded-full bg-muted flex items-center justify-center mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-muted-foreground" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
          </svg>
        </div>
        <h3 class="text-lg font-medium mb-2">No devices found</h3>
        <p class="text-sm text-muted-foreground mb-4">Get started by adding your first device.</p>
        <Link :href="route('devices.create')">
          <Button>Add Your First Device</Button>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface Device {
  id: number;
  name: string;
  uuid: string;
  type: string;
  status: string;
}

const page = usePage();
const devices = computed(() => page.props.devices as Device[] ?? []);

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Devices',
    href: '/devices',
  },
];

function statusClass(status: string) {
  switch (status) {
    case 'registered':
      return 'text-green-600 font-semibold';
    case 'inactive':
      return 'text-gray-400';
    case 'error':
      return 'text-red-600 font-semibold';
    default:
      return '';
  }
}
</script>

<style scoped>
.container {
  max-width: 900px;
}
</style>
