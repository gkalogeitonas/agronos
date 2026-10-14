<script setup lang="ts">
import ActivityFeed from '@/components/agronos/ActivityFeed.vue';
import ComingSoonButton from '@/components/agronos/ComingSoonButton.vue';
import DescriptionList from '@/components/agronos/DescriptionList.vue';
import LineChart from '@/components/agronos/LineChart.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import SensorTable from '@/components/agronos/SensorTable.vue';
import StatTile from '@/components/agronos/StatTile.vue';
import StatusBadge from '@/components/agronos/StatusBadge.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useLiveSensorReadings } from '@/composables/useLiveSensorReadings';
import { useTimestamp } from '@/composables/useTimestamp';
import AppLayout from '@/layouts/AppLayout.vue';
import { deviceTypeLabel, formatReading, isLowBattery } from '@/lib/format';
import { sampleDeviceActivity, sampleFirmwareVersion } from '@/lib/sampleData';
import { sensorTypeLabel, sensorUnits, signalColors } from '@/lib/theme';
import { type BreadcrumbItem as BreadcrumbItemType, type DeviceSummary } from '@/types';
import type { Sensor } from '@/types/sensor';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    device: DeviceSummary & {
        uuid: string;
        created_at: string;
        lora_frame_counter: number | null;
        mqtt_username: string | null;
        mqtt_password: string | null;
    };
    sensors: (Sensor & { farm?: { id: number; name: string } | null })[];
    farms: { id: number; name: string }[];
    batteryReading: number | string | null;
    batteryTimeSeries?: { time: string; value: number }[];
}>();

const page = usePage();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Devices', href: route('devices.index') },
    { title: props.device.name, href: route('devices.show', props.device.id) },
];
const { formatAge, formatTimestamp } = useTimestamp();
const liveReadings = useLiveSensorReadings(props.sensors);

const isLoRa = computed(() => props.device.type === 'lora');
const battery = computed<number | null>(() => {
    const value = props.batteryReading ?? props.device.battery_level;
    return value === null || value === '' || Number.isNaN(Number(value)) ? null : Number(value);
});

/** The two most informative sensor readings, followed by battery and signal. */
const readingTiles = computed(() =>
    props.sensors
        .filter((sensor) => sensor.type !== 'battery')
        .slice(0, 2)
        .map((sensor) => ({
            id: sensor.id,
            label: sensor.type === 'temperature' ? 'Air temperature' : sensorTypeLabel(sensor.type),
            value: formatReading(liveReadings[sensor.id]?.value),
            unit: sensorUnits[sensor.type] ?? '',
            note: sensor.name,
        })),
);

const batteryChart = computed(() =>
    props.batteryTimeSeries && props.batteryTimeSeries.length > 1
        ? [
              {
                  name: 'Battery',
                  color: signalColors.leaf,
                  data: props.batteryTimeSeries.map((point) => ({ x: new Date(point.time).getTime(), y: Number(point.value) })),
              },
          ]
        : null,
);

const info = computed(() => [
    { mono: true, label: 'Protocol', value: deviceTypeLabel(props.device.type) },
    { mono: true, label: 'Device ID', value: props.device.uuid },
    ...(isLoRa.value
        ? [
              { mono: true, label: 'Encryption', value: 'AES-128-CTR' },
              { mono: true, label: 'Frame counter', value: String(props.device.lora_frame_counter ?? 0) },
          ]
        : []),
    { mono: true, label: 'Firmware', value: sampleFirmwareVersion },
    { mono: true, label: 'Last seen', value: props.device.last_seen_at ? `${formatAge(props.device.last_seen_at)} ago` : 'Never' },
    { mono: true, label: 'Registered', value: formatTimestamp(props.device.created_at).slice(0, 10) },
]);

const showMqttPassword = ref(false);
const mqttCredentials = computed(() => (page.props as { mqtt_credentials?: { created?: boolean } }).mqtt_credentials ?? null);
const hasMqttCredentials = computed(() => Boolean(props.device.mqtt_username || props.device.mqtt_password));
const mqttForm = useForm({});
const createMqttCredentials = () => mqttForm.post(route('devices.mqtt.create', props.device.id));

const comingSoon = 'Coming soon: device commands are not available yet';
</script>

<template>
    <Head :title="device.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader :title="device.name" sample-data>
                <template #meta>
                    <StatusBadge :status="device.status" />
                    <span v-if="farms.length > 0" class="text-body text-muted-foreground">
                        <template v-for="(farm, index) in farms" :key="farm.id"
                            ><template v-if="index > 0">, </template
                            ><Link :href="route('farms.show', farm.id)" class="text-muted-foreground hover:text-info">{{ farm.name }}</Link></template
                        >
                    </span>
                </template>
                <template #actions>
                    <ComingSoonButton variant="outline" :reason="comingSoon">Restart device</ComingSoonButton>
                    <ComingSoonButton :reason="comingSoon">Update firmware</ComingSoonButton>
                </template>
            </PageHeader>

            <section aria-label="Latest readings" class="flex flex-wrap gap-4">
                <StatTile v-for="tile in readingTiles" :key="tile.id" :label="tile.label" :value="tile.value" :unit="tile.unit" :note="tile.note" />
                <StatTile
                    label="Battery"
                    :value="battery ?? '–'"
                    :unit="battery !== null ? '%' : ''"
                    :note="battery === null ? 'Not reported' : isLowBattery(battery) ? 'Low, replace soon' : 'No action needed'"
                />
                <StatTile
                    label="Signal"
                    :value="device.signal_strength ?? '–'"
                    :unit="device.signal_strength !== null ? 'dBm' : ''"
                    :note="device.signal_strength === null ? 'Not reported' : `Via ${deviceTypeLabel(device.type)}`"
                />
            </section>

            <div class="flex flex-wrap items-stretch gap-4">
                <SectionCard title="Battery, last 24 h" class="flex-[2_1_480px]">
                    <div
                        v-if="batteryTimeSeries === undefined"
                        class="h-[240px] animate-pulse rounded-md bg-muted"
                        aria-label="Loading battery readings"
                    ></div>
                    <LineChart
                        v-else-if="batteryChart"
                        :series="batteryChart"
                        unit="%"
                        label="Line chart of this device's battery level over the last 24 hours"
                    />
                    <div
                        v-else
                        class="flex h-[240px] items-center justify-center rounded-md bg-muted/60 p-6 text-center text-body text-muted-foreground"
                    >
                        No battery readings in the last 24 hours.
                    </div>
                </SectionCard>

                <SectionCard title="Device information" class="flex-[1_1_280px]">
                    <DescriptionList :items="info" />
                </SectionCard>
            </div>

            <div class="flex flex-wrap items-stretch gap-4">
                <SectionCard title="Sensors on this device" class="flex-[2_1_480px]">
                    <template #actions>
                        <Link :href="route('sensors.index')" class="text-sm font-medium text-info hover:text-foreground">All sensors</Link>
                    </template>
                    <SensorTable :sensors="sensors" :readings="liveReadings" min-width="520px" empty-text="No sensors on this device yet." />
                </SectionCard>

                <SectionCard title="Irrigation valve" class="flex-[1_1_280px]">
                    <template #title-extra>
                        <span class="rounded-full bg-sun px-2 py-0.5 font-mono text-micro text-foreground">PROPOSED</span>
                    </template>
                    <p class="m-0 text-body text-ink-soft">
                        Closed. A manual or agent-controlled irrigation run could be started here once actuators are supported.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <ComingSoonButton :reason="comingSoon">Open valve</ComingSoonButton>
                        <ComingSoonButton variant="outline" :reason="comingSoon">Set schedule</ComingSoonButton>
                    </div>
                </SectionCard>
            </div>

            <SectionCard v-if="!(hasMqttCredentials && mqttCredentials?.created)" title="MQTT credentials">
                <template v-if="hasMqttCredentials">
                    <dl class="m-0 grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <dt class="text-sm text-muted-foreground">Username</dt>
                            <dd class="m-0 font-mono text-sm">{{ device.mqtt_username ?? '–' }}</dd>
                        </div>
                        <div class="flex flex-col gap-1">
                            <dt class="text-sm text-muted-foreground">Password</dt>
                            <dd class="m-0 flex items-center gap-3 font-mono text-sm">
                                {{ device.mqtt_password ? (showMqttPassword ? device.mqtt_password : '•'.repeat(8)) : '–' }}
                                <Button v-if="device.mqtt_password" variant="ghost" size="sm" @click="showMqttPassword = !showMqttPassword">
                                    {{ showMqttPassword ? 'Hide' : 'Show' }}
                                </Button>
                            </dd>
                        </div>
                    </dl>
                </template>
                <div v-else-if="!hasMqttCredentials" class="flex flex-wrap items-center justify-between gap-3">
                    <p class="m-0 text-body text-muted-foreground">No MQTT credentials are set for this device.</p>
                    <Button variant="outline" :disabled="mqttForm.processing" @click="createMqttCredentials">Set MQTT credentials</Button>
                </div>
            </SectionCard>

            <SectionCard title="Device activity">
                <ActivityFeed :items="sampleDeviceActivity" />
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
