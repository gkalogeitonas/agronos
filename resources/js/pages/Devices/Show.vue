<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import { Card, CardHeader, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import SensorCard from '@/components/SensorCard.vue';
import { computed, ref } from 'vue';

const page = usePage();
const device = computed<any>(() => (page.props as any).device);
const sensors = computed<any[]>(() => (page.props as any).sensors ?? []);
const showMqtt = ref(false);
const toggleMqtt = () => { showMqtt.value = !showMqtt.value; };

const hasMqttCredentials = computed<boolean>(() => {
    return Boolean(device.value?.mqtt_username || device.value?.mqtt_password);
});

const mqttCredentials = computed<any>(() => (page.props as any).mqtt_credentials ?? null);
const form = useForm();
const createMqtt = () => { form.post(route('devices.mqtt.create', device.value.id)); };

const batteryReading = computed<number | null>(() => {
    const prop = (page.props as any).batteryReading;
    if (prop !== undefined && prop !== null) {
        return typeof prop === 'number' ? prop : (isNaN(Number(prop)) ? null : Number(prop));
    }
    return device.value?.battery_level ?? null;
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Devices', href: '/devices' },
    { title: device.value?.name || 'Device', href: '#' },
];
</script>

<template>

    <Head :title="device?.name ?? 'Device Details'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col h-full gap-6 p-4 w-full max-w-7xl mx-auto">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between gap-4 bg-muted/20">
                    <div class="flex flex-col gap-1 min-w-[180px]">
                        <span class="text-xs text-muted-foreground">UUID</span>
                        <span class="font-mono text-sm">{{ device.uuid }}</span>
                        <span class="text-xs text-muted-foreground mt-1">Type</span>
                        <span class="text-sm">{{ device.type }}</span>
                    </div>
                    <div class="flex-1 flex flex-col items-start justify-center">
                        <CardDescription class="text-lg font-semibold">{{ device.name }}</CardDescription>
                    </div>
                    <div class="flex flex-col items-end gap-2 min-w-[120px]">
                        <span class="device-status" :class="device.status">{{ device.status }}</span>
                        <div class="text-right">
                            <div class="text-xs text-muted-foreground">Battery</div>
                            <div class="text-sm">{{ batteryReading !== null ? batteryReading + '%' : 'N/A' }}</div>
                        </div>
                    </div>
                </CardHeader>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-muted-foreground">Created At</div>
                            <div>{{ device.created_at }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Last Seen</div>
                            <div>{{ device.last_seen_at || 'Never' }}</div>
                        </div>
                    </div>
                </div>
            </Card>
            <Card v-if="hasMqttCredentials && !(mqttCredentials && mqttCredentials.created)" class="mt-2">
                <CardHeader class="flex items-center justify-between bg-muted/10">
                    <CardDescription class="text-lg font-semibold">MQTT Credentials</CardDescription>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" @click="toggleMqtt">{{ showMqtt ? 'Hide' : 'Show' }}</Button>
                    </div>
                </CardHeader>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs text-muted-foreground">Username</div>
                            <div class="font-mono text-sm">{{ device.mqtt_username ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Password</div>
                            <div class="font-mono text-sm">{{ device.mqtt_password ? (showMqtt ? device.mqtt_password : '•'.repeat(8)) : 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </Card>
            <Card v-if="!hasMqttCredentials" class="mt-2">
                <CardHeader>
                    <CardTitle>MQTT Credentials</CardTitle>
                    <CardDescription>No MQTT credentials are set for this device.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="text-right text-sm text-muted-foreground p-4">
                        <Button variant="outline" @click="createMqtt">Set MQTT Credentials</Button>
                    </div>
                </CardContent>
            </Card>
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Sensors</CardTitle>
                    <CardDescription>All sensors registered to this device</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="sensors.length === 0" class="text-muted-foreground">No sensors found for this device.
                    </div>
                    <div v-else class="grid grid-cols-1 gap-4">
                        <SensorCard v-for="sensor in sensors" :key="sensor.id" :sensor="sensor" />
                    </div>
                </CardContent>
            </Card>
            <Link :href="route('devices.index')">
                <Button variant="outline">Back to List</Button>
            </Link>
        </div>
    </AppLayout>
</template>

<style scoped lang="postcss">
.device-status.registered { color: #16a34a; font-weight: 600; }
.device-status.inactive,
.device-status.offline { color: #9ca3af; font-weight: 600; }
.device-status.error { color: #dc2626; font-weight: 600; }
.device-status.online { color: #2563eb; font-weight: 600; }
</style>
