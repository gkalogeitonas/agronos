<script setup lang="ts">
import { ref } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Farms', href: '/farms' },
  { title: 'Create', href: route('farms.create'), current: true },
];

const form = useForm({
  name: '',
  location: '',
  size: '',
  coordinates: '',
  description: '',
});

const submit = () => {
  form.post(route('farms.store'));
};
</script>

<template>
  <Head title="Create Farm" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container py-8 max-w-2xl mx-auto">
      <Card>
        <CardHeader>
          <CardTitle>Create New Farm</CardTitle>
          <CardDescription>Fill in the details to add a new farm.</CardDescription>
        </CardHeader>
        <form @submit.prevent="submit">
          <CardContent class="space-y-6">
            <div>
              <label class="block mb-1 font-medium">Name</label>
              <Input v-model="form.name" required placeholder="Farm name" />
              <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</div>
            </div>
            <div>
              <label class="block mb-1 font-medium">Location</label>
              <Input v-model="form.location" required placeholder="Location" />
              <div v-if="form.errors.location" class="text-red-500 text-sm mt-1">{{ form.errors.location }}</div>
            </div>
            <div>
              <label class="block mb-1 font-medium">Size (m²)</label>
              <Input v-model="form.size" type="number" min="0" step="0.01" required placeholder="Size in square meters" />
              <div v-if="form.errors.size" class="text-red-500 text-sm mt-1">{{ form.errors.size }}</div>
            </div>
            <div>
              <label class="block mb-1 font-medium">Coordinates (POINT format)</label>
              <Input v-model="form.coordinates" placeholder="POINT(lng lat)" />
              <div v-if="form.errors.coordinates" class="text-red-500 text-sm mt-1">{{ form.errors.coordinates }}</div>
            </div>
            <div>
              <label class="block mb-1 font-medium">Description</label>
              <Textarea v-model="form.description" placeholder="Description (optional)" />
              <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</div>
            </div>
          </CardContent>
          <CardFooter class="flex justify-end gap-2">
            <Button variant="outline" type="button" :href="route('farms.index')">Cancel</Button>
            <Button type="submit" :disabled="form.processing">Create Farm</Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  </AppLayout>
</template>
