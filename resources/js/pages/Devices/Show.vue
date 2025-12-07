<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Card, CardHeader, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import SensorCard from '@/components/SensorCard.vue';
import { computed } from 'vue';

const page = usePage();
const device = computed(() => page.props.device);
const sensors = computed<any[]>(() => (page.props as any).sensors ?? []);

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
                        <span :class="statusClass(device.status)">{{ device.status }}</span>
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
                        <div>
                            <div class="text-xs text-muted-foreground">Battery Level</div>
                            <div>{{ device.battery_level !== null ? device.battery_level + '%' : 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Signal Strength</div>
                            <div>{{ device.signal_strength !== null ? device.signal_strength : 'N/A' }}</div>
                        </div>
                    </div>
                </div>
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

<script lang="ts">
export default {
    methods: {
        statusClass(status: string) {
            switch (status) {
                case 'registered':
                    return 'text-green-600 font-semibold';
                case 'inactive':
                    return 'text-gray-400';
                case 'error':
                    return 'text-red-600 font-semibold';
                case 'online':
                    return 'text-blue-600 font-semibold';
                case 'offline':
                    return 'text-gray-400 font-semibold';
                default:
                    return '';
            }
        },
    },
};
</script>
