<template>
  <Link :href="route('sensors.show', sensor.id)" class="block hover:shadow-lg transition-shadow">
    <Card class="overflow-hidden">
      <CardHeader class="bg-muted/20 flex flex-row items-center justify-between gap-4">
        <div class="flex flex-col gap-1 min-w-[180px]">
          <CardDescription class="text-lg font-semibold">{{ sensor.name || 'Unnamed Sensor' }}</CardDescription>
          <span class="text-xs text-muted-foreground">UUID</span>
          <span class="font-mono text-sm">{{ sensor.uuid }}</span>
        </div>
        <div class="flex-1 flex flex-col items-start justify-center">
          <!-- (Optional) Additional info here -->
        </div>
        <div class="flex flex-col items-end gap-2 min-w-[120px]">
          <span class="text-sm text-muted-foreground">Type: {{ sensor.type }}</span>
          <span class="text-xs text-muted-foreground">Lat: {{ sensor.lat }}, Lon: {{ sensor.lon }}</span>
          <span class="text-xs text-muted-foreground mt-2">Last seen: {{ sensor.last_reading_at ? sensor.last_reading_at : 'Never' }}</span>
          <span class="text-xs text-muted-foreground">Last value: {{ sensor.last_reading !== undefined && sensor.last_reading !== null ? sensor.last_reading : '—' }}</span>
        </div>
      </CardHeader>
    </Card>
  </Link>
</template>

<script setup lang="ts">
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { Link, router } from '@inertiajs/vue3';
import type { Sensor } from '@/types/sensor';


defineProps<{ sensor: Sensor }>();
</script>
