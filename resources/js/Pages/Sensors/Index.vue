<template>
  <div>
    <h1 class="text-2xl font-bold mb-4 flex items-center gap-2">
      <Waves class="w-6 h-6 text-blue-500" />
      Sensors
    </h1>
    <div v-if="sensors.length === 0" class="text-gray-500">No sensors found.</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="sensor in sensors" :key="sensor.id" class="p-4 bg-white rounded shadow flex flex-col gap-2">
        <div class="font-semibold text-lg flex items-center gap-2">
          <Radar class="w-4 h-4 text-blue-400" />
          {{ sensor.name || 'Unnamed Sensor' }}
        </div>
        <div class="text-sm text-gray-600">Type: <span class="font-mono">{{ sensor.type }}</span></div>
        <div class="text-xs text-gray-400">UUID: {{ sensor.uuid }}</div>
        <div class="text-xs text-gray-400">Device ID: {{ sensor.device_id }}</div>
        <div class="text-xs text-gray-400">Lat: {{ sensor.lat }}, Lon: {{ sensor.lon }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Waves, Radar } from 'lucide-vue-next';
import { defineProps } from 'vue';

const props = defineProps({
  sensors: {
    type: Array,
    required: true,
  },
});
</script>

<style scoped>
</style>
