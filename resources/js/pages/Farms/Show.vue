<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { router } from '@inertiajs/vue3'
import { Pencil, Trash2 } from 'lucide-vue-next'
import { type BreadcrumbItem } from '@/types'
import { Link } from '@inertiajs/vue3'
import SensorCard from '@/components/SensorCard.vue'
import FarmMapbox from '@/components/FarmMapbox.vue'
import type { Sensor } from '@/types/sensor'

// Props
const props = defineProps<{
  farm: {
    id: number
    name: string
    location: string
    size: number
    description: string | null
    coordinates: any // GeoJSON object or null
    created_at: string
    updated_at: string
    center: {
      lng: number
      lat: number
    } | null
  },
  sensors: Sensor[],
  farmStats?: {
    totalSensors: number
    activeSensors: number
    totalReadings: number
    sensorTypeStats: Record<string, number>
    readingStatsByType: Record<string, {
      count: number
      activeSensors: number
      avgReading: number | null
      minReading: number | null
      maxReading: number | null
      totalReadings: number
    }>
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Farms',
    href: '/farms',
  },
  {
    title: props.farm.name,
    href: route('farms.show', props.farm.id),
  },
]

// Delete farm handler
const deleteFarm = () => {
  if (confirm(`Are you sure you want to delete ${props.farm.name}?`)) {
    router.delete(route('farms.destroy', props.farm.id))
  }
}
</script>

<template>
  <Head :title="farm.name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container py-8">
      <div class="flex justify-between items-center mb-6">
        <Link :href="route('farms.show', farm.id)" class="text-3xl font-bold pl-3 hover:underline">
          {{ farm.name }}
        </Link>
        <div class="space-x-2">
          <Link :href="route('farms.edit', farm.id)">
            <Button
              variant="outline"
              size="sm"
            >
              <Pencil class="h-4 w-4 mr-2" />
              Edit
            </Button>
          </Link>
          <Button
            variant="destructive"
            size="sm"
            @click="deleteFarm"
          >
            <Trash2 class="h-4 w-4 mr-2" />
            Delete
          </Button>
          <Link :href="route('farms.sensors.create', { farm: farm.id })">
            <Button variant="default" size="sm">
              Add Sensor
            </Button>
          </Link>
        </div>
      </div>

      <div class="mb-6">
        <FarmMapbox
          :center="farm.center ? [farm.center.lng, farm.center.lat] : [0, 0]"
          :farmPolygon="farm.coordinates"
          :sensors="sensors.map(s => ({ lat: Number(s.lat), lon: Number(s.lon), name: s.name }))"
        />
      </div>

            <Card class="mb-6">
        <CardHeader>
          <CardTitle>Farm Details</CardTitle>
          <CardDescription>Information about this farm</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Location</h3>
              <p>{{ farm.location }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Size</h3>
              <p>{{ farm.size }} m²</p>
            </div>
            <div class="col-span-1 md:col-span-2">
              <h3 class="text-sm font-medium text-muted-foreground">Description</h3>
              <p>{{ farm.description || 'No description available' }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Created</h3>
              <p>{{ new Date(farm.created_at).toLocaleDateString() }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Last Updated</h3>
              <p>{{ new Date(farm.updated_at).toLocaleDateString() }}</p>
            </div>
          </div>
        </CardContent>
        <CardFooter class="flex justify-between">
          <Link :href="route('farms.index')">
            <Button variant="outline">
              Back to Farms
            </Button>
          </Link>
        </CardFooter>
      </Card>

      <!-- Farm Statistics -->
      <div v-if="farmStats" class="mb-6">
        <!-- Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <Card>
            <CardHeader>
              <CardTitle>Sensor Overview</CardTitle>
              <CardDescription>24h activity summary</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-sm text-muted-foreground">Total Sensors</span>
                  <span class="font-semibold">{{ farmStats.totalSensors }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-muted-foreground">Active Sensors</span>
                  <span class="font-semibold">{{ farmStats.activeSensors }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-muted-foreground">Total Readings</span>
                  <span class="font-semibold">{{ farmStats.totalReadings.toLocaleString() }}</span>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card v-if="farmStats.sensorTypeStats && Object.keys(farmStats.sensorTypeStats).length > 0">
            <CardHeader>
              <CardTitle>Sensor Types</CardTitle>
              <CardDescription>Distribution by type</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="space-y-2">
                <div v-for="(count, type) in farmStats.sensorTypeStats" :key="type" class="flex justify-between">
                  <span class="text-sm capitalize">{{ type || 'Unknown' }}</span>
                  <span class="font-medium">{{ count }}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Reading Statistics by Type -->
        <div v-if="farmStats.readingStatsByType && Object.keys(farmStats.readingStatsByType).length > 0" class="mb-6">
          <h3 class="text-lg font-semibold mb-4">Reading Statistics by Sensor Type</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Card v-for="(stats, type) in farmStats.readingStatsByType" :key="type">
              <CardHeader>
                <CardTitle class="capitalize text-base">{{ type }} Sensors</CardTitle>
                <CardDescription>{{ stats.activeSensors }}/{{ stats.count }} active • {{ stats.totalReadings }} readings</CardDescription>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <h4 class="text-xs font-medium text-muted-foreground">Min</h4>
                    <p class="text-sm font-semibold">{{ stats.minReading ?? '—' }}</p>
                  </div>
                  <div>
                    <h4 class="text-xs font-medium text-muted-foreground">Max</h4>
                    <p class="text-sm font-semibold">{{ stats.maxReading ?? '—' }}</p>
                  </div>
                  <div>
                    <h4 class="text-xs font-medium text-muted-foreground">Avg</h4>
                    <p class="text-sm font-semibold">{{ stats.avgReading != null ? stats.avgReading.toFixed(2) : '—' }}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <Card class="mb-6">
        <CardHeader>
          <CardTitle>Sensors</CardTitle>
          <CardDescription>All sensors registered to this farm</CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="sensors.length === 0" class="text-muted-foreground">No sensors found for this farm.</div>
          <div v-else class="grid grid-cols-1 gap-4">
            <SensorCard v-for="sensor in sensors" :key="sensor.id" :sensor="sensor" />
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<style scoped>
.map-container {
  width: 100%;
  height: 300px;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}
</style>
