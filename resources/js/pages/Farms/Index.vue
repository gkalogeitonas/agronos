<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface Farm {
  id: number;
  name: string;
  location: string;
  size: number;
  coordinates: string | null;
  description: string | null;
}

const props = defineProps<{
  farms: Farm[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Farms',
    href: '/farms',
  },
];

const formatSize = (size: number): string => {
  return `${size.toLocaleString()} ha`;
};
</script>

<template>
  <Head title="Farms" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col h-full gap-6 p-4">
      <!-- Header -->
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Farms Management</h1>

        <Link :href="route('farms.create')">
          <Button>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 3a1 1 0 00-1 1v5H4a1 1 0 100 2h5v5a1 1 0 102 0v-5h5a1 1 0 100-2h-5V4a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            Add Farm
          </Button>
        </Link>
      </div>

      <!-- Farm Cards Grid -->
      <div v-if="farms.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <Card v-for="farm in farms" :key="farm.id" class="overflow-hidden">
          <CardHeader class="bg-muted/20">
            <CardTitle>
              <Link :href="route('farms.show', farm.id)" class="hover:underline text-primary">
                {{ farm.name }}
              </Link>
            </CardTitle>
            <CardDescription>{{ farm.location }}</CardDescription>
          </CardHeader>
          <CardContent class="pt-6">
            <div class="flex flex-col gap-4">
              <div class="flex justify-between">
                <span class="text-sm text-muted-foreground">Size</span>
                <span>{{ formatSize(farm.size) }}</span>
              </div>

              <div class="flex gap-2 mt-4">
                <Link :href="route('farms.show', farm.id)" class="flex-1">
                  <Button variant="outline" class="w-full">View Details</Button>
                </Link>
                <Link :href="route('farms.edit', farm.id)" class="flex-1">
                  <Button variant="outline" class="w-full">Edit</Button>
                </Link>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center p-8 border rounded-lg border-dashed text-center">
        <div class="w-16 h-16 rounded-full bg-muted flex items-center justify-center mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-muted-foreground" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
          </svg>
        </div>
        <h3 class="text-lg font-medium mb-2">No farms found</h3>
        <p class="text-sm text-muted-foreground mb-4">Get started by adding your first farm.</p>
        <Link :href="route('farms.create')">
          <Button>Add Your First Farm</Button>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
