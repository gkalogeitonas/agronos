import type { ActivityItem } from '@/components/agronos/ActivityFeed.vue';
import type { AlertItem } from '@/components/agronos/AlertList.vue';

/**
 * Placeholder data for parts of the redesign the backend does not provide yet.
 *
 * Every export here maps to a follow-up task (label `redesign-followup`). Pages that
 * render any of it show the SAMPLE DATA pill. Remove each export once its real data lands.
 */

/** Follow-up: alerts (rules for moisture threshold, device offline, low battery). */
export const sampleAlerts: AlertItem[] = [
    { id: 'gateway', title: 'Gateway not reporting', meta: 'Hilltop Greenhouse · 18 min ago', tone: 'danger' },
    { id: 'zone-b', title: 'Zone B below threshold', meta: 'North Orchard · 12 min ago', tone: 'warning' },
    { id: 'battery', title: 'Low battery', meta: 'Riverside Fields · 2 devices', tone: 'warning' },
    { id: 'firmware', title: 'Firmware update available', meta: 'North Orchard · 3 devices', tone: 'info' },
];

/** Follow-up: activity feed (organisation and per device). */
export const sampleActivity: ActivityItem[] = [
    { id: 1, when: '12 min', subject: 'North Orchard', text: 'Zone B soil moisture dropped below the irrigation threshold' },
    { id: 2, when: '18 min', subject: 'Hilltop Greenhouse', text: 'LoRa gateway stopped reporting' },
    { id: 3, when: '3 h', subject: 'Riverside Fields', text: '2 devices added' },
    { id: 4, when: '5 h', subject: 'North Orchard', text: 'Firmware update available for 3 devices' },
];

/** Follow-up: farm zones. */
export const sampleZoneCount = 4;

/** Follow-up: alerts. Stable fake open-alert count per farm until alerts exist. */
export const sampleOpenAlertsForFarm = (farmId: number): number => farmId % 3;

/** Follow-up: sparkline trends from InfluxDB. Shape of a 24 h trend per sensor type. */
export const sampleTrends: Record<string, number[]> = {
    moisture: [46, 45, 45.5, 43, 43.5, 41, 41.5, 40, 39.5],
    temperature: [17, 18, 19, 21, 23, 24, 25, 24, 24.5],
    humidity: [64, 65, 63.5, 64.5, 64, 65.5, 64.5, 65, 64.5],
    devices: [12, 12, 12, 12, 12, 10, 10, 12, 12],
};

/**
 * Follow-up: per-zone time series. 24 h of hourly soil moisture for two zones, ending now.
 */
export function sampleMoistureSeries(now: Date = new Date()): { name: string; data: { x: number; y: number }[] }[] {
    const zoneA = [44, 43.5, 44, 42.5, 43, 41.5, 42, 41, 40, 40.5, 39, 39.5, 38, 38.5, 37.5, 38, 37, 36.5, 37, 36, 35.5, 36, 35, 35.5];
    const zoneB = [38, 37.5, 38, 36.5, 36, 36.5, 35, 35.5, 34.5, 34, 34.5, 33.5, 34, 33, 33.5, 32.5, 33, 32, 32.5, 31.5, 32, 31, 31.5, 31];
    const point = (y: number, index: number) => ({ x: now.getTime() - (23 - index) * 3600_000, y });

    return [
        { name: 'Zone A', data: zoneA.map(point) },
        { name: 'Zone B', data: zoneB.map(point) },
    ];
}

/** Follow-up: irrigation threshold per farm or zone. */
export const sampleIrrigationThreshold = 32;

/** Follow-up: device firmware reporting. */
export const sampleFirmwareVersion = '[VERSION]';

/** Follow-up: per-device activity feed. */
export const sampleDeviceActivity: ActivityItem[] = [
    { id: 1, when: '9 min', text: 'Last uplink received through the LoRa gateway' },
    { id: 2, when: '42 min', text: 'Soil moisture came within 5% of the irrigation threshold' },
    { id: 3, when: '3 h', text: 'Uplink received, signal -101 dBm' },
    { id: 4, when: '1 day', text: 'Firmware check completed' },
];

/** Follow-up: actuators and irrigation valve control. */
export const sampleActuators = [
    { id: 1, name: 'Zone A valve', farm: 'North Orchard', kind: 'Irrigation valve', state: 'Closed', lastRun: '6 h' },
    { id: 2, name: 'Zone B valve', farm: 'North Orchard', kind: 'Irrigation valve', state: 'Closed', lastRun: '1 day' },
    { id: 3, name: 'Bay 1 vent', farm: 'Hilltop Greenhouse', kind: 'Vent motor', state: 'Open', lastRun: '25 min' },
];
