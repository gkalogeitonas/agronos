<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { router } from '@inertiajs/vue3'
import { Download, Pencil, Trash2 } from 'lucide-vue-next'
import MapboxMap from '@/components/MapboxMap.vue'
import { type BreadcrumbItem } from '@/types'

// Props
const props = defineProps<{
  farm: {
    id: number
    name: string
    location: string
    size: number
    description: string | null
    coordinates: any // Now GeoJSON object or null
    created_at: string
    updated_at: string
    center: {
      lng: number
      lat: number
    } | null
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
    current: true,
  },
]

// Delete farm handler
const deleteFarm = () => {
  if (confirm(`Are you sure you want to delete ${props.farm.name}?`)) {
    router.delete(route('farms.destroy', props.farm.id))
  }
}

// Get center of polygon for map view
const lng = props.farm.center?.lng ?? 0;
const lat = props.farm.center?.lat ?? 0;
</script>

<template>
  <Head :title="farm.name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container py-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold pl-3">{{ farm.name }}</h1>
        <div class="space-x-2">
          <Button
            variant="outline"
            size="sm"
            :href="route('farms.edit', farm.id)"
          >
            <Pencil class="h-4 w-4 mr-2" />
            Edit
          </Button>
          <Button
            variant="destructive"
            size="sm"
            @click="deleteFarm"
          >
            <Trash2 class="h-4 w-4 mr-2" />
            Delete
          </Button>
        </div>
      </div>

      <MapboxMap :lng="lng" :lat="lat" :zoom="12" :polygon="farm.coordinates" class="mb-6" />
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
          <Button variant="outline" :href="route('farms.index')">
            Back to Farms
          </Button>
          <Button variant="outline" size="sm">
            <Download class="h-4 w-4 mr-2" />
            Export Data
          </Button>
        </CardFooter>
      </Card>
    </div>
  </AppLayout>
</template>
