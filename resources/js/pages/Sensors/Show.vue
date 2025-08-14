<template>
  <Head :title="sensor.name ? sensor.name : 'Sensor Details'" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container py-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold pl-3 flex items-center gap-2">
          <span v-if="sensor.name">{{ sensor.name }}</span>
          <span v-else class="text-muted-foreground">Unnamed Sensor</span>
          <span class="ml-2 px-2 py-1 rounded bg-muted text-xs">{{ sensor.type }}</span>
        </h1>
        <div class="space-x-2 flex items-center">
          <Link :href="route('sensors.edit', sensor.id)">
            <Button variant="outline" size="sm" class="flex flex-row items-center border p-2">
              <Pencil class="h-4 w-4 mr-2" />
              <span>Edit</span>
            </Button>
          </Link>
          <Button
            variant="destructive"
            size="sm"
            class="flex flex-row items-center"
            @click="deleteSensor"
          >
            <Trash2 class="h-4 w-4 mr-2" />
            <span>Delete</span>
          </Button>
        </div>
      </div>
      <div v-if="sensor.lat && sensor.lon" class="mb-6">
        <FarmMapbox
          :center="[parseFloat(sensor.lon), parseFloat(sensor.lat)]"
          :farmPolygon="sensor.farm?.coordinates"
          :sensors="[{ lat: parseFloat(sensor.lat), lon: parseFloat(sensor.lon), name: sensor.name }]"
          :zoom="15"
        />
      </div>
      <Card class="mb-6">
        <CardHeader>
          <CardTitle>Sensor Details</CardTitle>
          <CardDescription>Information about this sensor</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Sensor UUID</h3>
              <p class="font-mono break-all">{{ sensor.uuid }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Device UUID</h3>
              <p class="font-mono break-all">{{ sensor.device?.uuid || '—' }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Sensor type</h3>
              <p class="font-mono break-all">{{ sensor.type || '—' }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Farm</h3>
              <p>
                <template v-if="sensor.farm">
                  <Link :href="route('farms.show', sensor.farm.id)">{{ sensor.farm.name }}</Link>
                </template>
                <template v-else>
                  —
                </template>
              </p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Location</h3>
              <p>Lat: {{ sensor.lat ?? '—' }}, Lon: {{ sensor.lon ?? '—' }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <Card>
          <CardHeader>
            <CardTitle>Latest Reading</CardTitle>
            <CardDescription>Most recent data from this sensor</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Value</h3>
                <p class="text-2xl font-bold">
                  {{ sensor.last_reading ?? '—' }}
                  <span v-if="sensor.unit" class="text-base font-normal text-muted-foreground">{{ sensor.unit }}</span>
                </p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Last Seen</h3>
                <p class="text-sm">{{ sensor.last_reading_at ?? '—' }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>24h Statistics</CardTitle>
            <CardDescription>Min/Max/Average</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Min</h3>
                <p class="text-lg font-semibold">
                  {{ stats?.min ?? '—' }}
                  <span v-if="sensor.unit" class="text-base font-normal text-muted-foreground">{{ sensor.unit }}</span>
                </p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Max</h3>
                <p class="text-lg font-semibold">
                  {{ stats?.max ?? '—' }}
                  <span v-if="sensor.unit" class="text-base font-normal text-muted-foreground">{{ sensor.unit }}</span>
                </p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Avg</h3>
                <p class="text-lg font-semibold">
                  {{ stats?.avg != null ? stats.avg.toFixed(2) : '—' }}
                  <span v-if="sensor.unit" class="text-base font-normal text-muted-foreground">{{ sensor.unit }}</span>
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card class="mb-6" v-if="recentReadings && recentReadings.length">
        <CardHeader>
          <CardTitle>Recent Readings</CardTitle>
          <CardDescription>Last 10 measurements from InfluxDB</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr>
                  <th class="px-2 py-1 text-left">Timestamp</th>
                  <th class="px-2 py-1 text-left">Value</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in recentReadings" :key="row.time">
                  <td class="px-2 py-1">{{ row.time }}</td>
                  <td class="px-2 py-1">
                    {{ row.value }}
                    <span v-if="sensor.unit" class="text-xs text-muted-foreground">{{ sensor.unit }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head , Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import FarmMapbox from '@/components/FarmMapbox.vue'


const page = usePage();
const sensor = computed(() => page.props.sensor as any);

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Sensors', href: '/sensors' },
  { title: sensor.value.name || 'Sensor Details', href: null },
];

function deleteSensor() {
  if (confirm(`Are you sure you want to delete ${sensor.value.name || 'this sensor'}?`)) {
    router.delete(route('sensors.destroy', sensor.value.id));
  }
}

const recentReadings = computed(() => (page.props.recentReadings as Array<{time:string,value:number}>) || []);
const stats = computed(() => page.props.stats as {min:number|null,max:number|null,avg:number|null,count:number});
</script>

<style scoped>
.text-muted-foreground {
  color: #6b7280;
}
.bg-muted {
  background: #f3f4f6;
}
</style>
