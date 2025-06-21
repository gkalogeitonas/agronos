<script setup lang="ts">
import { ref, watch, toRefs } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import MapboxMap from '@/components/MapboxMap.vue';

const props = defineProps<{
  form: any,
  polygon: any,
  onUpdatePolygon: (poly: any) => void,
  onResetPolygon: () => void,
  onSubmit: () => void,
  isEdit?: boolean,
  defaultLng?: number,
  defaultLat?: number,
}>();

const { form, polygon, onUpdatePolygon, onResetPolygon, onSubmit, isEdit, defaultLng, defaultLat } = toRefs(props);
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>{{ isEdit ? 'Edit Farm' : 'Create New Farm' }}</CardTitle>
      <CardDescription>
        {{ isEdit ? 'Update the details of your farm.' : 'Fill in the details to add a new farm.' }}
      </CardDescription>
    </CardHeader>
    <form @submit.prevent="onSubmit">
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
          <label class="block mb-1 font-medium">Farm Area (draw on map)</label>
          <div class="flex flex-col gap-2">
            <MapboxMap
              :lng="defaultLng ?? 23.7275"
              :lat="defaultLat ?? 37.9838"
              :zoom="6"
              :editable="true"
              :polygon="polygon"
              @update:polygon="onUpdatePolygon"
              @reset:polygon="onResetPolygon"
              class="mt-4"
            />
          </div>
          <div class="text-xs text-muted-foreground mt-2">Use the polygon tool to draw your farm's boundaries. Click to add points, double-click to finish.</div>
          <div v-if="form.errors.coordinates" class="text-red-500 text-sm mt-1">{{ form.errors.coordinates }}</div>
        </div>
        <div>
          <label class="block mb-1 font-medium">Description</label>
          <Textarea v-model="form.description" placeholder="Description (optional)" />
          <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</div>
        </div>
      </CardContent>
      <CardFooter class="flex justify-end gap-2">
        <slot name="footer">
          <Button variant="outline" type="button" :href="route('farms.index')">Cancel</Button>
          <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Update Farm' : 'Create Farm' }}</Button>
        </slot>
      </CardFooter>
    </form>
  </Card>
</template>
