import type { ActivityItem } from '@/components/agronos/ActivityFeed.vue';

/**
 * Placeholder data for parts of the redesign the backend does not provide yet.
 *
 * Every export here maps to a follow-up task (label `redesign-followup`). Pages that
 * render any of it show the SAMPLE DATA pill. Remove each export once its real data lands.
 */

/** Follow-up: activity feed (organisation and per device). */
export const sampleActivity: ActivityItem[] = [
    { id: 1, when: '12 min', subject: 'North Orchard', text: 'Zone B soil moisture dropped below the irrigation threshold' },
    { id: 2, when: '18 min', subject: 'Hilltop Greenhouse', text: 'LoRa gateway stopped reporting' },
    { id: 3, when: '3 h', subject: 'Riverside Fields', text: '2 devices added' },
    { id: 4, when: '5 h', subject: 'North Orchard', text: 'Firmware update available for 3 devices' },
];

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
