<script setup lang="ts">
import { ref, watch, toRefs } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import MapboxMap from '@/components/MapboxMap.vue';
import { area } from '@turf/turf';

const props = defineProps<{
  form: any,
  polygon: any,
  onUpdatePolygon: (poly: any) => void,
  onSubmit: () => void,
  isEdit?: boolean,
  defaultLng?: number,
  defaultLat?: number,
}>();

const { form, polygon, onUpdatePolygon, onSubmit, isEdit, defaultLng, defaultLat } = toRefs(props);

const calculatedArea = ref<number | null>(null);

watch(
  () => polygon.value,
  (newPolygon) => {
    if (newPolygon && newPolygon.type === 'Polygon') {
      calculatedArea.value = area({ type: 'Feature', geometry: newPolygon });
      form.value.size = calculatedArea.value; // still set for backend
    } else {
      calculatedArea.value = null;
      form.value.size = '';
    }
  },
  { immediate: true }
);
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
        <!-- Map and area display -->
        <div>
          <label class="block mb-1 font-medium">Farm Area</label>
          <MapboxMap
            :lng="defaultLng"
            :lat="defaultLat"
            :polygon="polygon"
            :editable="true"
            @update:polygon="onUpdatePolygon"
          />
          <div v-if="calculatedArea !== null" class="mt-2 text-sm text-gray-600">
            <span class="font-semibold">Calculated Area:</span>
            {{ calculatedArea.toLocaleString(undefined, { maximumFractionDigits: 2 }) }} m²
          </div>
        </div>
        <div>
          <label class="block mb-1 font-medium">Description</label>
          <Textarea v-model="form.description" placeholder="Description" />
        </div>
      </CardContent>
      <CardFooter class="flex justify-end gap-2">
        <Button type="submit">{{ isEdit ? 'Update' : 'Create' }}</Button>
      </CardFooter>
    </form>
  </Card>
</template>
