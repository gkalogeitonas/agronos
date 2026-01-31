<template>
  <Head title="Add Sensor" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col h-full gap-6 p-4 w-full max-w-4xl mx-auto">
      <h1 class="text-2xl font-bold mb-6">Add Sensor</h1>
      <div class="flex justify-center mb-4">
        <Button type="button" variant="default" class="text-lg px-6 py-3" @click="showScanner = true">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3 5a2 2 0 012-2h2a1 1 0 110 2H5v2a1 1 0 11-2 0V5zm12-2a2 2 0 012 2v2a1 1 0 11-2 0V5h-2a1 1 0 110-2h2zm2 12a2 2 0 01-2 2h-2a1 1 0 110-2h2v-2a1 1 0 112 0v2zm-14 2a2 2 0 01-2-2v-2a1 1 0 112 0v2h2a1 1 0 110 2H5zm3-7a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
          </svg>
          Scan Sensor QR
        </Button>
      </div>
      <QrcodeStream v-if="showScanner" @detect="onDetect" @error="onQrError" class="mb-4" />
      <MapboxMap
        v-if="form.lat && form.lon"
        style="width: 100%; height: 400px; border-radius: 0.5rem; "
        :access-token="mapboxToken"
        map-style="mapbox://styles/mapbox/satellite-streets-v12"
        :center="[parseFloat(form.lon), parseFloat(form.lat)]"
        :zoom="15"
      >
        <MapboxMarker :lng-lat="[parseFloat(form.lon), parseFloat(form.lat)]" color="#2563eb" />
      </MapboxMap>


      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="name">Name</label>
          <input v-model="form.name" id="name" type="text" class="input w-full"  />
        </div>
        <div class="mb-4" v-if="!page.props.selectedFarm">
          <label class="block text-sm font-medium mb-1" for="farm_id">Farm</label>
          <select v-model="form.farm_id" id="farm_id" class="input w-full" required>
            <option value="" disabled>Select a farm</option>
            <option v-for="farm in farms" :key="farm.id" :value="farm.id">{{ farm.name }}</option>
          </select>
        </div>
        <div class="mb-4" v-else>
          <label class="block text-sm font-medium mb-1" for="farm_id">Farm</label>
          <input type="hidden" v-model="form.farm_id" id="farm_id" />
          <input :value="page.props.selectedFarm.name" class="input w-full bg-gray-100" readonly />
        </div>
        <div class="flex items-center mb-4 gap-2">
          <Switch v-model="allowEdit" id="allow-edit-switch" />
          <label for="allow-edit-switch" class="text-sm font-medium select-none cursor-pointer">Allow manual edit of QR & Location fields</label>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="type">Type</label>
          <div v-if="allowEdit">
            <select v-model="form.type" id="type" class="input w-full" required>
              <option value="" disabled>Select type</option>
              <option v-for="t in SensorTypes" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div v-else>
            <input v-model="form.type" id="type" type="text" class="input w-full" :disabled="true" required />
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="uuid">Sensor UUID</label>
          <input v-model="form.uuid" id="uuid" type="text" class="input w-full" :disabled="!allowEdit" required />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="device_uuid">Device UUID</label>
          <input v-model="form.device_uuid" id="device_uuid" type="text" class="input w-full" :disabled="!allowEdit" required />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="lat">Latitude</label>
          <input v-model="form.lat" id="lat" type="number" step="any" class="input w-full" required />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1" for="lon">Longitude</label>
          <input v-model="form.lon" id="lon" type="number" step="any" class="input w-full" required />
        </div>
        <div class="flex justify-end gap-2">
          <Link :href="route('sensors.index')">
            <Button type="button" variant="secondary">Cancel</Button>
          </Link>
          <Button type="submit">Add Sensor</Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
npx shadcn-vue@latest add switch
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { computed, reactive, ref, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { QrcodeStream } from 'vue-qrcode-reader';
import { MapboxMap, MapboxMarker } from '@studiometa/vue-mapbox-gl';
import Switch from '@/components/ui/switch/Switch.vue'
import 'mapbox-gl/dist/mapbox-gl.css';

const page = usePage();



// Type for farms to fix TS errors
interface Farm { id: number; name: string; }
const SensorTypes = computed(() => (page.props.SensorTypes as string[]) ?? []);
const farms = computed(() => (page.props.farms as Farm[]) ?? []);

const breadcrumbs = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Sensors', href: '/sensors' },
  { title: 'Add Sensor', href: null },
];

const form = reactive({
  name: '',
  farm_id: '',
  type: '',
  uuid: '',
  device_uuid: '',
  lat: '',
  lon: '',
});

const showScanner = ref(false);
const mapboxToken = import.meta.env.VITE_MAPBOX_TOKEN;

const mapCenter = ref([0, 0]);
const allowEdit = ref(false);

function submit() {
  //router.post(route('sensors.store'), form);
  router.post(route('sensors.scan'), form);
}

function onDetect(detectedCodes: any[]) {
  if (detectedCodes && detectedCodes.length > 0) {
    try {
      const data = JSON.parse(detectedCodes[0].rawValue);
      if (data.uuid) form.uuid = data.uuid;
      if (data.device_uuid) form.device_uuid = data.device_uuid;
      if (data.type) form.type = data.type;
      showScanner.value = false;
    } catch {
      alert('Invalid QR code format.');
    }
  }
}

function onQrError(error: any) {
  alert('QR Scanner Error: ' + error.message);
}

function getLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((position) => {
      form.lat = position.coords.latitude.toString();
      form.lon = position.coords.longitude.toString();
    }, (err) => {
      alert('Could not get location: ' + err.message);
    });
  } else {
    alert('Geolocation is not supported by this browser.');
  }
}

// Optionally, get location on mount
getLocation();

onMounted(() => {
  if (page.props.selectedFarm) {
    form.farm_id = page.props.selectedFarm.id;
  }
});

watch(() => page.props.selectedFarm, (selectedFarm) => {
  if (selectedFarm) {
    form.farm_id = selectedFarm.id;
  }
});
</script>

<style scoped>
.input {
  border: 1px solid #e5e7eb; /* fallback for border */
  border-radius: 0.375rem; /* rounded-md */
  padding: 0.5rem 0.75rem; /* px-3 py-2 */
  font-size: 0.875rem; /* text-sm */
  outline: none;
  transition: box-shadow 0.2s;
}
.input:focus {
  box-shadow: 0 0 0 2px var(--color-primary, #2563eb);
}



</style>
