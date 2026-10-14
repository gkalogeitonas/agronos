import type { ActivityItem } from '@/components/agronos/ActivityFeed.vue';
import type { AlertItem } from '@/components/agronos/AlertList.vue';

/**
 * Placeholder data for parts of the redesign the backend does not provide yet.
 *
 * Every export here maps to a follow-up task (label `redesign-followup`). Pages that
 * render any of it show the SAMPLE DATA pill. Remove each export once its real data lands.
 */

/** Organisation shown in the sidebar footer. Follow-up: organisation / tenant name. */
export const sampleOrganisation = {
    name: 'Your tenant name',
};

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
