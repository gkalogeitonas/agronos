<template>
  <div class="container mx-auto py-8">
    <h1 class="text-2xl font-bold mb-6">Devices</h1>
    <div v-if="devices.length === 0" class="text-gray-500">No devices registered yet.</div>
    <div v-else>
      <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow">
        <thead>
          <tr class="bg-gray-100">
            <th class="px-4 py-2 text-left">Name</th>
            <th class="px-4 py-2 text-left">UUID</th>
            <th class="px-4 py-2 text-left">Type</th>
            <th class="px-4 py-2 text-left">Status</th>
            <th class="px-4 py-2 text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="device in devices" :key="device.id" class="border-t">
            <td class="px-4 py-2">{{ device.name }}</td>
            <td class="px-4 py-2">{{ device.uuid }}</td>
            <td class="px-4 py-2">{{ device.type }}</td>
            <td class="px-4 py-2">
              <span :class="statusClass(device.status)">{{ device.status }}</span>
            </td>
            <td class="px-4 py-2">
              <a :href="route('devices.show', device.id)" class="text-blue-600 hover:underline">View</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const devices = computed(() => page.props.devices ?? []);

function statusClass(status) {
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
